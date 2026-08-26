<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/') . '/reset-password'
            . '?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        // ->view() remplace entièrement le rendu markdown par défaut de
        // MailMessage : on garde la classe Notification (pratique pour
        // ->subject(), la queue, etc.) mais le HTML vient intégralement de
        // notre propre template (voir resources/views/emails/).
        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->view('emails.reset-password', [
                'url' => $url,
                'expireMinutes' => config('auth.passwords.users.expire', 60),
            ]);
    }
}
