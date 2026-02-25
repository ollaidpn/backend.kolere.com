<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EntityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entities = Entity::with(['domain', 'links.manager'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($entities);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'domain_id'       => 'required|exists:domains,id',
            'name'            => 'required|string|max:255',
            'logo'            => 'nullable|string',
            'primary_color'   => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'town'            => 'nullable|string|max:255',
            'country'         => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'ccphone'         => 'nullable|string|max:10',
            'phone'           => 'nullable|string|max:20',
            // Manager invitation data
            'manager_name'    => 'required|string|max:255',
            'manager_email'   => 'required|email|max:255',
            'manager_ccphone' => 'nullable|string|max:10',
            'manager_phone'   => 'nullable|string|max:20',
        ]);

        $entity = Entity::create($request->only([
            'domain_id', 'name', 'logo', 'primary_color', 'secondary_color',
            'address', 'town', 'country', 'email', 'ccphone', 'phone',
        ]));

        // Create invitation for the manager
        $invitation = Invitation::create([
            'entity_id' => $entity->id,
            'email'     => $request->input('manager_email'),
            'name'      => $request->input('manager_name'),
            'ccphone'   => $request->input('manager_ccphone'),
            'phone'     => $request->input('manager_phone'),
            'token'     => Str::uuid()->toString(),
            'status'    => 'pending',
            'is_admin'  => true,
        ]);

        // TODO: Send invitation email to the manager

        return response()->json([
            'message'    => 'Boutique créée et invitation envoyée.',
            'data'       => $entity->load('domain'),
            'invitation' => $invitation,
        ], 201);
    }

    public function show(Entity $entity): JsonResponse
    {
        return response()->json([
            'data' => $entity->load(['domain', 'links.manager']),
        ]);
    }

    public function update(Request $request, Entity $entity): JsonResponse
    {
        $request->validate([
            'domain_id'       => 'sometimes|exists:domains,id',
            'name'            => 'sometimes|string|max:255',
            'logo'            => 'nullable|string',
            'primary_color'   => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'town'            => 'nullable|string|max:255',
            'country'         => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'ccphone'         => 'nullable|string|max:10',
            'phone'           => 'nullable|string|max:20',
        ]);

        $entity->update($request->only([
            'domain_id', 'name', 'logo', 'primary_color', 'secondary_color',
            'address', 'town', 'country', 'email', 'ccphone', 'phone',
        ]));

        return response()->json([
            'message' => 'Boutique mise à jour.',
            'data'    => $entity->load('domain'),
        ]);
    }

    public function destroy(Entity $entity): JsonResponse
    {
        $entity->delete();

        return response()->json(['message' => 'Boutique supprimée.']);
    }
}
