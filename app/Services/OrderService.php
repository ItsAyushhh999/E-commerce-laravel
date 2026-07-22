<?php

namespace App\Services;

use App\Contracts\OrderRepositoryInterface;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Carbon;

// use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function __construct(private OrderRepositoryInterface $orderRepository)
    {
        //
    }

    //
    // Customer - place an order from the cart and cart is deleted after rder placed.
    //

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

        $subtotal = $cartItems->reduce(function ($carry, $item) {
            return $carry + ($item->productVariant->price * $item->quantity);
        }, 0);
        $total = round($subtotal * 1.13, 2);

        $order = $this->orderRepository->createOrder([
            'user_id' => $userId,
            'total_price' => $total,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
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

        return ['order' => $order];
    }

    //
    // Customer - can view all of their orders and details
    //

    public function getUserOrders(int $userId)
    {
        return $this->orderRepository->findByUser($userId);
    }

    //
    // Customer - can view a specific order and details
    //

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
    // Admin - view orders of all users
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

    //
    // Admin - view details of the exact same orders created by different users
    //

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

    //
    // Admin - updates the status of an order (e.g., pending, shipped, delivered, canceled).
    //

    public function updateStatus(int $orderId, string $status): ?Order
    {
        $order = $this->orderRepository->find($orderId);

        if (! $order) {
            return null;
        }

        $oldStatus = $order->status;
        $this->orderRepository->updateStatus($orderId, $status);
        $order->refresh();

        $this->logStatusChange($order, $oldStatus, $status);

        return $order->load(['items.productVariant.product', 'items.productVariant.attributeValues.attribute']);
    }

    // Refund logic
    public function refundOrder(int $orderId, int $userId, ?bool $isAdmin = false): array
    {
        $order = $isAdmin
            ? $this->orderRepository->find($orderId)
            : $this->orderRepository->findbyIdAndUser($orderId, $userId);

        if (! $order) {
            return ['not_found' => true];
        }

        if ($order->status !== 'completed') {
            return ['invalid_status' => true];
        }

        return ['order' => $order];
    }

    public function markAsRefunded(Order $order): void
    {
        $order->load('items.productVariant');

        foreach ($order->items as $item) {
            $item->productVariant->increment('stock', $item->quantity);
        }

        $oldStatus = $order->status; // capture BEFORE update
        $order->update(['status' => 'refunded']);
        $this->logStatusChange($order, $oldStatus, 'refunded');
    }

    public function expireOldOrders(): int
    {
        $expiredOrders = $this->orderRepository->findExpiredPendingOrders();

        foreach ($expiredOrders as $order) {
            foreach ($order->items as $item) {
                $item->productVariant->increment('stock', $item->quantity);
            }

            $order->update(['status' => 'expired']);
            $this->logStatusChange($order, 'pending', 'expired');
        }

        return $expiredOrders->count();
    }

    public function logStatusChange(Order $order, string $fromStatus, string $toStatus): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'created_at' => now(),
        ]);
    }
}
