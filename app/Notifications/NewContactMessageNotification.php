<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Notifications\Notification;

class NewContactMessageNotification extends Notification
{
    public function __construct(private Contact $contact) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    // Enregistré en base (canal 'database') pour alimenter la cloche de
    // notifications du dashboard admin. L'envoi push (FCM) est déclenché
    // séparément par ContactController via PushNotificationService, pour
    // garder un contrôle explicite sur qui reçoit un push vs juste une
    // notif en base.
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Nouveau message de contact',
            'body' => "{$this->contact->name} ({$this->contact->email}) : {$this->contact->subject}",
            'contact_id' => $this->contact->id,
            'link' => '/staff/contacts',
        ];
    }
}
