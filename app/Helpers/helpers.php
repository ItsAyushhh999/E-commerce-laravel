<?php

use App\Models\AttributeValue;
use App\Models\Product;
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
    function generate_sku(Product $product, array $attributeValueIds): string
    {
        $productPrefix = strtoupper(substr(str_replace(' ', '', $product->name), 0, 3));

        $values = AttributeValue::whereIn('id', $attributeValueIds)
            ->pluck('value')
            ->map(fn ($value) => strtoupper(substr($value, 0, 3)))
            ->join('-');

        return $productPrefix.'-'.$product->id.'-'.$values;
    }
}
