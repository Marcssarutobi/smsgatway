<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorRecoveryAttempt;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    // Étape 1 : générer le secret + le QR code à scanner
    public function setup(Request $request)
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $request->user()->update(['two_factor_secret' => $secret]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret
        );

        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'secret' => $secret,      // affiché en fallback texte si le QR ne scanne pas
            'qr_code_svg' => $qrSvg,
        ]);
    }

    // Étape 2 : l'utilisateur entre le code à 6 chiffres généré par son app -> on confirme et active
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($request->user()->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Code invalide'], 422);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn () => \Illuminate\Support\Str::random(10))->toArray();

        $request->user()->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    // Étape 3 : utilisée à CHAQUE connexion une fois la 2FA activée
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user(); // authentifié via le temp_token (ability 2fa-pending)
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code)
            || in_array($request->code, $user->two_factor_recovery_codes ?? []);

        $request->user()->currentAccessToken()->delete(); // on invalide le temp_token dans tous les cas

        if (!$valid) {
            TwoFactorRecoveryAttempt::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'success' => false,
            ]);

            return response()->json(['message' => 'Code invalide'], 422);
        }

        TwoFactorRecoveryAttempt::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'success' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }
}
