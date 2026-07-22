<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Expire pending orders past their expiration window and restore stock')]
class ExpireOrders extends Command
{
    public function __construct(private OrderService $orderService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = $this->orderService->expireOldOrders();

        $this->info("Expired {$count} orders and restored stock.");
    }
}
