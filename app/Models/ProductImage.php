<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function product(): belongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Returns a full URL instead of the raw stored path
    public function getPathAttribute($value): string
    {
        return Storage::disk('public')->url($value);
    }
}
