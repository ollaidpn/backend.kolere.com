<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtensionCredential;
use Illuminate\Http\Request;

class ExtensionCredentialController extends Controller
{
    private function getEntityId(Request $request)
    {
        $entityId = $request->attributes->get('current_entity_id') 
            ?? $request->get('entity_id') 
            ?? $request->user()?->entity_id;

        if (!$entityId && $request->user() instanceof \App\Models\Manager) {
            $entityId = $request->user()->currentLink()?->first()?->entity_id;
        }

        if (!$entityId) {
            $entityId = \App\Models\Entity::first()?->id;
        }

        return $entityId;
    }

    /**
     * Obtenir les identifiants/statut d'une extension pour l'entité courante.
     */
    public function show(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        try {
            $credential = ExtensionCredential::where('entity_id', $entityId)
                ->where('extension', strtolower($extension))
                ->first();
        } catch (\Throwable $e) {
            $credential = null;
        }

        return response()->json([
            'success' => true,
            'data' => $credential ? [
                'extension' => $credential->extension,
                'status' => (bool) $credential->status,
                'data' => $credential->data ?? [],
                'config' => $credential->config ?? [],
            ] : [
                'extension' => strtolower($extension),
                'status' => false,
                'data' => [
                    'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
                    'email' => 'pharmaciekhadijaba@gmail.com',
                    'password' => 'meditect2025',
                    'pin' => '202600',
                ],
                'config' => [],
            ]
        ]);
    }

    /**
     * Mettre à jour uniquement la configuration (ex: selected_rayons) d'une extension.
     */
    public function updateConfig(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        try {
            $extensionName = strtolower($extension);
            $config = $validated['config'];
            $debugTrace = [];

            if ($extensionName === 'meditect') {
                $meditectService = app(\App\Services\MeditectService::class);

                $normalizeRayonIds = static function ($rayons): array {
                    if (!is_array($rayons)) {
                        return [];
                    }

                    $ids = [];

                    foreach ($rayons as $rayon) {
                        $value = null;
                        if (is_array($rayon)) {
                            $value = $rayon['id'] ?? $rayon['storage_id'] ?? $rayon['value'] ?? $rayon['name'] ?? null;
                        } elseif (is_string($rayon) || is_numeric($rayon)) {
                            $value = $rayon;
                        }

                        $value = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : null);
                        if ($value !== null && $value !== '') {
                            $ids[] = $value;
                        }
                    }

                    $ids = array_values(array_unique($ids));
                    sort($ids);

                    return $ids;
                };

                // Récupération de l'ancienne config pour vérifier le changement de rayons
                $oldCredential = ExtensionCredential::where('entity_id', $entityId)
                    ->where('extension', 'meditect')
                    ->first();
                $oldRayons = $oldCredential?->config['selected_rayons'] ?? [];
                $newRayons = $config['selected_rayons'] ?? [];

                // Normaliser les listes d'IDs de rayons pour éviter les resets sur simple sauvegarde
                $oldRayonIds = $normalizeRayonIds($oldRayons);
                $newRayonIds = $normalizeRayonIds($newRayons);

                // Sauvegarde d'une nouvelle config Meditect = on repart de zéro.
                $debugTrace[] = [
                    'at' => now()->toIso8601String(),
                    'step' => 'config.received',
                    'message' => 'Configuration Meditect reçue depuis le frontend.',
                    'context' => (object) [
                        'entity_id' => $entityId,
                        'selected_rayons' => count($newRayons),
                        'selected_rayon_ids' => $newRayonIds,
                    ],
                ];

                \App\Models\MeditectImportSession::where('entity_id', $entityId)->delete();
                \App\Models\MeditectImportTask::where('entity_id', $entityId)->delete();
                $debugTrace[] = [
                    'at' => now()->toIso8601String(),
                    'step' => 'queue.reset',
                    'message' => 'Ancienne session et anciennes tâches supprimées avant une nouvelle synchro.',
                    'context' => (object) [
                        'entity_id' => $entityId,
                    ],
                ];

                $config = $meditectService->normalizeMeditectConfigForEntity($entityId, $config);
                if (isset($config['selected_rayons']) && is_array($config['selected_rayons'])) {
                    foreach ($config['selected_rayons'] as &$r) {
                        $r['synched'] = 0;
                        $r['status'] = 'pending';
                    }
                    unset($r);
                }
                unset($config['import_state']);
                $debugTrace[] = [
                    'at' => now()->toIso8601String(),
                    'step' => 'config.normalized',
                    'message' => 'Configuration Meditect normalisée.',
                    'context' => (object) [
                        'selected_rayons' => count($config['selected_rayons'] ?? []),
                    ],
                ];
            }

            if (!\Illuminate\Support\Facades\Schema::hasColumn('extension_credentials', 'config')) {
                \Illuminate\Support\Facades\Schema::table('extension_credentials', function ($table) {
                    $table->json('config')->nullable()->after('data');
                });
            }

            $credentialData = [
                'config' => $config,
            ];

            if ($extensionName === 'meditect') {
                $credentialData['status'] = true;
            }

            $credential = ExtensionCredential::updateOrCreate(
                [
                    'entity_id' => $entityId,
                    'extension' => $extensionName,
                ],
                $credentialData
            );
            if ($extensionName === 'meditect') {
                $debugTrace[] = [
                    'at' => now()->toIso8601String(),
                    'step' => 'credential.saved',
                    'message' => 'Credential Meditect enregistrée et activée.',
                    'context' => (object) [
                        'credential_id' => $credential->id,
                        'status' => (bool) $credential->status,
                    ],
                ];
            }

            $importResult = null;
            if ($extensionName === 'meditect') {
                $savedConfig = $credential->config ?? $config;
                $hasRayons = !empty($savedConfig['selected_rayons'] ?? []);
                if ($hasRayons) {
                    $debugTrace[] = [
                        'at' => now()->toIso8601String(),
                        'step' => 'import.scheduled',
                        'message' => 'Planification de la synchronisation Meditect.',
                        'context' => (object) [
                            'has_rayons' => true,
                        ],
                    ];
                    // La session sera initialisée par le worker ou de façon différée sans bloquer le Save HTTP
                    $importResult = ['status' => 'pending', 'message' => 'Rayons enregistrés. Synchronisation prête.'];
                } else {
                    $debugTrace[] = [
                        'at' => now()->toIso8601String(),
                        'step' => 'import.skipped',
                        'message' => 'Aucun rayon sélectionné, la synchronisation n\'a pas démarré.',
                        'context' => (object) [],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuration sauvegardée !',
                'data' => [
                    'extension' => $credential->extension,
                    'status' => (bool) $credential->status,
                    'config' => $credential->config,
                    'import' => $importResult,
                    'debug_trace' => $debugTrace,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde de la config: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les produits Meditect pour la page d'articles.
     */
    public function sync(Request $request)
    {
        $meditectService = app(\App\Services\MeditectService::class);
        $credentials = [
            'api_key' => $request->input('api_key', 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk'),
            'email' => $request->input('email', 'pharmaciekhadijaba@gmail.com'),
            'password' => $request->input('password', 'meditect2025'),
            'pin' => $request->input('pin', '202600'),
        ];

        $tokens = $meditectService->authenticate($credentials);
        if (!$tokens) {
            return response()->json(['success' => false, 'message' => 'Authentification Meditect échouée'], 400);
        }

        $syncData = $meditectService->getAllProductsWithMeta($tokens) ?? [
            'products' => [],
            'page' => 1,
            'size' => 0,
            'total' => 0,
            'totalPages' => 1,
            'meta' => null,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $syncData['products'] ?? [],
                'page' => $syncData['page'] ?? 1,
                'size' => $syncData['size'] ?? 0,
                'total' => $syncData['total'] ?? 0,
                'totalPages' => $syncData['totalPages'] ?? 1,
                'meta' => $syncData['meta'] ?? null,
            ],
        ]);
    }

    /**
     * Obtenir la liste des rayons/storages depuis Meditect pour cette entité.
     */
    public function storages(Request $request)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        $meditectService = app(\App\Services\MeditectService::class);
        $credentials = $meditectService->getCredentials($entityId);

        $defaults = [
            'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
            'email' => 'pharmaciekhadijaba@gmail.com',
            'password' => 'meditect2025',
            'pin' => '202600',
        ];

        $credentials = array_merge($defaults, array_filter($credentials ?? []));

        $tokens = $meditectService->authenticate($credentials);
        if (!$tokens) {
            return response()->json(['success' => false, 'message' => 'Impossible de s\'authentifier auprès de Meditect'], 400);
        }

        $storages = $meditectService->getStorages($tokens);
        return response()->json([
            'success' => true,
            'data' => $storages ?? [],
        ]);
    }

    /**
     * Obtenir les détails d'un produit (Référentiel complet, Catégorie, Fabricant/Manufacturer) depuis l'API Meditect.
     */
    public function productDetails(Request $request, string $productId)
    {
        $entityId = $this->getEntityId($request);
        $meditectService = app(\App\Services\MeditectService::class);
        $credentials = $entityId ? $meditectService->getCredentials($entityId) : [];

        $defaults = [
            'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
            'email' => 'pharmaciekhadijaba@gmail.com',
            'password' => 'meditect2025',
            'pin' => '202600',
        ];

        $credentials = array_merge($defaults, array_filter($credentials ?? []));
        $tokens = $meditectService->authenticate($credentials);

        if (!$tokens) {
            return response()->json(['success' => false, 'message' => 'Authentification Meditect échouée'], 400);
        }

        $details = $meditectService->getProductDetails($tokens, $productId);
        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Enregistrer / Mettre à jour les identifiants et le statut d'une extension.
     */
    public function store(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        $validated = $request->validate([
            'status' => 'required|boolean',
            'data' => 'required|array',
            'data.api_key' => 'nullable|string',
            'data.email' => 'required|email',
            'data.password' => 'required|string',
            'data.pin' => 'required|string',
        ]);

        try {
            $credential = ExtensionCredential::updateOrCreate(
                [
                    'entity_id' => $entityId,
                    'extension' => strtolower($extension),
                ],
                [
                    'status' => $validated['status'],
                    'data' => $validated['data'],
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Paramètres de l\'extension enregistrés avec succès !',
                'data' => [
                    'extension' => $credential->extension,
                    'status' => (bool) $credential->status,
                    'data' => $credential->data,
                ],
            ]);
        } catch (\Throwable $e) {
            // Si la table n'existe pas encore sur la base locale, tenter la création dynamique
            try {
                \Illuminate\Support\Facades\Schema::create('extension_credentials', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('entity_id');
                    $table->string('extension');
                    $table->boolean('status')->default(false);
                    $table->json('data')->nullable();
                    $table->timestamps();
                    $table->unique(['entity_id', 'extension']);
                });

                $credential = ExtensionCredential::updateOrCreate(
                    [
                        'entity_id' => $entityId,
                        'extension' => strtolower($extension),
                    ],
                    [
                        'status' => $validated['status'],
                        'data' => $validated['data'],
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Paramètres de l\'extension enregistrés avec succès (table créée) !',
                    'data' => [
                        'extension' => $credential->extension,
                        'status' => (bool) $credential->status,
                        'data' => $credential->data,
                    ],
                ]);
            } catch (\Throwable $ex) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur base de données : ' . $ex->getMessage(),
                ], 500);
            }
        }
    }

    public function importStatus(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        if (strtolower($extension) !== 'meditect') {
            return response()->json(['success' => false, 'message' => 'Import non supporté pour cette extension'], 400);
        }

        $meditectService = app(\App\Services\MeditectService::class);
        $state = $meditectService->getMeditectImportStatus($entityId);

        return response()->json([
            'success' => true,
            'data' => $state ? [
                'config' => $state['config'] ?? [],
                'queue' => $state['queue'] ?? null,
                'session' => $state['session'] ? [
                    'id' => $state['session']->id,
                    'status' => $state['session']->status,
                    'cursor' => $state['session']->cursor,
                    'processed_count' => $state['session']->processed_count,
                    'imported_count' => $state['session']->imported_count,
                    'matched_count' => $state['session']->matched_count,
                    'total_count' => $state['session']->total_count,
                    'buffer_index' => $state['session']->buffer_index,
                    'batch_size' => $state['session']->batch_size,
                    'error_message' => $state['session']->error_message,
                    'started_at' => $state['session']->started_at,
                    'paused_at' => $state['session']->paused_at,
                    'finished_at' => $state['session']->finished_at,
                    'last_run_at' => $state['session']->last_run_at,
                ] : null,
            ] : null,
        ]);
    }

    public function importStart(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        if (strtolower($extension) !== 'meditect') {
            return response()->json(['success' => false, 'message' => 'Import non supporté pour cette extension'], 400);
        }

        $meditectService = app(\App\Services\MeditectService::class);
        // La récupération et le filtrage du catalogue sont traités par le cron.
        // L'endpoint HTTP doit répondre immédiatement, même avec plusieurs rayons.
        $result = $meditectService->queueMeditectImportSession($entityId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function importPause(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        if (strtolower($extension) !== 'meditect') {
            return response()->json(['success' => false, 'message' => 'Import non supporté pour cette extension'], 400);
        }

        $meditectService = app(\App\Services\MeditectService::class);
        $result = $meditectService->pauseMeditectImportSession($entityId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function importResume(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        if (strtolower($extension) !== 'meditect') {
            return response()->json(['success' => false, 'message' => 'Import non supporté pour cette extension'], 400);
        }

        $meditectService = app(\App\Services\MeditectService::class);
        $result = $meditectService->resumeMeditectImportSession($entityId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function importStop(Request $request, string $extension)
    {
        $entityId = $this->getEntityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité non résolue'], 400);
        }

        if (strtolower($extension) !== 'meditect') {
            return response()->json(['success' => false, 'message' => 'Import non supporté pour cette extension'], 400);
        }

        $meditectService = app(\App\Services\MeditectService::class);
        $result = $meditectService->cancelMeditectImportSession($entityId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
