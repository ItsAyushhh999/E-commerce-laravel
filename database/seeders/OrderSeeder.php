<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    const TOTAL_ORDERS = 7_200_000;

    const BATCH_SIZE = 10_000;

    const TXN_GROUP_SIZE = 20;

    public function run(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        DB::connection()->disableQueryLog();

        // Temporarily relax the fsync-per-commit guarantee for this seeding run.
        // At 1 (the safe default, correct for real order/payment data), every
        // transaction commit forces a disk fsync , with TXN_GROUP_SIZE=20 that's
        // still one fsync per 200k-order group, which adds up over 7.2M rows.
        // At 2, MySQL still writes the log on commit but only fsyncs once a
        // second, cutting disk I/O further. Requires SUPER/SYSTEM_VARIABLES_ADMIN
        // privilege , harmless to skip if that fails, just slower.
        // NEVER leave this at 2 outside of local seeding , restored in finally.
        $originalFlushSetting = DB::selectOne('SELECT @@GLOBAL.innodb_flush_log_at_trx_commit AS val')->val;

        try {
            DB::statement('SET GLOBAL innodb_flush_log_at_trx_commit = 2');
            $this->command->info('Set innodb_flush_log_at_trx_commit=2 for this run.');
        } catch (\Throwable $e) {
            $this->command->warn('Could not change innodb_flush_log_at_trx_commit (needs privilege) , continuing at current setting.');
        }

        try {
            $this->seedAll();
        } finally {
            try {
                DB::statement("SET GLOBAL innodb_flush_log_at_trx_commit = {$originalFlushSetting}");
                $this->command->info("Restored innodb_flush_log_at_trx_commit={$originalFlushSetting}.");
            } catch (\Throwable $e) {
                $this->command->warn("Could not restore innodb_flush_log_at_trx_commit , please set it back to {$originalFlushSetting} manually.");
            }
        }
    }

    private function seedAll(): void
    {
        // min/max id instead of loading every user/variant id into a PHP array.
        // At 12M orders this matters a lot more than it did at 100k , no array
        // ever grows with TOTAL_ORDERS, so memory stays flat regardless of scale.
        $minUserId = DB::table('users')->min('id');
        $maxUserId = DB::table('users')->max('id');

        $minVariantId = DB::table('product_variants')->min('id');
        $maxVariantId = DB::table('product_variants')->max('id');

        $statuses = ['pending', 'processing', 'completed', 'cancelled'];

        $existingOrders = DB::table('orders')->count();
        if ($existingOrders >= self::TOTAL_ORDERS) {
            $this->command->info("Orders already seeded ({$existingOrders}), skipping.");
        } else {
            $this->seedOrders($minUserId, $maxUserId, $statuses);
        }

        $this->seedOrderItems($minVariantId, $maxVariantId);
        $this->backfillPricesAndTotals();

        $this->command->info('Orders:      '.number_format(DB::table('orders')->count()));
        $this->command->info('Order items: '.number_format(DB::table('order_items')->count()));
        $this->command->info('Done!');
    }

    private function seedOrders(int $minUserId, int $maxUserId, array $statuses): void
    {
        $this->command->info('Seeding '.number_format(self::TOTAL_ORDERS).' orders...');

        $now = now()->format('Y-m-d H:i:s');
        $totalBatches = (int) ceil(self::TOTAL_ORDERS / self::BATCH_SIZE);
        $done = 0;

        foreach (array_chunk(range(0, $totalBatches - 1), self::TXN_GROUP_SIZE) as $batchGroup) {
            DB::transaction(function () use ($batchGroup, $minUserId, $maxUserId, $statuses, $now, &$done) {
                foreach ($batchGroup as $batch) {
                    $rows = [];
                    for ($i = 0; $i < self::BATCH_SIZE; $i++) {
                        $rows[] = [
                            'user_id' => rand($minUserId, $maxUserId),
                            'total_price' => 0, // backfilled at the end via one UPDATE JOIN
                            'status' => $statuses[array_rand($statuses)],
                            'created_at' => now()->subDays(rand(0, 730))->format('Y-m-d H:i:s'),
                            'updated_at' => $now,
                        ];
                    }

                    DB::table('orders')->insert($rows);
                    $done += self::BATCH_SIZE;

                    if ($batch % 20 === 0) {
                        $this->command->info(
                            'Orders: '.number_format($done).' / '.number_format(self::TOTAL_ORDERS)
                        );
                    }
                }
            });
        }
    }

    /**
     * Inserts order_items with a random product_variant_id and price = 0.
     * No variant price lookup here , that would mean holding all variant prices
     * in memory (1M+ rows) for the entire run. Prices are backfilled in one SQL
     * pass afterward instead, which is both simpler and much faster at this scale.
     */
    private function seedOrderItems(int $minVariantId, int $maxVariantId): void
    {
        // Resume point: since an order's items are only flushed to the DB after
        // ALL of that order's items are generated (never split mid-order across
        // batches), the highest order_id already present is guaranteed to have
        // complete, committed items. Safe to continue strictly after it.
        $lastProcessedOrderId = DB::table('order_items')->max('order_id') ?? 0;

        if ($lastProcessedOrderId > 0) {
            $this->command->info("Resuming order items from order_id > {$lastProcessedOrderId}...");
        } else {
            $this->command->info('Seeding order items...');
        }

        $now = now()->format('Y-m-d H:i:s');
        $itemRows = [];
        $totalItems = 0;
        $batchesInTxn = 0;

        DB::beginTransaction();

        // orderBy('id') is required by lazy() , it uses the last seen id to fetch
        // the next page under the hood, so it needs a deterministic sort order.
        foreach (
            DB::table('orders')
                ->select('id')
                ->where('id', '>', $lastProcessedOrderId)
                ->orderBy('id')
                ->lazy(5000) as $order
        ) {
            $itemCount = 1;
            for ($i = 0; $i < $itemCount; $i++) {
                $itemRows[] = [
                    'order_id' => $order->id,
                    'product_variant_id' => rand($minVariantId, $maxVariantId),
                    'quantity' => rand(1, 5),
                    'price' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($itemRows) >= self::BATCH_SIZE) {
                DB::table('order_items')->insert($itemRows);
                $totalItems += count($itemRows);
                $itemRows = [];

                if (++$batchesInTxn >= self::TXN_GROUP_SIZE) {
                    DB::commit();
                    DB::beginTransaction();
                    $batchesInTxn = 0;
                    $this->command->info('Order items inserted so far: '.number_format($totalItems));
                }
            }
        }

        if (! empty($itemRows)) {
            DB::table('order_items')->insert($itemRows);
            $totalItems += count($itemRows);
        }

        DB::commit();

        $this->command->info('Order items inserted: '.number_format($totalItems));
    }

    /**
     * Two single SQL statements instead of any PHP-side price lookups:
     * 1. Pull each item's price from its variant.
     * 2. Sum item totals back onto the parent order.
     * At 12M+ orders this is drastically faster than anything done row-by-row in PHP.
     */
    private function backfillPricesAndTotals(): void
    {
        $this->command->info('Backfilling order_items prices from variants...');
        $this->backfillOrderItemPrices();

        $this->command->info('Updating order totals...');
        DB::statement('
            UPDATE orders o
            JOIN (
                SELECT order_id, SUM(price * quantity) AS total
                FROM order_items
                GROUP BY order_id
            ) oi ON oi.order_id = o.id
            SET o.total_price = oi.total
        ');
    }

    private function backfillOrderItemPrices(int $chunkSize = 10000): void
    {
        $this->command->info('Backfilling order_items prices from variants in chunks...');

        $maxId = DB::table('order_items')->max('id') ?? 0;
        for ($start = 0; $start <= $maxId; $start += $chunkSize) {
            $end = $start + $chunkSize;

            $updated = DB::update(
                'UPDATE order_items oi
                 JOIN product_variants pv ON pv.id = oi.product_variant_id
                 SET oi.price = pv.price
                 WHERE oi.id > ? AND oi.id <= ?',
                [$start, $end]
            );

            if ($updated > 0) {
                $this->command->info('Backfilled order_items ids '.($start + 1).' to '.$end.': '.number_format($updated));
            }
        }

        $this->command->info('order_items prices backfilled.');
    }
}
