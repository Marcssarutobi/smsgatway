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

        return (new MailMessage)
            ->subject('Votre plan ' . $this->plan->name . ' est activé')
            ->view('emails.subscription-activated', [
                'planName' => $this->plan->name,
                'isPaid' => (float) $this->plan->price > 0,
                'smsQuota' => $this->plan->sms_quota_monthly,
                'dashboardUrl' => $dashboardUrl,
            ]);
    }
}
