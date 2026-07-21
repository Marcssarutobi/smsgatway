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

    public int $tries = 3;
    public int $backoff = 10; // secondes avant retry si aucun device dispo

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
            // Aucun device en ligne avec du quota restant : on retente plus tard
            $this->release($this->backoff);
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
        $this->sms->updateStatus('failed', 'Échec du dispatch : ' . $exception->getMessage());
    }
}
