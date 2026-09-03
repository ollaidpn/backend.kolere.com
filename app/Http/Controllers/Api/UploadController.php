<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|max:5120',
                'folder' => 'nullable|string|max:120',
            ]);

            $fileService = new \App\Services\FileUploadService();
            $uploaded = $fileService->upload(
                $validated['file'],
                $validated['folder'] ?? 'website/sliders'
            );

            return response()->json([
                'message' => 'Fichier uploadé',
                'data' => $uploaded,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[UploadController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'upload'], 500);
        }
    }
}
