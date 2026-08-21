<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    // Lien cliqué depuis l'email (signé, protégé par le middleware `signed`).
    // On redirige ensuite vers une page du frontend plutôt que de renvoyer du JSON,
    // puisque ce lien est ouvert directement dans le navigateur, pas appelé par l'app.
    public function verify(Request $request, string $id, string $hash)
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/') . '/email-verified';
        $user = User::find($id);

        if (!$user || !hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect($frontendUrl . '?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($frontendUrl . '?status=already');
        }

        $user->markEmailAsVerified();

        return redirect($frontendUrl . '?status=success');
    }

    // Renvoyer l'email de vérification depuis les paramètres du compte
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email déjà vérifié']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Email de vérification renvoyé']);
    }
}
