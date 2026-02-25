<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Admin;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginClient(Request $request): JsonResponse
    {
        Log::info('[AuthController@loginClient] Attempt', ['email' => $request->email]);
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('[AuthController@loginClient] Invalid credentials', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $token = $user->createToken('client-token')->plainTextToken;
            Log::info('[AuthController@loginClient] Success', ['user_id' => $user->id]);

            return response()->json([
                'token' => $token,
                'user' => $user->makeHidden(['password']),
                'role' => 'client',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@loginClient] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function loginManager(Request $request): JsonResponse
    {
        Log::info('[AuthController@loginManager] Attempt', ['email' => $request->email]);
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $manager = Manager::where('email', $request->email)->first();

            if (!$manager || !Hash::check($request->password, $manager->password)) {
                Log::warning('[AuthController@loginManager] Invalid credentials', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $token = $manager->createToken('manager-token')->plainTextToken;
            Log::info('[AuthController@loginManager] Success', ['manager_id' => $manager->id]);

            return response()->json([
                'token' => $token,
                'user' => $manager->makeHidden(['password']),
                'role' => 'manager',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@loginManager] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function loginAdmin(Request $request): JsonResponse
    {
        Log::info('[AuthController@loginAdmin] Attempt', ['email' => $request->email]);
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                Log::warning('[AuthController@loginAdmin] Invalid credentials', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $token = $admin->createToken('admin-token')->plainTextToken;
            Log::info('[AuthController@loginAdmin] Success', ['admin_id' => $admin->id]);

            return response()->json([
                'token' => $token,
                'user' => $admin->makeHidden(['password']),
                'role' => 'admin',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@loginAdmin] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
