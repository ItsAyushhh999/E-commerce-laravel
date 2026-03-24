<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateOrderStatuses implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Update pending orders to processing
        Order::where('status', 'pending')
            ->update(['status' => 'processing']);

        // Update processing orders to completed
        Order::where('status', 'processing')
            ->update(['status' => 'completed']);

        // Randomly cancel some orders (10% chance)
        // Get all orders that are not already completed or cancelled
        $ordersToCancel = Order::whereIn('status', ['pending', 'processing'])
            ->inRandomOrder()
            ->limit((int) ceil(Order::whereIn('status', ['pending', 'processing'])->count() * 0.1))
            ->get();

        foreach ($ordersToCancel as $order) {
            $order->update(['status' => 'cancelled']);
        }
    }
}
