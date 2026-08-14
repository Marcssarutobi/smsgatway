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

        $deviceSim = $this->pickAvailableSim();

        if (!$deviceSim) {
            // Aucun device en ligne avec du quota restant : on retente plus tard,
            // avec un délai qui augmente à chaque tentative (voir $backoff).
            $delay = $this->backoff[$this->attempts() - 1] ?? end($this->backoff);
            $this->release($delay);
            return;
        }

        $this->sms->update([
            'device_sim_id' => $deviceSim->id,
            'status' => 'queued',
        ]);

        $this->sms->statusLogs()->create([
            'status' => 'queued',
            'details' => "Assigné à la SIM #{$deviceSim->id} (device #{$deviceSim->device_id})",
        ]);

        // Réveille l'app mobile concernée via FCM
        app(\App\Services\FcmService::class)->sendWakeUp($deviceSim->device);
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

        $reason = $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException
            ? "Aucun téléphone disponible pour envoyer ce SMS après plusieurs tentatives. Vérifiez qu'un appareil est appairé, en ligne, et que ses SIM ont du quota journalier restant."
            : 'Échec du dispatch : ' . $exception->getMessage();

        $this->sms->updateStatus('failed', $reason);

        // On ne facture pas au client un SMS qui n'a jamais pu être pris en
        // charge par un téléphone : on recrédite son quota mensuel.
        $subscription = $this->sms->user->activeSubscription;
        if ($subscription && $subscription->sms_used > 0) {
            $subscription->decrement('sms_used');
        }
    }
}
