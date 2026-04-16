<?php

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function createOrder(array $data): Order;

    public function findByUser(int $userId): Collection;

    public function findByIdAndUser(int $orderId, int $userId): ?Order;

    public function getAllOrders(): Collection;

    public function getSimilarOrders(): Collection;

    public function updateStatus(int $orderId, string $status): ?Order;
}
