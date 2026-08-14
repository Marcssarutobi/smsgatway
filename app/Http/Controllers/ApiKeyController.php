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

    // Un utilisateur n'a jamais qu'une seule paire de clés (test + live), générée
    // automatiquement à l'inscription (voir User::generatePairFor). Cette action
    // sert à la RÉGÉNÉRER (ex: fuite de clé) : les anciennes sont supprimées.
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $request->user()->apiKeys()->delete();

        $keys = ApiKey::generatePairFor($request->user(), $request->name);

        return response()->json([
            'message' => 'Clés API régénérées avec succès. Les anciennes clés ne fonctionnent plus.',
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
