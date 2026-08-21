<?php

namespace App\Notifications;

use App\Models\Plan;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SubscriptionActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Plan $plan)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $dashboardUrl = rtrim(config('app.frontend_url'), '/') . '/admin/abonnement';
        $isPaid = (float) $this->plan->price > 0;

        $mail = (new MailMessage)
            ->subject('Votre plan ' . $this->plan->name . ' est activé')
            ->greeting('Merci pour votre confiance !');

        $mail = $isPaid
            ? $mail->line('Votre paiement a été confirmé et votre plan ' . $this->plan->name . ' est maintenant actif.')
            : $mail->line('Votre plan ' . $this->plan->name . ' est maintenant actif.');

        return $mail
            ->line('Quota mensuel : ' . $this->plan->sms_quota_monthly . ' SMS.')
            ->action('Voir mon abonnement', $dashboardUrl)
            ->line('Vous pouvez suivre votre consommation à tout moment depuis votre tableau de bord.');
    }
}
