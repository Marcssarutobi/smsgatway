<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{

    public function index(Request $request)
    {
        return response()->json($request->user()->webhooks);
    }

    public function store(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'event' => 'required|in:sms.sent,sms.delivered,sms.failed',
        ]);

        $webhook = $request->user()->webhooks()->create([
            'url' => $request->url,
            'event' => $request->event,
            'secret' => Str::random(40),
        ]);

        return response()->json($webhook, 201);
    }

    public function toggle(Request $request, Webhook $webhook)
    {
        if ($webhook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $webhook->update(['active' => !$webhook->active]);

        return response()->json($webhook);
    }

    public function destroy(Request $request, Webhook $webhook)
    {
        if ($webhook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $webhook->delete();

        return response()->json(['message' => 'Webhook supprimé']);
    }

}
