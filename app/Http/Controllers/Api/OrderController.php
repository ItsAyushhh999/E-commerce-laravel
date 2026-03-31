<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use LaravelDaily\Invoices\Classes\Buyer;          // vendor imports
use LaravelDaily\Invoices\Classes\InvoiceItem;    // vendor imports
use LaravelDaily\Invoices\Invoice;                // vendor imports

class OrderController extends Controller
{
    // ===================================
    // Customer placing order from cart
    // ===================================

    public function store(Request $request)
    {
        $cartItems = Cart::where('user_id', $request->user()->id)
            ->with('productVariant')
            ->get();

        // is cart empty?
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty',
            ], 400);
        }

        // checking stock
        foreach ($cartItems as $item) {
            if ($item->productVariant->stock < $item->quantity) {
                return response()->json([
                    'message' => "Not enough stock for {$item->productVariant->sku}",
                ], 400);
            }
        }

        $total = $cartItems->sum(function ($item) {
            return $item->productVariant->price * $item->quantity;
        });

        // creating order
        $order = Order::create([
            'user_id' => $request->user()->id,
            'total_price' => $total,
            'status' => 'pending',
        ]);

        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $item->product_variant_id,
                'price' => $item->productVariant->price,
                'quantity' => $item->quantity,
            ]);

            // deducting stock
            $item->productVariant->decrement('stock', $item->quantity);
        }

        // clearing cart after order placed
        Cart::where('user_id', $request->user()->id)
            ->delete();

        // sending email confirmed
        Mail::to($request->user()->email)
            ->send(new OrderPlaced($order->load('items.productVariant.product')));

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order->load('items.productVariant.product'),

            // Carbon additions
            'placed_at' => Carbon::parse($order->created_at)->format('F d, Y h:i A'),
            'estimated_delivery' => Carbon::now()->addDays(7)->format('F d, Y'),
        ], 201);
    }

    // ======================================
    // Customer - viewing their own order
    // ======================================

    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.productVariant.product')
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // ==================================
    // Customer - view single order
    // ==================================

    public function show(Request $request, $id)
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

        $order->placed_at = Carbon::parse($order->created_at)->format('F d, Y h:i A');
        $order->time_ago = Carbon::parse($order->created_at)->diffForHumans();
        $order->estimated_delivery = Carbon::parse($order->created_at)->addDays(7)->format('F d, Y');

        return response()->json($order);
    }

    // ===========================
    // Admin - view all order
    // ===========================

    public function adminIndex()
    {
        $orders = Order::with(['user', 'items.productVariant.product'])
            ->latest()
            ->get();

        // Carbon addition
        $orders->transform(function ($order) {
            $order->placed_at = Carbon::parse($order->created_at)->format('F d, Y h:i A');
            $order->time_ago = Carbon::parse($order->created_at)->diffForHumans();
            $order->estimated_delivery = Carbon::parse($order->created_at)->addDays(7)->format('F d, Y');

            return $order;
        });

        return response()->json($orders);
    }

    // =================================
    // Admin - retrieve similar order
    // =================================

    public function specificOrder()
    {

        $allOrders = Order::with(['items.productVariant'])->get();

        $similarOrders = $allOrders->map(function ($order) {
            $similar = $order->items
                ->map(fn ($item) => $item->productVariant->sku.':'.$item->quantity)
                ->sort()
                ->implode(',');

            return [
                'order' => $order,
                'similar' => $similar,
            ];
        });

        $exactOrders = $similarOrders
            ->groupBy('similar')
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => $group->pluck('order'))
            ->values();

        return response()->json($exactOrders);
    }

    // ===================================
    // Admin- update order status
    // ===================================

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Order status updated',
            'order' => $order->load('items.productVariant.product'),

            // Carbon addition
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
