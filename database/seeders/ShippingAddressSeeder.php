<?php

namespace Database\Seeders;

use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingAddressSeeder extends Seeder
{
    const BATCH_SIZE = 5_000;

    const PRICE_BACKFILL_CHUNK_SIZE = 1_000;

    const MAX_RETRIES = 5;

    const RETRY_SLEEP_US = 250000; // 250ms

    // Coherent location sets (area, city, country) instead of independently
    // random pieces — avoids nonsense combos like a Nepali city with an
    // Australian state.
    private array $locations = [
        ['Lainchaur', 'Kathmandu', 'Nepal'],
        ['Thamel', 'Kathmandu', 'Nepal'],
        ['Baneshwor', 'Kathmandu', 'Nepal'],
        ['Patan', 'Lalitpur', 'Nepal'],
        ['Boudha', 'Kathmandu', 'Nepal'],
        ['Kalanki', 'Kathmandu', 'Nepal'],
        ['Jawalakhel', 'Lalitpur', 'Nepal'],
        ['Balkumari', 'Lalitpur', 'Nepal'],
        ['Kapan', 'Kathmandu', 'Nepal'],
        ['Maitighar', 'Kathmandu', 'Nepal'],
        ['Chabahil', 'Kathmandu', 'Nepal'],
        ['Kirtipur', 'Kathmandu', 'Nepal'],
        ['Sankhamul', 'Kathmandu', 'Nepal'],
        ['Durbarmarg', 'Kathmandu', 'Nepal'],
        ['Gairidhara', 'Kathmandu', 'Nepal'],
        ['Pulchowk', 'Lalitpur', 'Nepal'],
        ['Jhamsikhel', 'Lalitpur', 'Nepal'],
        ['Satdobato', 'Lalitpur', 'Nepal'],
        ['Maharajgunj', 'Kathmandu', 'Nepal'],
        ['Basantapur', 'Kathmandu', 'Nepal'],
        ['NewRoad', 'Kathmandu', 'Nepal'],
        ['Kamaladi', 'Kathmandu', 'Nepal'],
        ['Swayambhu', 'Kathmandu', 'Nepal'],
        ['Gongabu', 'Kathmandu', 'Nepal'],
        ['Chakrapath', 'Kathmandu', 'Nepal'],
        ['Bhatbhateni', 'Kathmandu', 'Nepal'],
        ['Koteshwor', 'Kathmandu', 'Nepal'],
        ['Maitidevi', 'Kathmandu', 'Nepal'],
        ['Teku', 'Kathmandu', 'Nepal'],
        ['Sinamangal', 'Kathmandu', 'Nepal'],
        ['Gwarko', 'Lalitpur', 'Nepal'],
    ];

    public function run(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        DB::connection()->disableQueryLog();

        // Must run before the batch loop below — total_price computed per
        // batch depends on order_items.price already being backfilled from
        // variant prices. This is a separate table/pass and can't be merged
        // into the orders batch update.
        $this->backfillOrderItemPrices();

        $remaining = DB::table('orders')->whereNull('shipping_address')->count();

        if ($remaining === 0) {
            $this->command->info('All orders already have a shipping_address. Nothing to do.');

            return;
        }

        $this->command->info("Backfilling shipping_address + total_price for {$remaining} orders...");

        $totalUpdated = 0;

        while (true) {
            $ids = DB::table('orders')
                ->whereNull('shipping_address')
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $cases = [];
            $caseBindings = [];

            foreach ($ids as $id) {
                $cases[] = 'WHEN ? THEN ?';
                $caseBindings[] = $id;
                $caseBindings[] = $this->randomAddress();
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Single UPDATE per batch: computes each order's total from its
            // items (scoped to just this batch's ids, not the whole table)
            // AND assigns shipping_address, in one pass over these rows —
            // instead of two separate full passes over `orders`.
            // LEFT JOIN + COALESCE guards against an order with zero items
            // (no matching row in the subquery) — total_price is left as-is
            // rather than being wiped to NULL.
            $sql = "UPDATE orders o
                LEFT JOIN (
                    SELECT order_id, SUM(price * quantity) AS total
                    FROM order_items
                    WHERE order_id IN ({$placeholders})
                    GROUP BY order_id
                ) t ON t.order_id = o.id
                SET o.shipping_address = CASE o.id ".implode(' ', $cases)." END,
                    o.total_price = COALESCE(t.total, o.total_price)
                WHERE o.id IN ({$placeholders})";

            // Binding order matches placeholder order in the SQL above:
            // 1) subquery's IN (...) ids
            // 2) CASE id/value pairs for shipping_address
            // 3) final WHERE IN (...) ids
            $bindings = array_merge($ids, $caseBindings, $ids);

            DB::update($sql, $bindings);

            $totalUpdated += count($ids);
            $this->command->info('Updated: '.number_format($totalUpdated).' / '.number_format($remaining));
        }

        $this->command->info('Done! Total updated: '.number_format($totalUpdated));
    }

    private function randomAddress(): string
    {
        [$area, $city, $country] = $this->locations[array_rand($this->locations)];

        return "{$area}, {$city}, {$country}";
    }

    private function backfillOrderItemPrices(): void
    {
        $this->command->info('Backfilling order_items prices from variants in chunks...');

        $maxId = DB::table('order_items')->max('id') ?? 0;

        for ($start = 0; $start <= $maxId; $start += self::PRICE_BACKFILL_CHUNK_SIZE) {
            $end = $start + self::PRICE_BACKFILL_CHUNK_SIZE;
            $attempt = 0;

            while (true) {
                try {
                    $updated = DB::update(
                        'UPDATE order_items oi
                         JOIN product_variants pv ON pv.id = oi.product_variant_id
                         SET oi.price = pv.price
                         WHERE oi.id > ? AND oi.id <= ?
                           AND (oi.price IS NULL OR oi.price <> pv.price)',
                        [$start, $end]
                    );

                    if ($updated > 0) {
                        $this->command->info('Backfilled order_items ids '.($start + 1).' to '.$end.': '.number_format($updated));
                    }

                    break;
                } catch (QueryException $e) {
                    $sqlState = $e->errorInfo[0] ?? null;
                    $mysqlCode = $e->errorInfo[1] ?? null;

                    // Retry only lock wait timeout (1205) / deadlock (1213)
                    $isRetryable = $sqlState === 'HY000' && in_array($mysqlCode, [1205, 1213], true);

                    if (! $isRetryable || $attempt >= self::MAX_RETRIES) {
                        throw $e;
                    }

                    $attempt++;
                    usleep(self::RETRY_SLEEP_US * $attempt);
                }
            }
        }

        $this->command->info('order_items prices backfilled.');
    }
}
