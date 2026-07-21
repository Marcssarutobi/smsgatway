<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SmsMessage;
use App\Jobs\DispatchSmsJob;

class SmsMessageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:918', // ~6 segments SMS max, ajustable
        ]);

        $user = $request->authenticated_user;
        $subscription = $user->activeSubscription;

        if (!$subscription || !$subscription->hasQuotaLeft()) {
            return response()->json(['message' => 'Quota d\'envoi mensuel dépassé'], 402);
        }

        $content = $request->message;
        if ($signature = $user->organisation?->signature) {
            $content .= "\n" . $signature;
        }

        $sms = SmsMessage::create([
            'user_id' => $user->id,
            'api_key_id' => $request->authenticated_api_key->id,
            'recipient' => $request->to,
            'content' => $content,
            'status' => 'pending',
        ]);

        $sms->statusLogs()->create(['status' => 'pending']);
        $subscription->increment('sms_used');

        dispatch(new DispatchSmsJob($sms));

        return response()->json(['id' => $sms->id, 'status' => $sms->status], 201);
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

        return response()->json($query->paginate(20));
    }
}
