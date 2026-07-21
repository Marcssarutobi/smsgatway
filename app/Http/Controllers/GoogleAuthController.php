<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OauthAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

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
    }
}
