<?php

namespace App\Notifications;

use App\Models\Device;
use Illuminate\Notifications\Notification;

class DeviceWentOfflineNotification extends Notification
{
    public function __construct(private Device $device) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Passerelle SMS déconnectée',
            'body' => "\"{$this->device->name}\" ne répond plus depuis 2 minutes. Vérifiez que le téléphone est allumé et connecté au réseau.",
            'device_id' => $this->device->id,
            'link' => '/admin/devices',
        ];
    }
}
