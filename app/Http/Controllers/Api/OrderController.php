<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;
use Stripe\Exception\ApiErrorException;

class OrderController extends Controller
{
    public function __construct(private OrderService $service,
        private Mailer $mailer,
        private StripeService $stripeService
    ) {}

    //
    // Places an order for the authenticated user based on their cart contents, checks stock, creates order and sends confirmation email
    //

    public function store(Request $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $cacheKey = "idempotency:order:{$idempotencyKey}";
            $cached = Cache::get($cacheKey);

            if ($cached) {
                return response()->json($cached, 201);
            }
        }

        $result = $this->service->placeOrder(
            $request->user()->id,
            $request->user()->email
        );

        if (isset($result['cart_empty'])) {
            return response()->json(['message' => 'Your cart is empty'], 400);
        }

        if (isset($result['stock_error'])) {
            return response()->json([
                'message' => "Not enough stock for {$result['stock_error']}",
            ], 400);
        }

        $order = $result['order'];

        // Create PaymentIntent — amount in cents
        $paymentIntent = $this->stripeService->createPaymentIntent(
            amountInCents: (int) round($order->total_price * 100),
            metadata: [
                'order_id' => $order->id,
                'email' => $request->user()->email,
            ]
        );

        // Store PaymentIntent ID on order
        $order->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
        ]);

        $responseData = [
            'message' => 'Order created. Complete payment to confirm.',
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'total_price' => $order->total_price,
            ],
            'order_id' => $order->id,
            'status' => $order->status,
            'client_secret' => $paymentIntent->client_secret,
            'amount' => $order->total_price,
            'currency' => 'usd',
            'placed_at' => Carbon::parse($order->created_at)->format('F d, Y h:i A'),
        ];

        if ($idempotencyKey) {
            Cache::put($cacheKey, $responseData, now()->addHours(24));
        }

        return response()->json($responseData, 201);
    }

    //
    // Customer/Admin - refund a completed order, restock items, and update status
    //
    public function refund(Request $request, $id)
    {
        $isAdmin = $request->user()->is_admin ?? false; // adjust based on your auth setup

        $result = $this->service->refundOrder($id, $request->user()->id, $isAdmin);

        if (isset($result['not_found'])) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (isset($result['invalid_status'])) {
            return response()->json(['message' => 'Only completed orders can be refunded'], 400);
        }

        $order = $result['order'];

        try {
            $refund = $this->stripeService->refund($order->stripe_payment_intent_id);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Refund failed: '.$e->getMessage()], 400);
        }

        $this->service->markAsRefunded($order);

        return response()->json([
            'message' => 'Refund issued successfully',
            'refund_id' => $refund->id,
            'order_id' => $order->id,
        ]);
    }

    public function statusHistory(Request $request, $id)
    {
        $order = $this->service->getUserOrder($request->user()->id, $id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'current_status' => $order->status,
            'history' => $order->statusHistories()->orderBy('created_at')->get(),
        ]);
    }

    //
    // Returns a list of orders for the authenticated user with their details and grouped items by SKU and size
    //

    public function index(Request $request)
    {
        return response()->json(
            $this->service->getUserOrders($request->user()->id)
        );
    }

    //
    // Returns the details of a specific order for the authenticated user, including items grouped by SKU and size
    //

    public function show(Request $request, $id)
    {
        $order = $this->service->getUserOrder($request->user()->id, $id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Group items by SKU then size
        $groupedItems = collect($order->items)
            ->map(fn ($item) => [
                'sku' => $item->productVariant->sku,
                'size' => $item->productVariant->size,
                'color' => $item->productVariant->color,
                'name' => $item->productVariant->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])
            ->groupByMultiple(['sku', 'size']);

        return response()->json([
            'order' => $order,
            'grouped_items' => $groupedItems,
        ]);
    }

    //
    // Admin - retrieve all orders with their details and grouped items by SKU and size
    //

    public function adminIndex()
    {
        return response()->json(
            $this->service->getAllOrders()
        );
    }

    //
    // Admin - retrieve details of a specific order with items grouped by SKU and size
    //

    public function specificOrder()
    {

        return response()->json(
            $this->service->getSimilarOrders()
        );
    }

    //
    // Admin - update the status of an order (pending, processing, completed, cancelled)
    //

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        $order = $this->service->updateStatus($id, $request->status);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'message' => 'Order status updated',
            'order' => $order,
            'updated_at_formatted' => Carbon::parse($order->updated_at)->format('F d, Y h:i A'),
            'updated_ago' => Carbon::parse($order->updated_at)->diffForHumans(),
        ]);
    }

    // Group by sku, size
    public function similargroup(Request $request)
    {
        $order = Order::where('orders.id', $request->id)
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->select(
                'product_variants.sku',
                'product_variants.size',
                'product_variants.color',
                'products.name',
                'product_variants.price',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as line_count')
            )
            ->groupBy('product_variants.sku', 'product_variants.size', 'product_variants.color')
            ->orderBy('product_variants.sku', 'asc')
            ->get();

        return response()->json([
            'similar_items' => $order,
        ]);
    }

    // =======================================
    // Gives invoice of the order placed
    // =======================================

    public function downloadInvoice(Request $request, $id)
    {
        $order = Order::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('items.productVariant.product')
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        $customer = new Buyer([
            'name' => $request->user()->name,
            'custom_fields' => [
                'email' => $request->user()->email,
            ],
        ]);

        $invoiceItems = $order->items->map(function ($item) {
            return InvoiceItem::make($item->productVariant->product->name)
                ->pricePerUnit($item->price)
                ->quantity($item->quantity);
        })->toArray();

        $invoice = Invoice::make()
            ->buyer($customer)
            ->serialNumberFormat('INV-{SEQUENCE}')
            ->addItems($invoiceItems);

        return $invoice->download("invoice-order-{$order->id}.pdf");
    }

    public function employees(Request $request)
    {
        $products = ProductVariant::with(['product', 'orderItems.order'])
            ->when($request->filled('name'), fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', '%'.$request->name.'%')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->to))
            ->orderBy('created_at', 'DESC')
            ->paginate(250);

        return response()->json([
            'data' => $products,
        ]);
    }

    public function optimiz(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
        ]);

        $orders = Order::with(['user', 'items'])
            ->where('user.id', $request->search)
            ->orWhere('tasks.title', 'like', '%'.$request->search.'%')
            ->orWhere('tasks.description', 'like', '%'.$request->search.'%')
            ->orWhereHas('comments', function ($query) use ($request) {
                $query->where('comments', 'like', '%'.$request->search.'%');
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->select('tasks.id', 'tasks.title', 'tasks.description', 'tasks.project_id', 'tasks.status', 'tasks.priority')
            ->orderBy('created_at', 'desc')
            ->paginate(250);

        return response()->json($orders);
    }
}
