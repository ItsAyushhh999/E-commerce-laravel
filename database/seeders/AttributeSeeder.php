<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $size = Attribute::create(['name' => 'size']);
        $size->values()->createMany([
            ['value' => 'XS'],
            ['value' => 'S'],
            ['value' => 'M'],
            ['value' => 'L'],
            ['value' => 'XL'],
            ['value' => 'XXL'],
        ]);

        $color = Attribute::create(['name' => 'color']);
        $color->values()->createMany([
            ['value' => 'Red'],
            ['value' => 'Blue'],
            ['value' => 'Black'],
            ['value' => 'White'],
            ['value' => 'Green'],
        ]);
    }
}
