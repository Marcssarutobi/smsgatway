<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // Étape 1 : l'utilisateur saisit son email -> on envoie le lien de réinitialisation
    // (utilise le broker de mot de passe natif de Laravel, table password_reset_tokens
    // déjà présente dans les migrations par défaut, rien à créer côté BDD).
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        // Réponse volontairement identique que l'email existe ou non, pour ne pas
        // révéler si une adresse est enregistrée (énumération de comptes).
        return response()->json([
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.',
        ], in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER]) ? 200 : 422);
    }

    // Étape 2 : l'utilisateur arrive depuis le lien reçu par email (token + email en
    // query params côté frontend) et soumet son nouveau mot de passe.
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Sécurité : on révoque toutes les sessions/API tokens actifs, au cas où
                // le mot de passe a été oublié suite à une compromission du compte.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => $status === Password::INVALID_TOKEN
                    ? 'Ce lien de réinitialisation est invalide ou a expiré.'
                    : 'Impossible de réinitialiser le mot de passe.',
            ], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
