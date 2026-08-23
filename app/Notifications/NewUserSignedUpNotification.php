<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class NewUserSignedUpNotification extends Notification
{
    public function __construct(private User $newUser) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Nouvelle inscription',
            'body' => "{$this->newUser->name} ({$this->newUser->email}) vient de créer un compte.",
            'user_id' => $this->newUser->id,
            'link' => '/staff/users',
        ];
    }
}
