<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants_attribute_values', function (Blueprint $table) {
            $table->index('product_variant_id');
            $table->index('attribute_value_id');
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->index('attribute_id');
            $table->index('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants_attribute_values', function (Blueprint $table) {
            $table->dropIndex(['product_variant_id']);
            $table->dropIndex(['attribute_value_id']);
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropIndex(['attribute_id']);
            $table->dropIndex(['value']);
        });
    }
};
