<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OauthAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;

class GoogleAuthController extends Controller
{
    // Renvoie l'URL vers laquelle le frontend React doit rediriger l'utilisateur
    public function redirect()
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json(['url' => $url]);
    }

    // Appelé après que Google ait redirigé l'utilisateur vers ton callback
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $oauthAccount = OauthAccount::where('provider', 'google')
                ->where('provider_id', $googleUser->getId())
                ->first();

            if ($oauthAccount) {
                $user = $oauthAccount->user;
            } else {
                // L'email existe peut-être déjà (inscription classique) -> on lie le compte
                $user = User::where('email', $googleUser->getEmail())->first();

                if (!$user) {
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'avatar' => $googleUser->getAvatar(),
                        'password' => null,
                        'role' => 'Client',
                        'status' => 'actif',
                        'email_verified_at' => now(), // Google a déjà vérifié l'email
                    ]);
                    $user->startTrialSubscription();
                    \App\Models\ApiKey::generatePairFor($user);
                }

                OauthAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'access_token' => $googleUser->token,
                    'refresh_token' => $googleUser->refreshToken,
                ]);
            }

            if ($user->two_factor_confirmed_at) {
                $tempToken = $user->createToken('2fa_pending', ['2fa-pending'])->plainTextToken;

                return response()->json(['requires_2fa' => true, 'temp_token' => $tempToken]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json(['user' => $user, 'token' => $token]);

        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'error'   => 'google_auth_failed',
                'message' => $e->getMessage(), // à retirer en prod, utile en debug seulement
            ], 401);
        }
    }

    public function mobileLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        // Vérifie le token directement auprès de Google (pas de package composer nécessaire)
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);

        if (!$response->ok()) {
            return response()->json(['message' => 'Token Google invalide'], 401);
        }

        $payload = $response->json();

        if (!in_array($payload['iss'], [
                'accounts.google.com',
                'https://accounts.google.com'
            ], true)) {
            return response()->json([
                'message' => 'Issuer Google invalide'
            ], 401);
        }

        // Vérifie que le token est bien destiné à TON app (évite qu'un token
        // Google d'une autre application ne soit accepté ici)
        $validAudiences = [
            env('GOOGLE_WEB_CLIENT_ID'),
            env('GOOGLE_ANDROID_CLIENT_ID'),
            env('GOOGLE_IOS_CLIENT_ID'),
        ];

        if (!in_array($payload['aud'] ?? null, $validAudiences)) {
            return response()->json(['message' => 'Token non destiné à cette application'], 401);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            return response()->json(['message' => 'Email Google non vérifié'], 401);
        }

        if (($payload['exp'] ?? 0) < time()) {
            return response()->json([
                'message' => 'Token expiré'
            ], 401);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'] ?? $email;
        $avatar = $payload['picture'] ?? null;

        $oauthAccount = OauthAccount::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        if ($oauthAccount) {
            $user = $oauthAccount->user;
        } else {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'avatar' => $avatar,
                    'password' => null,
                    'role' => 'Client',
                    'status' => 'actif',
                    'email_verified_at' => now(),
                ]);

                $user->startTrialSubscription();
                \App\Models\ApiKey::generatePairFor($user);
            }

            OauthAccount::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_id' => $googleId,
            ]);
        }

        if ($user->two_factor_confirmed_at) {
            $tempToken = $user->createToken('2fa_pending', ['2fa-pending'])->plainTextToken;
            return response()->json(['requires_2fa' => true, 'temp_token' => $tempToken]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }
}
