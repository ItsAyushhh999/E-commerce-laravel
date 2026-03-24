<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    //customer placing order from cart
    public function store(Request $request)
    {
        $cartItems = Cart::where('user_id', $request->user()->id)
                        ->with('productVariant')
                        ->get();

        //is cart empty?
        if($cartItems->isEmpty())
            {
                return response()->json([
                    'message' => 'Your cart is empty',
                ], 400);
            }

        //checking stock
        foreach($cartItems as $item)
            {
                if($item->productVariant->stock < $item->quantity)
                    {
                        return response()->json([
                            'message' => "Not enough stock for {$item->productVariant->sku}",
                        ], 400);
                    }
            }

        $total = $cartItems->sum(function($item)
        {
            return $item->productVariant->price * $item->quantity;
        });

        //creating order
        $order = Order::create([
            'user_id' => $request->user()->id,
            'total_price' => $total,
            'status' => 'pending',
        ]);

        foreach($cartItems as $item)
            {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item->product_variant_id,
                    'price' => $item->productVariant->price,
                    'quantity' => $item->quantity,
                ]);

                //deducting stock
                $item->productVariant->decrement('stock', $item->quantity);
            }

        //clearing cart after order placed
        Cart::where('user_id', $request->user()->id)
            ->delete();
        
        //sending email confirmed
        Mail::to($request->user()->email)
            ->send(new OrderPlaced($order->load('items.productVariant.product')));
        
        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order->load('items.productVariant.product'),
        ], 201);
    }

    //customer - view own order
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
                        ->with('items.productVariant.product')
                        ->latest()
                        ->get();

        return response()->json($orders);
    }

    //customer - view single order
    public function show(Request $request, $id)
    {
        $order = Order::where('id', $id)
                        ->where('user_id', $request->user()->id)
                        ->with('items.productVariant.product')
                        ->first();
        
        if(!$order)
            {
                return response()->json([
                    'message' => 'Order not found',
                ], 404);
            }
        
        return response()->json($order);
    }

    //admin - view all order
    public function adminIndex()
    {
        $orders = Order::with(['user', 'items.productVariant.product'])
                        ->latest()
                        ->get();

        return response()->json($orders);
    }

    //admin- update order status
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if(!$order)
            {
                return response()->json([
                    'message' => 'Order not found',
                ], 404);
            }

        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        $order->update(['status'=> $request->status]);

        return response()->json([
            'message' => 'Order status updated',
            'order' => $order->load('items.productVariant.product'),
        ]);
    }


}
