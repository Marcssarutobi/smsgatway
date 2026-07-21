<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->organisation);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'signature' => 'nullable|string|max:100',
            'website' => 'nullable|url',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
        ]);

        $organisation = $request->user()->organisation()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->only('name', 'signature', 'website', 'phone', 'address')
        );

        return response()->json($organisation);
    }
}
