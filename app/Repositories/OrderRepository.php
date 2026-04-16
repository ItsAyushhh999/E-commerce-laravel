<?php

namespace App\Repositories;

use App\Contracts\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function createOrder(array $data): Order
    {
        return Order::create($data);
    }

    public function createOrderItem(array $data): OrderItem
    {
        return OrderItem::create($data);
    }

    public function findByUser(int $userId): Collection
    {
        return Order::where('user_id', $userId)
            ->with(['items.productVariant.product', 'items.productVariant.attributeValues.attribute'])
            ->latest()
            ->get();
    }

    public function findByIdAndUser(int $orderId, int $userId): ?Order
    {
        return Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->with(['items.productVariant.product', 'items.productVariant.attributeValues.attribute'])
            ->first();
    }

    public function getAllOrders(): Collection
    {
        return Order::with(['user', 'items.productVariant.product', 'items.productVariant.attributeValues.attribute'])
            ->latest()
            ->get();
    }

    public function getSimilarOrders(): Collection
    {
        return Order::with(['items.productVariant'])->get();
    }

    public function updateStatus(int $orderId, string $status): ?Order
    {
        $order = Order::find($orderId);

        if (! $order) {
            return null;
        }

        $order->update(['status' => $status]);

        return $order->load(['items.productVariant.product', 'items.productVariant.attributeValues.attribute']);
    }
}
