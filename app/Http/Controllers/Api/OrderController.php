<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LaravelDaily\Invoices\Classes\Buyer;          // vendor imports
use LaravelDaily\Invoices\Classes\InvoiceItem;    // vendor imports
use LaravelDaily\Invoices\Invoice;                // vendor imports

class OrderController extends Controller
{
    public function __construct(private OrderService $service, private Mailer $mailer) {}

    // ===================================
    // Customer placing order from cart
    // ===================================

    public function store(Request $request)
    {
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

        // New macro method — never existed on Mailer before
        $this->mailer->sendOrderConfirmation(
            $request->user()->email,
            $order
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order,
            'placed_at' => Carbon::parse($order->created_at)->format('F d, Y h:i A'),
            'estimated_delivery' => Carbon::now()->addDays(7)->format('F d, Y'),
        ], 201);
    }

    // ======================================
    // Customer - viewing their own order
    // ======================================

    public function index(Request $request)
    {
        return response()->json(
            $this->service->getUserOrders($request->user()->id)
        );
    }

    // ==================================
    // Customer - view single order
    // ==================================

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

    // ===========================
    // Admin - view all order
    // ===========================

    public function adminIndex()
    {
        return response()->json(
            $this->service->getAllOrders()
        );
    }

    // =================================
    // Admin - retrieve similar order
    // =================================

    public function specificOrder()
    {

        return response()->json(
            $this->service->getSimilarOrders()
        );
    }

    // ===================================
    // Admin- update order status
    // ===================================

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
}
