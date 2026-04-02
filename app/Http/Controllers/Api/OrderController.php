<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use LaravelDaily\Invoices\Classes\Buyer;          // vendor imports
use LaravelDaily\Invoices\Classes\InvoiceItem;    // vendor imports
use LaravelDaily\Invoices\Invoice;                // vendor imports

class OrderController extends Controller
{
    public function __construct(private OrderService $service) {}

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
