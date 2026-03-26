<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value
                ? asset('storage/'.$value)
                : null
        );
    }
}
