<?php

namespace App\Http\Controllers;

use App\Models\SmsPricingSetting;
use Illuminate\Http\Request;

class SmsPricingController extends Controller
{
    // Public : le front (modal Device/Opérateur, calcul de prix en direct)
    // a besoin de connaître le tarif en vigueur avant même que l'utilisateur
    // soit connecté (page de tarifs publique).
    public function show()
    {
        return response()->json(SmsPricingSetting::current());
    }

    // Staff uniquement (voir routes/api.php, middleware 'admin').
    public function update(Request $request)
    {
        $validated = $request->validate([
            'price_per_sms' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'network_enabled' => 'sometimes|boolean',
        ]);

        $setting = SmsPricingSetting::current();
        $setting->update($validated);

        return response()->json($setting);
    }
}
