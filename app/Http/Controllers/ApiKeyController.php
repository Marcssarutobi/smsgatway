<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->apiKeys()->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $keys = collect(['test', 'live'])->map(function ($environment) use ($request) {

            return $request->user()->apiKeys()->create([
                'name' => ($request->name ?? 'API Key') . ' (' . ucfirst($environment) . ')',
                'environment' => $environment,
                'key' => $environment === 'test'
                    ? 'sk_test_' . Str::random(32)
                    : 'sk_live_' . Str::random(32),

                'secret' => $environment === 'test'
                    ? 'ss_test_' . Str::random(48)
                    : 'ss_live_' . Str::random(48),

                'status' => 'active',
            ]);
        });

        return response()->json([
            'message' => 'Clés API générées avec succès.',
            'keys' => $keys,
        ], 200);
    }

    public function revoke(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $apiKey->update(['status' => 'revoked']);

        return response()->json(['message' => 'Clé révoquée']);
    }

    public function destroy(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $apiKey->delete();

        return response()->json(['message' => 'Clé supprimée']);
    }
}
