<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booted(function () {
        // Login - 5 attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by('login:'.$request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in a minute.',
                    ], 429);
                });
        });

        // Register - 3 attempts per minute per IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)
                ->by('register:'.$request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many register attempts. Please try again in a minute.',
                    ], 429);
                });
        });

        // General API - 60 requests per minute per user or IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by('api:'.($request->user()?->id ?: $request->ip()))
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many requests. Please slow down.',
                    ], 429);
                });
        });

        // Orders - 10 per minute per user
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(10)
                ->by('orders:'.($request->user()?->id ?: $request->ip()))
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many order requests. Please slow down.',
                    ], 429);
                });
        });
    })->create();
