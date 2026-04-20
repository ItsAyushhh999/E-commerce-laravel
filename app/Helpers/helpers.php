<?php

use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Support\Carbon;

// Formats price to Rs. 1,234.00

if (! function_exists('format_price')) {
    function format_price(float $amount): string
    {
        return 'Rs. '.number_format($amount, 2);
    }
}

// Date formatting to human readable format

if (! function_exists('human_date')) {
    function human_date($date): string
    {
        return Carbon::parse($date)->format('M d, Y');
    }
}

// SKU generation based on product name and attribute values

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
