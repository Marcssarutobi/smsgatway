<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class TriggerWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public SmsMessage $sms) {}

    public function handle(): void
    {
        // On mappe le statut du SMS vers le nom d'évènement attendu par les webhooks
        $eventMap = [
            'sent' => 'sms.sent',
            'delivered' => 'sms.delivered',
            'failed' => 'sms.failed',
        ];

        $event = $eventMap[$this->sms->status] ?? null;

        if (!$event) {
            return; // statut sans webhook associé (ex: pending, queued)
        }

        $webhooks = Webhook::where('user_id', $this->sms->user_id)
            ->where('event', $event)
            ->where('active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            $this->send($webhook, $event);
        }
    }

    private function send(Webhook $webhook, string $event): void
    {
        $payload = [
            'event' => $event,
            'sms' => [
                'id' => $this->sms->id,
                'recipient' => $this->sms->recipient,
                'status' => $this->sms->status,
                'sent_at' => $this->sms->sent_at,
                'delivered_at' => $this->sms->delivered_at,
                'error_message' => $this->sms->error_message,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $webhook->secret);

        try {
            Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
            ])
                ->timeout(5)
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (\Throwable $e) {
            // On ne fait pas échouer tout le job SMS pour un webhook injoignable :
            // on journalise seulement, le client verra le SMS livré même si son webhook est down.
            report($e);
        }
    }
}
