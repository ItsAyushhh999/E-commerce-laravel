<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products
        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
        });

        // Product Variants
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('price');
            $table->index('stock');
        });

        // Carts
        Schema::table('carts', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('product_variant_id');
            $table->unique(['user_id', 'product_variant_id']);
        });

        // Orders
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Order Items
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['price']);
            $table->dropIndex(['stock']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropUnique(['user_id', 'product_variant_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['product_variant_id']);
        });
    }
};
