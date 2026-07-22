<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $key = $request->bearerToken();

        if (!$key) {
            return response()->json(['message' => 'Clé API manquante'], 401);
        }

        $apiKey = ApiKey::where('key', $key)
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return response()->json(['message' => 'Clé API invalide ou révoquée'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        // Les deux lignes suivantes sont indispensables :
        // authenticated_user -> utilisé par SmsMessageController pour user_id, quota, organisation...
        // authenticated_api_key -> utilisé pour tracer quelle clé a servi à l'envoi (api_key_id)
        $request->merge(['authenticated_user' => $apiKey->user]);
        $request->merge(['authenticated_api_key' => $apiKey]);

        return $next($request);
    }
}
