<?php

namespace App\Mail;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseReceipt extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Purchase $purchase) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your RealVows receipt — '.$this->purchase->reference());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-receipt',
            with: [
                'firstName' => $this->purchase->user?->profile?->greetingName(),
                'reference' => $this->purchase->reference(),
                'amount' => $this->purchase->amountFormatted(),
                'credits' => $this->purchase->credits_granted,
                'paidOn' => $this->purchase->completed_at?->format('j F Y'),

                /*
                 * ⚠️ Read from settings, never from a constant. The company
                 * that settles the money is not the name the member sees, so
                 * the descriptor has to travel with the receipt.
                 */
                'billing' => app(\App\Support\Billing::class)->forDisplay(),

                // Signed, so it opens straight from the email with no session.
                'receiptUrl' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'receipt',
                    now()->addYears(2),
                    ['order' => $this->purchase->order_identifier]
                ),
            ],
        );
    }
}
