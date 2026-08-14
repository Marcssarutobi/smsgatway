<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchSmsJob;
use App\Models\DeviceSim;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Http\Request;

class SmsMessageController extends Controller
{
    // Nombre maximum de destinataires acceptés en un seul envoi groupé.
    // Vise à éviter qu'une seule requête ne monopolise la file d'attente
    // ou ne consomme d'un coup tout le quota mensuel d'un utilisateur.
    private const MAX_BULK_RECIPIENTS = 100;

    public function store(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:918', // ~6 segments SMS max, ajustable
        ]);

        $user = $request->authenticated_user;
        $apiKey = $request->authenticated_api_key;
        $subscription = $user->activeSubscription;

        if (!$subscription || !$subscription->hasQuotaLeft()) {
            return response()->json(['message' => 'Quota d\'envoi mensuel dépassé'], 402);
        }

        if (!$this->hasAvailableDevice($user)) {
            return response()->json([
                'message' => 'Aucun téléphone disponible pour envoyer ce SMS. Vérifiez qu\'un appareil est appairé, en ligne, et que ses SIM ont du quota journalier restant.',
            ], 503);
        }

        $sms = SmsMessage::create([
            'user_id' => $user->id,
            'api_key_id' => $apiKey->id,
            'recipient' => $request->to,
            'content' => $this->buildContent($user, $apiKey, $request->message),
            'status' => 'pending',
        ]);

        $sms->statusLogs()->create(['status' => 'pending']);
        $subscription->increment('sms_used');

        dispatch(new DispatchSmsJob($sms));

        return response()->json(['id' => $sms->id, 'status' => $sms->status], 201);
    }

    // Envoi du même message à plusieurs destinataires en une seule requête.
    // Tout ou rien : si le quota ou la disponibilité d'un device ne permet pas
    // de couvrir l'intégralité du lot, rien n'est créé (évite les envois
    // partiels difficiles à rattraper côté client).
    public function storeBulk(Request $request)
    {
        $request->validate([
            'to' => 'required|array|min:1|max:' . self::MAX_BULK_RECIPIENTS,
            'to.*' => 'required|string',
            'message' => 'required|string|max:918',
        ]);

        $user = $request->authenticated_user;
        $apiKey = $request->authenticated_api_key;
        $subscription = $user->activeSubscription;
        $recipients = array_values(array_unique($request->to));
        $count = count($recipients);

        if (!$subscription || !$subscription->hasQuotaLeftFor($count)) {
            $remaining = $subscription
                ? max(0, $subscription->plan->sms_quota_monthly - $subscription->sms_used)
                : 0;

            return response()->json([
                'message' => "Quota insuffisant pour envoyer à {$count} destinataires (il vous reste {$remaining} SMS ce mois-ci).",
            ], 402);
        }

        if (!$this->hasAvailableDevice($user)) {
            return response()->json([
                'message' => 'Aucun téléphone disponible pour envoyer ce SMS. Vérifiez qu\'un appareil est appairé, en ligne, et que ses SIM ont du quota journalier restant.',
            ], 503);
        }

        $content = $this->buildContent($user, $apiKey, $request->message);

        $created = collect($recipients)->map(function (string $to) use ($user, $apiKey, $content) {
            $sms = SmsMessage::create([
                'user_id' => $user->id,
                'api_key_id' => $apiKey->id,
                'recipient' => $to,
                'content' => $content,
                'status' => 'pending',
            ]);

            $sms->statusLogs()->create(['status' => 'pending']);
            dispatch(new DispatchSmsJob($sms));

            return ['id' => $sms->id, 'to' => $to, 'status' => $sms->status];
        });

        $subscription->increment('sms_used', $count);

        return response()->json([
            'message' => "{$count} SMS mis en file d'attente.",
            'messages' => $created,
        ], 201);
    }

    public function show(Request $request, SmsMessage $sms)
    {
        if ($sms->user_id !== $request->authenticated_user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json($sms->load('statusLogs'));
    }

    public function index(Request $request)
    {
        $query = $request->authenticated_user->smsMessages()->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    public function indexForUser(Request $request)
    {
        $query = $request->user()->smsMessages()->with('deviceSim.device')->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    public function showForUser(Request $request, SmsMessage $sms)
    {
        if ($sms->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json($sms->load('statusLogs'));
    }

    private function hasAvailableDevice(User $user): bool
    {
        // Vérifié avant mise en file d'attente plutôt que de laisser
        // DispatchSmsJob épuiser ses tentatives en silence : ça donne un
        // retour immédiat et clair au lieu d'un échec après plusieurs minutes.
        return DeviceSim::query()
            ->whereHas('device', function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('status', 'online');
            })
            ->where('is_active', true)
            ->whereColumn('sent_today', '<', 'daily_quota')
            ->exists();
    }

    // Ajoute une signature en bas du message :
    // - clé de TEST : signature fixe imposée, pour qu'un message envoyé par erreur
    //   (ou dans un test) soit toujours identifiable comme non-officiel.
    // - clé de PRODUCTION : signature de l'organisation si elle en a configuré une,
    //   sinon rien n'est ajouté.
    private function buildContent(User $user, \App\Models\ApiKey $apiKey, string $message): string
    {
        if ($apiKey->environment === 'test') {
            return $message . "\n\nMessage de test SMS-gatewap";
        }

        if ($signature = $user->organisation?->signature) {
            return $message . "\n" . $signature;
        }

        return $message;
    }
}
