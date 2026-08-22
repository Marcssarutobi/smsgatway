<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Réservé au staff de la plateforme (role 'Admin', par opposition à 'Client').
// Contrairement à FacturePro, il n'existe pas ici de notion d'équipe/membres
// au sein d'une organisation : ce rôle ne concerne QUE l'accès à la vue
// globale de la plateforme (tous les utilisateurs, tous les devices, etc.),
// jamais des permissions supplémentaires dans l'organisation d'un tiers.
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'Admin') {
            return response()->json(['message' => 'Accès réservé aux administrateurs.'], 403);
        }

        return $next($request);
    }
}
