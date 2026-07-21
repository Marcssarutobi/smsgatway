<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;

class PlanController extends Controller
{
    // Public, pas besoin d'auth — utilisé sur la page tarifs du site
    public function index()
    {
        return response()->json(Plan::where('active', true)->get());
    }
}
