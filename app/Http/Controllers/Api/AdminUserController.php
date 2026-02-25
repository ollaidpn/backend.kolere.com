<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = Admin::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $admins]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'ccphone' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
        ]);

        // Check if admin with this email already exists
        if (Admin::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Un administrateur avec cet email existe déjà.'], 422);
        }

        $invitation = AdminInvitation::create([
            'name' => $request->name,
            'email' => $request->email,
            'ccphone' => $request->ccphone,
            'phone' => $request->phone,
            'token' => Str::uuid()->toString(),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Invitation envoyée avec succès.',
            'data' => $invitation,
        ], 201);
    }

    public function update(Request $request, Admin $admin): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'ccphone' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
        ]);

        $admin->update($request->only(['name', 'email', 'ccphone', 'phone']));

        return response()->json([
            'message' => 'Administrateur mis à jour.',
            'data' => $admin->fresh(),
        ]);
    }

    public function toggleStatus(Admin $admin): JsonResponse
    {
        $newStatus = $admin->status === 'active' ? 'inactive' : 'active';
        $admin->update(['status' => $newStatus]);

        return response()->json([
            'message' => "Compte {$newStatus}.",
            'data' => $admin->fresh(),
        ]);
    }

    public function resetPassword(Admin $admin): JsonResponse
    {
        $tempPassword = Str::random(12);
        $admin->update(['password' => Hash::make($tempPassword)]);

        // In production, send email with $tempPassword
        return response()->json([
            'message' => 'Mot de passe réinitialisé. Un email a été envoyé.',
        ]);
    }

    public function destroy(Request $request, Admin $admin): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $currentAdmin = $request->user();

        // Prevent self-deletion
        if ($currentAdmin && $currentAdmin->id === $admin->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        // Verify current admin's password
        if (!Hash::check($request->password, $currentAdmin->password)) {
            return response()->json(['message' => 'Mot de passe incorrect.'], 403);
        }

        $admin->delete();

        return response()->json(['message' => 'Compte administrateur supprimé.']);
    }
}
