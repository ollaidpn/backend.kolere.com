<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Invitation;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EntityController extends Controller
{
    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
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

    public function index(Request $request): JsonResponse
    {
        Log::info('[EntityController@index] Fetching entities list');
        try {
            $entities = Entity::with(['domain', 'links.manager'])
                ->latest()
                ->paginate($request->get('per_page', 15));

            Log::info('[EntityController@index] Success', ['count' => $entities->total()]);
            return response()->json($entities);
        } catch (\Exception $e) {
            Log::error('[EntityController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('[EntityController@store] Creating entity', ['name' => $request->name]);
        try {
            $request->validate([
                'domain_id'       => 'required|exists:domains,id',
                'name'            => 'required|string|max:255',
                'reference'       => 'nullable|string|max:255|unique:entities,reference',
                'subdomain'       => 'nullable|string|max:255|unique:entities,subdomain',
                'website_status'  => 'nullable|string|max:30',
                'logo'            => 'nullable|string',
                'primary_color'   => 'nullable|string|max:20',
                'secondary_color' => 'nullable|string|max:20',
                'web_slider'      => 'nullable',
                'address'         => 'nullable|string|max:255',
                'town'            => 'nullable|string|max:255',
                'country'         => 'nullable|string|max:255',
                'email'           => 'nullable|email|max:255',
                'ccphone'         => 'nullable|string|max:10',
                'phone'           => 'nullable|string|max:20',
                'manager_name'    => 'required|string|max:255',
                'manager_email'   => 'required|email|max:255',
                'manager_ccphone' => 'nullable|string|max:10',
                'manager_phone'   => 'nullable|string|max:20',
            ]);
            Log::info('[EntityController@store] Validation passed');

            $data = $request->only([
                'reference', 'subdomain', 'website_status', 'domain_id', 'name', 'logo', 'primary_color', 'secondary_color',
                'address', 'town', 'country', 'email', 'ccphone', 'phone',
            ]);
            $data['primary_color'] = $this->nullableString($data['primary_color'] ?? null);
            $data['secondary_color'] = $this->nullableString($data['secondary_color'] ?? null);
            $webSlider = $this->normalizeWebSlider($request->input('web_slider'));
            if ($webSlider !== null) {
                $data['web_slider'] = $webSlider;
            }

            $entity = Entity::create($data);
            Log::info('[EntityController@store] Entity created', ['entity_id' => $entity->id]);

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
            Log::info('[EntityController@store] Invitation created', ['invitation_id' => $invitation->id]);

            return response()->json([
                'message'    => 'Boutique créée et invitation envoyée.',
                'data'       => $entity->load('domain'),
                'invitation' => $invitation,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[EntityController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function show(Entity $entity): JsonResponse
    {
        Log::info('[EntityController@show] Fetching entity', ['entity_id' => $entity->id]);
        try {
            $data = $entity->load(['domain', 'links.manager']);
            Log::info('[EntityController@show] Success');
            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            Log::error('[EntityController@show] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function update(Request $request, Entity $entity): JsonResponse
    {
        Log::info('[EntityController@update] Updating entity', ['entity_id' => $entity->id]);
        try {
            $request->validate([
                'domain_id'       => 'sometimes|exists:domains,id',
                'name'            => 'sometimes|string|max:255',
                'reference'       => 'nullable|string|max:255|unique:entities,reference,' . $entity->id,
                'subdomain'       => 'nullable|string|max:255|unique:entities,subdomain,' . $entity->id,
                'website_status'  => 'nullable|string|max:30',
                'logo'            => 'nullable|string',
                'primary_color'   => 'nullable|string|max:20',
                'secondary_color' => 'nullable|string|max:20',
                'web_slider'      => 'nullable',
                'address'         => 'nullable|string|max:255',
                'town'            => 'nullable|string|max:255',
                'country'         => 'nullable|string|max:255',
                'email'           => 'nullable|email|max:255',
                'ccphone'         => 'nullable|string|max:10',
                'phone'           => 'nullable|string|max:20',
            ]);

            $data = $request->only([
                'reference', 'subdomain', 'website_status', 'domain_id', 'name', 'logo', 'primary_color', 'secondary_color',
                'address', 'town', 'country', 'email', 'ccphone', 'phone',
            ]);
            $data['primary_color'] = $this->nullableString($data['primary_color'] ?? null);
            $data['secondary_color'] = $this->nullableString($data['secondary_color'] ?? null);
            $webSlider = $this->normalizeWebSlider($request->input('web_slider'));
            if ($webSlider !== null) {
                $data['web_slider'] = $webSlider;
            }

            $entity->update($data);
            Log::info('[EntityController@update] Success', ['entity_id' => $entity->id]);

            return response()->json([
                'message' => 'Boutique mise à jour.',
                'data'    => $entity->load('domain'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[EntityController@update] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function destroy(Entity $entity): JsonResponse
    {
        Log::info('[EntityController@destroy] Deleting entity', ['entity_id' => $entity->id]);
        try {
            $entity->delete();
            Log::info('[EntityController@destroy] Success');
            return response()->json(['message' => 'Boutique supprimée.']);
        } catch (\Exception $e) {
            Log::error('[EntityController@destroy] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function resolve(Request $request): JsonResponse
    {
        try {
            $reference = $request->query('reference');
            $subdomain = $request->query('subdomain');
            $domain = $request->query('domain');
            $host = $request->query('host') ?? $request->getHost();

            $entity = null;

            if ($reference) {
                $entity = Entity::whereRaw('LOWER(reference) = ?', [mb_strtolower(trim((string) $reference))])->with('domain')->first();
            } elseif ($subdomain) {
                $entity = Entity::whereRaw('LOWER(subdomain) = ?', [mb_strtolower(trim((string) $subdomain))])->with('domain')->first();
            } elseif ($domain) {
                $entity = Entity::whereHas('domain', function ($query) use ($domain) {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $domain))]);
                })->with('domain')->first();
            } elseif ($host) {
                $normalizedHost = mb_strtolower(trim((string) $host));
                $entity = Entity::whereRaw('LOWER(reference) = ?', [$normalizedHost])
                    ->orWhereRaw('LOWER(subdomain) = ?', [$normalizedHost])
                    ->with('domain')
                    ->first();
            }

            if (!$entity) {
                return response()->json(['message' => 'Boutique introuvable'], 404);
            }

            return response()->json([
                'data' => [
                    'id' => $entity->id,
                    'reference' => $entity->reference,
                    'subdomain' => $entity->subdomain,
                    'website_status' => $entity->website_status,
                    'name' => $entity->name,
                    'type' => $entity->type,
                    'logo' => $entity->logo,
                    'primary_color' => $entity->primary_color,
                    'secondary_color' => $entity->secondary_color,
                    'web_slider' => $entity->web_slider,
                    'address' => $entity->address,
                    'town' => $entity->town,
                    'country' => $entity->country,
                    'email' => $entity->email,
                    'ccphone' => $entity->ccphone,
                    'phone' => $entity->phone,
                    'wave_business_id' => $entity->wave_business_id,
                    'wave_business_status' => (bool) $entity->wave_business_status,
                    'fayko_status' => (bool) $entity->fayko_status,
                    'delivery_zones' => $entity->delivery_zones,
                    'logo_url' => $entity->logo ? (str_starts_with($entity->logo, 'http') ? $entity->logo : (new FileUploadService())->getUrl($entity->logo)) : null,
                    'domain' => $entity->domain,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[EntityController@resolve] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
