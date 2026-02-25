<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Admin;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion Client (table users)
     */
    public function loginClient(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->makeHidden(['password']),
            'role' => 'client',
        ]);
    }

    /**
     * Connexion Manager (table managers)
     */
    public function loginManager(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $manager = Manager::where('email', $request->email)->first();

        if (!$manager || !Hash::check($request->password, $manager->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $token = $manager->createToken('manager-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $manager->makeHidden(['password']),
            'role' => 'manager',
        ]);
    }

    /**
     * Connexion Admin (table admins)
     */
    public function loginAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $admin->makeHidden(['password']),
            'role' => 'admin',
        ]);
    }
}
