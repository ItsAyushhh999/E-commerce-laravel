<?php

namespace App\Providers;

use Illuminate\Mail\Mailer;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerCollectionMacros();
        $this->registerMailerMacros();
    }

    private function registerCollectionMacros(): void
    {
        Collection::macro('groupByMultiple', function (array $keys) {

            // Take the first key out of the array
            $firstKey = array_shift($keys);

            // Group the collection by that first key
            $grouped = $this->groupBy($firstKey);

            // If no more keys left, return the grouped result as is
            if (empty($keys)) {
                return $grouped;
            }

            // If more keys remain, go deeper into each group and call groupByMultiple again with remaining keys
            return $grouped->map(function ($group) use ($keys) {
                return $group->groupByMultiple($keys);
            });
        });
    }

    private function registerMailerMacros(): void
    {
        Mailer::macro('sendOrderConfirmation', function (string $email, $order) {
            logger()->info('sendOrderConfirmation macro fired', [
                'email' => $email,
                'order_id' => $order->id,
            ]);

            // raw() takes a plain string — no blade view needed
            return $this->raw(
                "Your order #{$order->id} has been placed successfully. Total: {$order->total_price}",
                fn ($message) => $message
                    ->to($email)
                    ->subject("Order Confirmation #{$order->id}")
            );
        });
    }
}
