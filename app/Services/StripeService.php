<?php

namespace App\Services;

use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createPaymentIntent(int $amountInCents, string $currency = 'usd', array $metadata = []): PaymentIntent
    {
        return $this->stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => $currency,
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true,
                'allow_redirects' => 'never', ],
        ]);
    }

    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }

    public function refund(string $paymentIntentId, ?int $amountInCents = null): Refund
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amountInCents !== null) {
            $params['amount'] = $amountInCents; // partial refund if specified
        }

        return $this->stripe->refunds->create($params);
    }
}
