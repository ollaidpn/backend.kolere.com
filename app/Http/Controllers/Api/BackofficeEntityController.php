<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackofficeEntityController extends Controller
{
    private function getEntity(Request $request)
    {
        $entity = $request->attributes->get('current_entity');
        if ($entity) {
            return $entity;
        }

        $manager = $request->user();
        $link = $manager->currentLink()->with('entity')->first();
        return $link ? $link->entity : null;
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $entity = $this->getEntity($request);
            if (!$entity) {
                return response()->json(['message' => 'Entité non trouvée'], 404);
            }
            $data = $entity->toArray();
            if ($entity->logo && !str_starts_with($entity->logo, 'http')) {
                $data['logo_url'] = url(Storage::url($entity->logo));
            } else {
                $data['logo_url'] = $entity->logo;
            }
            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            Log::error('[BackofficeEntityController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $entity = $this->getEntity($request);
            if (!$entity) {
                return response()->json(['message' => 'Entité non trouvée'], 404);
            }

            $request->validate([
                'name'    => 'sometimes|string|max:255',
                'logo'    => 'nullable|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
                'address' => 'nullable|string|max:255',
                'town'    => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'email'   => 'nullable|email|max:255',
                'phone'   => 'nullable|string|max:20',
                'ccphone' => 'nullable|string|max:10',
            ]);

            $data = $request->only(['name', 'address', 'town', 'country', 'email', 'phone', 'ccphone']);

            if ($request->hasFile('logo')) {
                if ($entity->logo && !str_starts_with($entity->logo, 'http')) {
                    Storage::disk('public')->delete($entity->logo);
                }
                $data['logo'] = $request->file('logo')->store('entity-logos', 'public');
            }

            $entity->update($data);

            $result = $entity->toArray();
            $result['logo_url'] = $entity->logo
                ? (str_starts_with($entity->logo, 'http') ? $entity->logo : url(Storage::url($entity->logo)))
                : null;

            return response()->json(['message' => 'Paramètres mis à jour', 'data' => $result]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[BackofficeEntityController@update] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }
}
