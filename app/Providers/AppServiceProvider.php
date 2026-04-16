<?php

namespace App\Providers;

use App\Contracts\CartRepositoryInterface;
use App\Contracts\OrderRepositoryInterface;
use App\Contracts\ProductsRepositoryInterface;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductsRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProductsRepositoryInterface::class, ProductsRepository::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
