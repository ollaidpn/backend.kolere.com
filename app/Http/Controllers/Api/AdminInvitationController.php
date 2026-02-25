<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminInvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = AdminInvitation::where('token', $token)->firstOrFail();
        return response()->json(['data' => $invitation]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = AdminInvitation::where('token', $token)->firstOrFail();

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'Cette invitation a déjà été traitée.'], 422);
        }

        // Check if admin already exists
        if (Admin::where('email', $invitation->email)->exists()) {
            return response()->json(['message' => 'Un compte administrateur avec cet email existe déjà.'], 422);
        }

        $request->validate([
            'password' => 'required|string|min:6',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'ccphone' => 'nullable|string|max:10',
        ]);

        Admin::create([
            'name' => $request->name ?: $invitation->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'reference' => 'ADM-' . strtoupper(Str::random(8)),
            'ccphone' => $request->ccphone ?: $invitation->ccphone,
            'phone' => $request->phone ?: $invitation->phone,
        ]);

        $invitation->update(['status' => 'accepted']);

        return response()->json(['message' => 'Compte administrateur créé avec succès.']);
    }

    public function refuse(string $token): JsonResponse
    {
        $invitation = AdminInvitation::where('token', $token)->firstOrFail();

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'Cette invitation a déjà été traitée.'], 422);
        }

        $invitation->update(['status' => 'refused']);

        return response()->json(['message' => 'Invitation refusée.']);
    }
}
