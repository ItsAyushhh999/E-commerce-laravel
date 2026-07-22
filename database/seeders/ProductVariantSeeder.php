<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductVariantSeeder extends Seeder
{
    const TARGET_VARIANTS = 5_500_000;

    const BATCH_SIZE = 5_000;

    const MIN_ATTRS_PER_VARIANT = 1;

    const MAX_ATTRS_PER_VARIANT = 3;

    const PIVOT_BATCH_SIZE = 5_000;

    public function run(): void
    {
        ini_set('memory_limit', '1G');
        set_time_limit(0);
        DB::connection()->disableQueryLog();

        $this->seedVariants();
        $this->seedPivotTable();

        $this->command->info('Variants: '.number_format(DB::table('product_variants')->count()));
        $this->command->info('Pivot rows: '.number_format(DB::table('product_variants_attribute_values')->count()));
        $this->command->info('Done!');
    }

    private function seedVariants(): void
    {
        $existing = DB::table('product_variants')->count();
        $needed = self::TARGET_VARIANTS - $existing;

        if ($needed <= 0) {
            $this->command->info("Variants already at or above target ({$existing}). Skipping.");

            return;
        }

        // Use the FULL current product id range (including products just added
        // by ProductSeeder), not just the range that existed when variants were
        // first seeded — otherwise new products would never get variants.
        $minProductId = DB::table('products')->min('id');
        $maxProductId = DB::table('products')->max('id');

        $this->command->info("Have {$existing} variants, adding ".number_format($needed).' to reach '.number_format(self::TARGET_VARIANTS).'...');

        $now = now()->format('Y-m-d H:i:s');
        $totalBatches = (int) ceil($needed / self::BATCH_SIZE);
        $added = 0;

        // SKU counter starts after existing count so new SKUs don't collide
        // with ones already generated (VAR-00000001 style, from before).
        $skuCounter = $existing;

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $count = min(self::BATCH_SIZE, $needed - $added);
            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $skuCounter++;
                $rows[] = [
                    'product_id' => rand($minProductId, $maxProductId),
                    'sku' => 'VAR-'.str_pad($skuCounter, 8, '0', STR_PAD_LEFT),
                    'price' => rand(500, 50000) / 100,
                    'stock' => rand(0, 200),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('product_variants')->insert($rows);
            $added += $count;

            if ($batch % 10 === 0) {
                $this->command->info('Variants added: '.number_format($added).' / '.number_format($needed));
            }
        }
    }

    /**
     * Only backfills pivot rows for variants that don't have any yet — found via
     * a LEFT JOIN ... WHERE NULL anti-join rather than truncating the whole pivot
     * table and rebuilding from scratch, which would waste all the pivot rows
     * already correctly seeded for existing variants.
     */
    private function seedPivotTable(): void
    {
        $this->command->info('Seeding pivot table for variants missing attribute values...');

        $attributeValueIds = DB::table('attribute_values')->pluck('id')->all();
        $attrCount = count($attributeValueIds);

        if ($attrCount === 0) {
            $this->command->warn('No attribute values found, skipping pivot seeding.');

            return;
        }

        $maxAttrsPerVariant = min(self::MAX_ATTRS_PER_VARIANT, $attrCount);
        $buffer = [];
        $totalInserted = 0;

        $variantsMissingPivot = DB::table('product_variants as pv')
            ->leftJoin('product_variants_attribute_values as pvav', 'pvav.product_variant_id', '=', 'pv.id')
            ->whereNull('pvav.product_variant_id')
            ->select('pv.id')
            ->orderBy('pv.id')
            ->lazy(5000);

        foreach ($variantsMissingPivot as $variant) {
            $howMany = rand(self::MIN_ATTRS_PER_VARIANT, $maxAttrsPerVariant);
            $keys = array_rand($attributeValueIds, $howMany);
            $keys = is_array($keys) ? $keys : [$keys];

            foreach ($keys as $key) {
                $buffer[] = [
                    'product_variant_id' => $variant->id,
                    'attribute_value_id' => $attributeValueIds[$key],
                ];
            }

            if (count($buffer) >= self::PIVOT_BATCH_SIZE) {
                DB::table('product_variants_attribute_values')->insert($buffer);
                $totalInserted += count($buffer);
                $buffer = [];
                $this->command->info('Pivot rows inserted so far: '.number_format($totalInserted));
            }
        }

        if (! empty($buffer)) {
            DB::table('product_variants_attribute_values')->insert($buffer);
            $totalInserted += count($buffer);
        }

        $this->command->info('Pivot rows inserted: '.number_format($totalInserted));
    }
}
