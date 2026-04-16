<?php

namespace App\Services;

use App\Contracts\OrderRepositoryInterface;
use App\Mail\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function __construct(private OrderRepositoryInterface $orderRepository)
    {
        //
    }

    // ===================================
    // Customer placing order from cart
    // ===================================

    public function placeOrder(int $userId, string $email): array
    {
        $cartItems = Cart::where('user_id', $userId)
            ->with('productVariant')
            ->get();

        if ($cartItems->isEmpty()) {
            return ['cart_empty' => true];
        }

        foreach ($cartItems as $item) {
            if ($item->productVariant->stock < $item->quantity) {
                return ['stock_error' => $item->productVariant->sku];
            }
        }

        $total = $cartItems->sum(fn ($item) => $item->productVariant->price * $item->quantity);

        $order = $this->orderRepository->createOrder([
            'user_id' => $userId,
            'total_price' => $total,
            'status' => 'pending',
        ]);

        foreach ($cartItems as $item) {
            $this->orderRepository->createOrderItem([
                'order_id' => $order->id,
                'product_variant_id' => $item->product_variant_id,
                'price' => $item->productVariant->price,
                'quantity' => $item->quantity,
            ]);

            $item->productVariant->decrement('stock', $item->quantity);
        }

        Cart::where('user_id', $userId)->delete();

        $order->load(['items.productVariant.product', 'items.productVariant.attributeValues.attribute']);

        Mail::to($email)->send(new OrderPlaced($order));

        return ['order' => $order];
    }

    // ======================================
    // Customer - viewing their own order
    // ======================================

    public function getUserOrders(int $userId)
    {
        return $this->orderRepository->findByUser($userId);
    }

    public function getUserOrder(int $userId, int $orderId): ?Order
    {
        $order = $this->orderRepository->findbyIdAndUser($orderId, $userId);

        if (! $order) {
            return null;
        }

        $order->placed_at = Carbon::parse($order->created_at)->format('F d, Y h:i A');
        $order->time_ago = Carbon::parse($order->created_at)->diffForHumans();
        $order->estimated_delivery = Carbon::parse($order->created_at)->addDays(7)->format('F d, Y');

        return $order;
    }

    // ====================================
    // Admin - view all order
    // ====================================

    public function getAllOrders()
    {
        $orders = $this->orderRepository->getAllOrders();

        return $orders->transform(function ($order) {
            $order->placed_at = Carbon::parse($order->created_at)->format('F d, Y h:i A');
            $order->time_ago = Carbon::parse($order->created_at)->diffForHumans();
            $order->estimated_delivery = Carbon::parse($order->created_at)->addDays(7)->format('F d, Y');

            return $order;
        });
    }

    // ====================================
    // Admin - view similar orders
    // ====================================

    public function getSimilarOrders()
    {
        $allOrders = $this->orderRepository->getSimilarOrders();

        $similarOrders = $allOrders->map(function ($order) {
            $similar = $order->items
                ->map(fn ($item) => $item->productVariant->sku.':'.$item->quantity)
                ->sort()
                ->implode(',');

            return ['order' => $order, 'similar' => $similar];
        });

        return $similarOrders
            ->groupBy('similar')
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => $group->pluck('order'))
            ->values();
    }

    // =================================
    // Admin - update order status
    // =================================

    public function updateStatus(int $orderId, string $status): ?Order
    {
        return $this->orderRepository->updateStatus($orderId, $status);
    }
}
