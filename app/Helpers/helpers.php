<?php

use App\Models\ProductVariant;
use Illuminate\Support\Carbon;

// Format price
if (! function_exists('format_price')) {
    function format_price(float $amount): string
    {
        return 'Rs. '.number_format($amount, 2);
    }
}

// Human readable date
if (! function_exists('human_date')) {
    function human_date($date): string
    {
        return Carbon::parse($date)->format('M d, Y');
    }
}

// sku generate
if (! function_exists('generate_sku')) {
    function generate_sku(string $productName, string $color, string $size): string
    {
        $initials = collect(explode(' ', strtoupper($productName)))
            ->map(fn ($word) => substr($word, 0, 1))
            ->implode('');

        $colorCode = strtoupper(substr($color, 0, 3));
        $sizeCode = strtoupper($size);
        $base = "{$initials}-{$colorCode}-{$sizeCode}";
        $sku = $base;
        $count = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = "{$base}-{$count}";
            $count++;
        }

        return $sku;
    }
}
