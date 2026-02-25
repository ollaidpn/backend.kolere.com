<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Link;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Log::info('[InvitationController@store] Creating invitation', ['email' => $request->email]);
        try {
            $request->validate([
                'entity_id' => 'required|exists:entities,id',
                'email'     => 'required|email|max:255',
                'name'      => 'required|string|max:255',
                'ccphone'   => 'nullable|string|max:10',
                'phone'     => 'nullable|string|max:20',
                'is_admin'  => 'boolean',
            ]);

            $invitation = Invitation::create([
                'entity_id' => $request->input('entity_id'),
                'email'     => $request->input('email'),
                'name'      => $request->input('name'),
                'ccphone'   => $request->input('ccphone'),
                'phone'     => $request->input('phone'),
                'token'     => Str::uuid()->toString(),
                'status'    => 'pending',
                'is_admin'  => $request->input('is_admin', false),
            ]);
            Log::info('[InvitationController@store] Invitation created', ['invitation_id' => $invitation->id]);

            return response()->json([
                'message' => 'Invitation envoyée.',
                'data'    => $invitation->load('entity'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[InvitationController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function show(string $token): JsonResponse
    {
        Log::info('[InvitationController@show] Fetching invitation', ['token' => $token]);
        try {
            $invitation = Invitation::where('token', $token)
                ->with('entity.domain')
                ->firstOrFail();

            Log::info('[InvitationController@show] Found', ['invitation_id' => $invitation->id]);
            return response()->json(['data' => $invitation]);
        } catch (\Exception $e) {
            Log::error('[InvitationController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        Log::info('[InvitationController@accept] Accepting invitation', ['token' => $token]);
        try {
            $invitation = Invitation::where('token', $token)
                ->where('status', 'pending')
                ->firstOrFail();

            $manager = Manager::where('email', $invitation->email)->first();

            if (!$manager) {
                $request->validate([
                    'password' => 'required|string|min:1',
                    'name'     => 'nullable|string|max:255',
                    'phone'    => 'nullable|string|max:20',
                    'ccphone'  => 'nullable|string|max:10',
                ]);

                $manager = Manager::create([
                    'name'      => $request->input('name', $invitation->name),
                    'email'     => $invitation->email,
                    'ccphone'   => $request->input('ccphone', $invitation->ccphone),
                    'phone'     => $request->input('phone', $invitation->phone),
                    'password'  => Hash::make($request->input('password')),
                    'reference' => 'MGR-' . strtoupper(Str::random(8)),
                    'status'    => 'active',
                ]);
                Log::info('[InvitationController@accept] Manager created', ['manager_id' => $manager->id]);
            } else {
                Log::info('[InvitationController@accept] Manager already exists', ['manager_id' => $manager->id]);
            }

            Link::create([
                'manager_id' => $manager->id,
                'entity_id'  => $invitation->entity_id,
                'is_admin'   => $invitation->is_admin,
            ]);
            Log::info('[InvitationController@accept] Link created');

            $invitation->update(['status' => 'accepted']);
            Log::info('[InvitationController@accept] Success');

            return response()->json([
                'message'         => 'Invitation acceptée.',
                'manager_existed' => $manager->wasRecentlyCreated === false,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[InvitationController@accept] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function refuse(string $token): JsonResponse
    {
        Log::info('[InvitationController@refuse] Refusing invitation', ['token' => $token]);
        try {
            $invitation = Invitation::where('token', $token)
                ->where('status', 'pending')
                ->firstOrFail();

            $invitation->update(['status' => 'refused']);
            Log::info('[InvitationController@refuse] Success');

            return response()->json(['message' => 'Invitation refusée.']);
        } catch (\Exception $e) {
            Log::error('[InvitationController@refuse] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
