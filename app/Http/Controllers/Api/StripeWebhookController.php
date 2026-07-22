<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;

class StripeWebhookController extends Controller
{
    public function __construct(protected StripeService $stripeService, protected OrderService $orderService) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'charge.refunded' => $this->handleChargeRefunded($event->data->object),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    protected function handlePaymentSucceeded(PaymentIntent $paymentIntent): void
    {
        Log::info('handlePaymentSucceeded fired', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $order || $order->status === 'completed') {
            return; // idempotency guard — Stripe can send the same event more than once
        }

        if ($order->status === 'expired') {
            Log::warning("Payment succeeded for already-expired order #{$order->id}. Auto-refunding.", [
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            try {
                $this->stripeService->refund($paymentIntent->id);
            } catch (ApiErrorException $e) {
                Log::error("Failed to auto-refund expired order #{$order->id}: ".$e->getMessage());
            }

            return;
        }

        $oldStatus = $order->status; // capture BEFORE update
        $order->update(['status' => 'completed']);
        $this->orderService->logStatusChange($order, $oldStatus, 'completed');

        // Send confirmation email — moved here from OrderService
        $email = $paymentIntent->metadata->email ?? null;

        if ($email) {
            $order->load(['items.productVariant.product', 'items.productVariant.attributeValues.attribute']);
            Mail::to($email)->send(new OrderPlaced($order));
        }

        Log::info("Order #{$order->id} marked as completed.");
    }

    protected function handlePaymentFailed(PaymentIntent $paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $order || $order->status === 'payment_failed') {
            return; // idempotency guard
        }

        $order->update(['status' => 'cancelled']);

        // Restore stock — Option A
        $order->load('items.productVariant');

        foreach ($order->items as $item) {
            $item->productVariant->increment('stock', $item->quantity);
        }

        Log::info("Order #{$order->id} payment failed. Stock restored.");
    }

    protected function handleChargeRefunded(Charge $charge): void
    {
        if (! $charge->payment_intent) {
            return;
        }

        $order = Order::where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if (! $order) {
            return;
        }

        if (in_array($order->status, ['refunded', 'expired'])) {
            if ($order->status === 'expired') {
                $order->update(['status' => 'refunded']);
                Log::info("Order #{$order->id} status synced from expired to refunded (stock already restored, skipped).");
            }

            return;
        }

        $order->load('items.productVariant');

        foreach ($order->items as $item) {
            $item->productVariant->increment('stock', $item->quantity);
        }

        $oldStatus = $order->status; // capture BEFORE update
        $order->update(['status' => 'refunded']);
        $this->orderService->logStatusChange($order, $oldStatus, 'refunded');

        Log::info("Order #{$order->id} marked as refunded via webhook.");
    }
}
