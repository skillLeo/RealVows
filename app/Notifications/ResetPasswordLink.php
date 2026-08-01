<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class ResetPasswordLink extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage())
            ->subject('Reset your RealVows password')
            ->view('emails.reset-password', [
                'url' => $url,
                // Stated in the email as well as on screen, because by the
                // time somebody opens this they have usually left the tab
                // that told them.
                'expiresMinutes' => Config::get('auth.passwords.users.expire', 60),
            ]);
    }
}
