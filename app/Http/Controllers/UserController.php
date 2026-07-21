<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Liste des utilisateurs — réservé à l'admin (protège via middleware sur la route, cf. plus bas)
    public function index(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $users = User::orderBy('id', 'desc')->paginate(20);

        return response()->json($users);
    }

    public function show(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        // Un client ne peut voir que son propre profil, l'admin peut tout voir
        if ($request->user()->role !== 'Admin' && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json(['user' => $user]);
    }

    // Mise à jour du profil de l'utilisateur connecté (pas un admin qui modifie un autre user ici)
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($validator->validated());

        return response()->json(['user' => $user]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'Client',
            'status' => 'en_attente', // ex: en attente de vérification email
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        // Si la 2FA est activée, on ne donne pas encore le token final
        if ($user->two_factor_confirmed_at) {
            $tempToken = $user->createToken('2fa_pending', ['2fa-pending'])->plainTextToken;

            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $tempToken,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('organisation', 'activeSubscription.plan'));
    }

    // Changement de mot de passe — distinct de updateProfile pour forcer la vérification de l'ancien mot de passe
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$user->password || !Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        // On invalide tous les autres tokens actifs par sécurité, sauf celui utilisé pour cette requête
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Mot de passe mis à jour']);
    }

    // Suppression d'un compte — réservé à l'admin, ou à l'utilisateur lui-même pour son propre compte
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        if ($request->user()->role !== 'Admin' && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $user->tokens()->delete(); // révoque tous ses accès avant suppression
        $user->delete();

        return response()->json(['message' => 'Compte supprimé']);
    }
}
