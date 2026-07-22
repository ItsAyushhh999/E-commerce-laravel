<?php

use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::prefix('v1')->group(function () {
    require __DIR__.'/api_versions/v1.php';
});
