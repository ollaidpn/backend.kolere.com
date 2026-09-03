<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopBrand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ShopBrandController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopBrand::query();

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            }

            $brands = $query->orderBy('name')->get();
            $brands = $brands->map(function ($brand) {
                $brand->image_url = $brand->image;
                return $brand;
            });

            return response()->json(['data' => $brands]);
        } catch (\Exception $e) {
            Log::error('[ShopBrandController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des marques'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $entityId = $this->currentEntityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
            }

            $validated = $request->validate([
                'reference' => 'nullable|string|max:255|unique:shop_brands,reference',
                'name' => 'required|string|max:255',
                'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            ]);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $fileService = new \App\Services\FileUploadService();
                $uploaded = $fileService->upload($request->file('image'), 'shop-brands');
                $imagePath = $uploaded['url'];
            }

            $brand = ShopBrand::create([
                'entity_id' => $entityId,
                'reference' => $validated['reference'] ?? null,
                'name' => $validated['name'],
                'image' => $imagePath,
            ]);

            $brand->image_url = $brand->image;

            return response()->json(['message' => 'Marque créée', 'data' => $brand], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopBrandController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de la marque'], 500);
        }
    }

    public function update(Request $request, ShopBrand $brand): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $brand->entity_id === (int) $entityId, 404);
            }

            $validated = $request->validate([
                'reference' => 'nullable|string|max:255|unique:shop_brands,reference,' . $brand->id,
                'name' => 'required|string|max:255',
                'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            ]);

            $data = [
                'reference' => $validated['reference'] ?? $brand->reference,
                'name' => $validated['name'],
            ];

            if ($request->hasFile('image')) {
                $fileService = new \App\Services\FileUploadService();
                // Extrait le chemin S3 relatif de l'URL absolue existante pour pouvoir la supprimer
                if ($brand->image && !str_starts_with($brand->image, 'http')) {
                    $fileService->delete($brand->image);
                } elseif ($brand->image && str_contains($brand->image, 'esargal.com')) {
                    $path = explode('esargal.com/', $brand->image)[1] ?? null;
                    if ($path) $fileService->delete($path);
                }
                $uploaded = $fileService->upload($request->file('image'), 'shop-brands');
                $data['image'] = $uploaded['url'];
            }

            $brand->update($data);

            $brand->image_url = $brand->image;

            return response()->json(['message' => 'Marque mise à jour', 'data' => $brand]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopBrandController@update] Error', ['id' => $brand->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de la marque'], 500);
        }
    }

    public function destroy(Request $request, ShopBrand $brand): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $brand->entity_id === (int) $entityId, 404);
            }

            if ($brand->image && !str_starts_with($brand->image, 'http')) {
                $fileService = new \App\Services\FileUploadService();
                $fileService->delete($brand->image);
            }
            $brand->delete();

            return response()->json(['message' => 'Marque supprimée']);
        } catch (\Exception $e) {
            Log::error('[ShopBrandController@destroy] Error', ['id' => $brand->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression de la marque'], 500);
        }
    }
}
