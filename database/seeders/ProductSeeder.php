<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    const TARGET_TOTAL = 3_000_000;

    const BATCH_SIZE = 2_000;

    public function run(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        DB::connection()->disableQueryLog();

        // Additive: no truncate. Truncating here would orphan every existing
        // product_variant that references a product_id we just deleted.
        $existing = DB::table('products')->count();
        $needed = self::TARGET_TOTAL - $existing;

        if ($needed <= 0) {
            $this->command->info("Already at or above target ({$existing}). Nothing to do.");

            return;
        }

        $this->command->info("Have {$existing}, adding ".number_format($needed).' more to reach '.number_format(self::TARGET_TOTAL).'...');

        $totalBatches = (int) ceil($needed / self::BATCH_SIZE);
        $added = 0;

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $count = min(self::BATCH_SIZE, $needed - $added);

            $rows = Product::factory()
                ->count($count)
                ->make()
                ->map(fn ($product) => $product->getAttributes())
                ->all();

            DB::table('products')->insert($rows);
            $added += $count;

            if ($batch % 5 === 0) {
                $this->command->info('Products added: '.number_format($added).' / '.number_format($needed));
            }
        }

        $this->command->info('Products done! Total: '.number_format(DB::table('products')->count()));
    }
}
