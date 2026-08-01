<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The branded confirmation email, replacing Laravel's default.
 *
 * The default is a perfectly good generic email — which is exactly the
 * problem. This is the first thing a new member receives from RealVows, and
 * it should look like the platform they just joined.
 */
class VerifyEmailAddress extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage())
            ->subject('Confirm your email · RealVows')
            ->view('emails.confirm-email', ['url' => $url]);
    }
}
