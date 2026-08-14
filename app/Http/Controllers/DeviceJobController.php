<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SmsMessage;

class DeviceJobController extends Controller
{
    public function pending(Request $request)
    {
        $device = $request->authenticated_device;

        $simIds = $device->activeSims()->pluck('id');

        $jobs = SmsMessage::whereIn('device_sim_id', $simIds)
            ->where('status', 'queued')
            ->orderBy('priority', 'desc')
            ->limit(20)
            ->get(['id', 'device_sim_id', 'recipient', 'content']);

        return response()->json(['jobs' => $jobs]);
    }

    public function report(Request $request, SmsMessage $sms)
    {
        $request->validate([
            'status' => 'required|in:sent,delivered,failed',
            'error_message' => 'nullable|string',
            // Renseigné par l'app si elle a dû basculer sur une autre SIM du même
            // téléphone après plusieurs échecs sur la SIM initialement assignée.
            'device_sim_id' => 'nullable|integer|exists:device_sims,id',
        ]);

        $device = $request->authenticated_device;

        if (!$device->sims()->where('id', $sms->device_sim_id)->exists()) {
            return response()->json(['message' => 'Ce SMS n\'appartient pas à ce device'], 403);
        }

        // Si l'app a basculé sur une autre SIM du même device pour réussir l'envoi,
        // on réassigne le SMS à cette SIM avant de créditer son quota journalier,
        // sinon le compteur sent_today serait incrémenté sur la mauvaise SIM.
        if ($request->device_sim_id && (int) $request->device_sim_id !== $sms->device_sim_id) {
            if (!$device->sims()->where('id', $request->device_sim_id)->exists()) {
                return response()->json(['message' => 'Cette SIM n\'appartient pas à ce device'], 403);
            }

            $sms->update(['device_sim_id' => $request->device_sim_id]);
            $sms->statusLogs()->create([
                'status' => $sms->status,
                'details' => "Basculé sur la SIM #{$request->device_sim_id} après échec sur la SIM initiale",
            ]);
            $sms->refresh();
        }

        $sms->updateStatus($request->status, $request->error_message);

        $updates = ['error_message' => $request->error_message];
        if ($request->status === 'sent') $updates['sent_at'] = now();
        if ($request->status === 'delivered') $updates['delivered_at'] = now();
        $sms->update($updates);

        if ($request->status === 'sent') {
            $sms->deviceSim->increment('sent_today');
        }

        // Déclenche le webhook du client (job asynchrone, voir plus bas)
        dispatch(new \App\Jobs\TriggerWebhookJob($sms));

        return response()->json(['message' => 'Statut mis à jour']);
    }

    public function heartbeat(Request $request)
    {
        $request->validate([
            'battery_level' => 'nullable|integer|min:0|max:100',
            'sims' => 'nullable|array',
        ]);

        $device = $request->authenticated_device;
        $device->update([
            'status' => 'online',
            'battery_level' => $request->battery_level,
            'last_seen_at' => now(),
        ]);

        // Met à jour signal/statut de chaque SIM si l'app en envoie
        foreach ($request->sims ?? [] as $simData) {
            $device->sims()->where('slot_index', $simData['slot_index'])
                ->update(['signal_strength' => $simData['signal_strength'] ?? null]);
        }

        return response()->json(['message' => 'ok']);
    }
}
