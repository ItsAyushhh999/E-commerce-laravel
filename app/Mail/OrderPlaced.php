<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmation #'.$this->order->id,
        );
    }

    public function content(): Content
    {
        $items = '';
        foreach ($this->order->items as $item) {
            $subtotal = number_format($item->price * $item->quantity, 2);
            $items .= "
                - {$item->productVariant->product->name}
                  ({$item->productVariant->color} / {$item->productVariant->size})
                  Qty: {$item->quantity} x \${$item->price} = \${$subtotal}
            ";
        }

        $total = number_format($this->order->total_price, 2);
        $date = $this->order->created_at->format('M d, Y h:i A');
        $name = $this->order->user->name;
        $orderId = $this->order->id;
        $status = ucfirst($this->order->status);

        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #f53003; color: white; padding: 20px; text-align: center;'>
                <h1>Order Confirmed!</h1>
            </div>

            <div style='padding: 20px;'>
                <p>Hi <strong>{$name}</strong>,</p>
                <p>Thank you for your order! Here are your details:</p>

                <p><strong>Order ID:</strong> #{$orderId}</p>
                <p><strong>Status:</strong> {$status}</p>
                <p><strong>Date:</strong> {$date}</p>

                <h3>Items Ordered:</h3>
                <pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>{$items}</pre>

                <p style='font-size:18px; font-weight:bold;'>Total: \${$total}</p>

                <p>We will notify you when your order status changes.</p>
                <p>Thank you for shopping with us!</p>
            </div>
        </div>
        ";

        return new Content(htmlString: $html);
    }
}
