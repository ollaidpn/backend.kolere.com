<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Link;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * Create a new invitation (from admin space).
     */
    public function store(Request $request): JsonResponse
    {
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

        // TODO: Send invitation email

        return response()->json([
            'message' => 'Invitation envoyée.',
            'data'    => $invitation->load('entity'),
        ], 201);
    }

    /**
     * Show invitation details by token (public).
     */
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->with('entity.domain')
            ->firstOrFail();

        return response()->json(['data' => $invitation]);
    }

    /**
     * Accept an invitation (public).
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        // Check if manager already exists
        $manager = Manager::where('email', $invitation->email)->first();

        if (!$manager) {
            // New manager — require password
            $request->validate([
                'password' => 'required|string|min:6',
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
        }

        // Create the link between manager and entity
        Link::create([
            'manager_id' => $manager->id,
            'entity_id'  => $invitation->entity_id,
            'is_admin'   => $invitation->is_admin,
        ]);

        // Mark invitation as accepted
        $invitation->update(['status' => 'accepted']);

        return response()->json([
            'message'         => 'Invitation acceptée.',
            'manager_existed' => $manager->wasRecentlyCreated === false,
        ]);
    }

    /**
     * Refuse an invitation (public).
     */
    public function refuse(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        $invitation->update(['status' => 'refused']);

        return response()->json(['message' => 'Invitation refusée.']);
    }
}
