<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\DeviceSim;
use App\Models\SmsMessage;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    // Backoff progressif : laisse le temps à un téléphone de se reconnecter
    // (heartbeat toutes les 15s côté app) sans abandonner trop vite, tout en
    // évitant d'attendre indéfiniment si vraiment aucun device n'est dispo.
    public array $backoff = [10, 15, 20, 30, 45, 60, 90];

    public function __construct(public SmsMessage $sms) {}


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Le SMS a peut-être déjà été traité (sécurité en cas de retry)
        if ($this->sms->status !== 'pending') {
            return;
        }

        $subscription = $this->sms->user->activeSubscription;

        // Le canal est décidé par l'abonnement du client (choisi à la
        // souscription, voir PaymentController::activateSubscription), PAS
        // en essayant un device puis en retombant sur MTN "si aucun trouvé" :
        // ça évitait de facturer MTN pour un client Device dont le téléphone
        // est juste momentanément hors-ligne (son plan ne couvre pas ce coût).
        if ($subscription?->channel === 'network') {
            $this->sendViaMtn();
            return;
        }

        $deviceSim = $this->pickAvailableSim();

        if ($deviceSim) {
            $this->sms->update([
                'device_sim_id' => $deviceSim->id,
                'channel' => 'device',
                'status' => 'queued',
            ]);

            $this->sms->statusLogs()->create([
                'status' => 'queued',
                'details' => "Assigné à la SIM #{$deviceSim->id} (device #{$deviceSim->device_id})",
            ]);

            // Réveille l'app mobile concernée via FCM
            app(\App\Services\FcmService::class)->sendWakeUp($deviceSim->device);
            return;
        }

        // Client en mode Device sans téléphone dispo pour l'instant : on
        // retente plus tard (délai croissant), on ne bascule PAS vers MTN
        // même si MTN est activé globalement — ce n'est pas ce que ce client
        // a payé.
        $delay = $this->backoff[$this->attempts() - 1] ?? end($this->backoff);
        $this->release($delay);
    }

    // Envoi de secours via l'API MTN SMS v3 quand aucun téléphone appairé
    // n'est disponible. En cas d'échec MTN (réseau, credentials, refus), on
    // retombe sur le même mécanisme de retry/backoff que pour un device
    // indisponible plutôt que d'échouer immédiatement.
    private function sendViaMtn(): void
    {
        if (!config('services.mtn.enabled') || !filled(config('services.mtn.service_code'))) {
            // MTN pas (ou plus) configuré côté plateforme, alors qu'un client
            // a un abonnement Réseau actif : on retente plutôt que d'échouer
            // tout de suite, le temps qu'un admin corrige la config.
            \Illuminate\Support\Facades\Log::warning('Envoi Réseau demandé mais MTN désactivé/non configuré', [
                'sms_id' => $this->sms->id,
            ]);

            $delay = $this->backoff[$this->attempts() - 1] ?? end($this->backoff);
            $this->release($delay);
            return;
        }

        try {
            $result = \App\Services\MtnSmsService::forOrganisation($this->sms->user->organisation)->send(
                recipient: $this->sms->recipient,
                message: $this->sms->content,
                clientCorrelatorId: (string) $this->sms->id,
            );

            $this->sms->update([
                'channel' => 'mtn',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->sms->statusLogs()->create([
                'status' => 'sent',
                'details' => "Envoyé via l'API MTN (transactionId: " . ($result['transactionId'] ?? 'inconnu') . ')',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Échec envoi SMS via MTN', [
                'sms_id' => $this->sms->id,
                'error' => $e->getMessage(),
            ]);

            $delay = $this->backoff[$this->attempts() - 1] ?? end($this->backoff);
            $this->release($delay);
        }
    }

    private function pickAvailableSim(): ?DeviceSim
    {
        return DeviceSim::query()
            ->whereHas('device', function ($query) {
                $query->where('user_id', $this->sms->user_id)
                    ->where('status', 'online');
            })
            ->where('is_active', true)
            ->whereColumn('sent_today', '<', 'daily_quota')
            ->orderBy('sent_today', 'asc') // répartit la charge : la SIM la moins utilisée d'abord
            ->first();
    }

    public function failed(\Throwable $exception): void
    {
        // Le SMS a peut-être déjà été pris en charge par une SIM entre-temps
        // (course possible avec un retry tardif) : dans ce cas on ne touche à rien.
        if ($this->sms->fresh()->status !== 'pending') {
            return;
        }

        $subscription = $this->sms->user->activeSubscription;

        $reason = match (true) {
            $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException && $subscription?->channel === 'network'
                => "Échec de l'envoi via l'opérateur réseau après plusieurs tentatives. Vérifiez la configuration MTN (identifiants, service code) ou contactez le support.",
            $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException
                => "Aucun téléphone disponible pour envoyer ce SMS après plusieurs tentatives. Vérifiez qu'un appareil est appairé, en ligne, et que ses SIM ont du quota journalier restant.",
            default => 'Échec du dispatch : ' . $exception->getMessage(),
        };

        $this->sms->updateStatus('failed', $reason);

        // On ne facture pas au client un SMS qui n'a jamais pu être envoyé
        // (ni via un téléphone, ni via MTN) : on recrédite son quota mensuel.
        if ($subscription && $subscription->sms_used > 0) {
            $subscription->decrement('sms_used');
        }
    }
}
