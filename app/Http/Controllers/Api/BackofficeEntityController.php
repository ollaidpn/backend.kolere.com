<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
            $fileService = new FileUploadService();
            $data = $entity->loadMissing('domain')->toArray();
            $data['logo_url'] = $entity->logo
                ? (str_starts_with($entity->logo, 'http') ? $entity->logo : $fileService->getUrl($entity->logo))
                : null;
            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            Log::error('[BackofficeEntityController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    private function normalizeSliderLink(mixed $link): ?array
    {
        if ($link === null || $link === '') {
            return null;
        }

        if (is_array($link)) {
            $type = strtolower(trim((string) ($link['type'] ?? '')));
            $allowedTypes = ['item', 'brand', 'category', 'url'];
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'url';
            }

            $rawValue = $link['value'] ?? $link['id'] ?? null;
            $label = trim((string) ($link['label'] ?? ''));

            return [
                'type' => $type,
                'value' => is_numeric($rawValue) ? (int) $rawValue : trim((string) $rawValue),
                'label' => $label,
            ];
        }

        if (!is_string($link)) {
            return null;
        }

        $trimmed = trim($link);
        if ($trimmed === '') {
            return null;
        }

        return [
            'type' => 'url',
            'value' => $trimmed,
            'label' => $trimmed,
        ];
    }

    private function normalizeWebSlider(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return array_values(array_map(function ($item) {
                $link = $this->normalizeSliderLink($item['link'] ?? null);

                return [
                    'title' => trim((string) ($item['title'] ?? '')),
                    'subtitle' => trim((string) ($item['subtitle'] ?? '')),
                    'btn' => trim((string) ($item['btn'] ?? '')),
                    'link' => $link,
                    'image' => trim((string) ($item['image'] ?? '')),
                ];
            }, $value));
        }

        if (!is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $this->normalizeWebSlider($decoded);
    }

    private function normalizeDeliveryZones(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($decoded)) {
            return null;
        }

        $hasDefault = false;
        foreach ($decoded as $zone) {
            if (($zone['id'] ?? '') === 'zone-default' || ($zone['is_default'] ?? false) === true) {
                $hasDefault = true;
                break;
            }
        }

        $normalized = [];
        if (!$hasDefault) {
            $normalized[] = [
                'id' => 'zone-default',
                'name' => 'Autres (Par défaut)',
                'price' => 2500.0,
                'is_default' => true,
                'sub_zones' => [],
            ];
        }

        foreach ($decoded as $zone) {
            $isDefault = ($zone['id'] ?? '') === 'zone-default' || ($zone['is_default'] ?? false) === true;
            
            $subZones = [];
            if (isset($zone['sub_zones']) && is_array($zone['sub_zones'])) {
                foreach ($zone['sub_zones'] as $sz) {
                    if (empty($sz['name'])) continue;
                    $subZones[] = [
                        'id' => trim((string)($sz['id'] ?? 'sub-' . uniqid())),
                        'name' => trim((string)($sz['name'] ?? '')),
                        'price' => isset($sz['price']) ? (float)$sz['price'] : 0.0,
                    ];
                }
            }

            if ($isDefault) {
                $normalized[] = [
                    'id' => 'zone-default',
                    'name' => 'Autres (Par défaut)',
                    'price' => isset($zone['price']) ? (float)$zone['price'] : 2500.0,
                    'is_default' => true,
                    'sub_zones' => [],
                ];
            } else {
                if (empty($zone['name'])) continue;
                $normalized[] = [
                    'id' => trim((string)($zone['id'] ?? 'zone-' . uniqid())),
                    'name' => trim((string)($zone['name'] ?? '')),
                    'price' => isset($zone['price']) && $zone['price'] !== '' && $zone['price'] !== null ? (float)$zone['price'] : null,
                    'is_default' => false,
                    'sub_zones' => $subZones,
                ];
            }
        }

        return $normalized;
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
                'primary_color' => 'nullable|string|max:30',
                'secondary_color' => 'nullable|string|max:30',
                'logo'    => 'nullable|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
                'address' => 'nullable|string|max:255',
                'town'    => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'email'   => 'nullable|email|max:255',
                'phone'   => 'nullable|string|max:20',
                'ccphone' => 'nullable|string|max:10',
                'web_slider' => 'nullable',
                'delivery_zones' => 'nullable',
                'fayko_public_key' => 'nullable|string|max:255',
                'fayko_secret_key' => 'nullable|string|max:255',
                'fayko_webhook_key' => 'nullable|string|max:255',
                'fayko_mode' => 'nullable|string|max:20',
                'diotko_public_key' => 'nullable|string|max:255',
                'diotko_secret_key' => 'nullable|string|max:255',
            ]);

            $data = $request->only([
                'name', 'primary_color', 'secondary_color', 'address', 'town', 'country', 'email', 'phone', 'ccphone',
                'fayko_public_key', 'fayko_secret_key', 'fayko_webhook_key', 'fayko_mode',
                'diotko_public_key', 'diotko_secret_key',
            ]);



            
            $webSlider = $this->normalizeWebSlider($request->input('web_slider'));
            if ($webSlider !== null) {
                $data['web_slider'] = $webSlider;
            }

            $deliveryZones = $this->normalizeDeliveryZones($request->input('delivery_zones'));
            if ($deliveryZones !== null) {
                $data['delivery_zones'] = $deliveryZones;
            }

            if ($request->hasFile('logo')) {
                $fileService = new \App\Services\FileUploadService();
                if ($entity->logo && !str_starts_with($entity->logo, 'http')) {
                    $fileService->delete($entity->logo);
                }
                try {
                    $uploaded = $fileService->upload($request->file('logo'), 'entity-logos');
                    $data['logo'] = $uploaded['path'];
                    $data['logo_url'] = $uploaded['url'];
                } catch (\Throwable $uploadError) {
                    Log::error('[BackofficeEntityController@update] Logo upload failed', [
                        'message' => $uploadError->getMessage(),
                    ]);
                    return response()->json([
                        'message' => 'Échec de l\'upload du logo sur le stockage distant.',
                    ], 500);
                }
            }

            $entity->update($data);

            $fileService = new \App\Services\FileUploadService();
            $result = $entity->loadMissing('domain')->toArray();
            return response()->json(['message' => 'Paramètres mis à jour', 'data' => $result]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[BackofficeEntityController@update] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    public function requestDomainActivation(Request $request): JsonResponse
    {

        try {
            $entity = $this->getEntity($request);
            if (!$entity) {
                return response()->json(['message' => 'Entité non trouvée'], 404);
            }

            $request->validate([
                'domain' => 'required|string|max:255',
                'notes'  => 'nullable|string|max:1000',
            ]);

            $requestedDomain = trim($request->input('domain'));
            $notes = $request->input('notes');

            // Envoi des emails à dev@ollaid.com et en copie à ollaidpn@gmail.com
            try {
                \Illuminate\Support\Facades\Mail::to('dev@ollaid.com')
                    ->cc('ollaidpn@gmail.com')
                    ->send(new \App\Mail\DomainActivationRequestMail($entity, $requestedDomain, $notes));
            } catch (\Throwable $mailError) {
                Log::error('[BackofficeEntityController@requestDomainActivation] Email failed', ['message' => $mailError->getMessage()]);
            }

            return response()->json([
                'message' => 'Demande d\'activation envoyée avec succès',
                'domain'  => $requestedDomain,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[BackofficeEntityController@requestDomainActivation] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'envoi de la demande'], 500);
        }
    }
}
