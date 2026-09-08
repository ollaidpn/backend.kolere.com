<?php

namespace App\Services;

use App\Models\ExtensionCredential;
use App\Models\MeditectImportTask;
use App\Models\MeditectImportSession;
use App\Models\ShopBrand;
use App\Models\ShopCategory;
use App\Models\ShopItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MeditectService
{
    private function meditectDebug(array &$trace, string $step, string $message, array $context = []): void
    {
        $trace[] = [
            'at' => now()->toIso8601String(),
            'step' => $step,
            'message' => $message,
            'context' => (object) $context,
        ];
    }

    /**
     * Récupérer les identifiants enregistrés pour une entité donnée.
     */
    public function getCredentials(int $entityId): ?array
    {
        $credential = ExtensionCredential::where('entity_id', $entityId)
            ->where('extension', 'meditect')
            ->first();

        if (!$credential || !$credential->status) {
            return null;
        }

        return $credential->data;
    }

    /**
     * Authentification complète auprès des services Meditect (Firebase + Token Meditect)
     */
    public function authenticate(array $credentials): array|string|null
    {
        $apiKey = $credentials['api_key'] ?? 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk';
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';
        $pin = $credentials['pin'] ?? '';

        $cacheKey = 'meditect_auth_tokens_' . md5($email . '|' . $pin);
        $cachedTokens = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cachedTokens && is_array($cachedTokens)) {
            return $cachedTokens;
        }

        try {
            // 1. Étape 1 : Connexion Firebase Identity Toolkit
            $authRes = Http::post("https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key={$apiKey}", [
                'email' => $email,
                'password' => $password,
                'returnSecureToken' => true,
            ]);

            if ($authRes->failed()) {
                Log::error('[MeditectService] Échec connexion Firebase:', $authRes->json());
                return null;
            }

            $idToken = $authRes->json('idToken');

            // 2. Étape 2 : Échange du token + PIN auprès du serveur Meditect (/tokens/)
            $tokenRes = Http::withHeaders([
                'Authorization' => "Bearer {$idToken}",
                'Content-Type' => 'application/json',
            ])->post('https://api-315096745494.europe-west1.run.app/tokens/', [
                'pin' => $pin,
            ]);

            if ($tokenRes->failed()) {
                Log::error('[MeditectService] Échec échange PIN Meditect:', $tokenRes->json());
                return null;
            }

            $customToken = $tokenRes->json('token') ?? $tokenRes->json('access_token');

            // 3. Étape 3 : Échange CustomToken -> Firebase (signInWithCustomToken)
            $customAuthRes = Http::post("https://identitytoolkit.googleapis.com/v1/accounts:signInWithCustomToken?key={$apiKey}", [
                'token' => $customToken,
                'returnSecureToken' => true,
            ]);

            $finalFirebaseToken = $idToken;
            if ($customAuthRes->successful()) {
                $finalFirebaseToken = $customAuthRes->json('idToken') ?? $idToken;
            }

            // 4. Étape 4 : lookup utilisateur Firebase
            Http::post("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$apiKey}", [
                'idToken' => $finalFirebaseToken,
            ]);

            $tokensResult = [
                'idToken' => $idToken,
                'customToken' => $customToken,
                'finalFirebaseToken' => $finalFirebaseToken,
            ];

            \Illuminate\Support\Facades\Cache::put($cacheKey, $tokensResult, now()->addMinutes(50));

            return $tokensResult;
        } catch (\Exception $e) {
            Log::error('[MeditectService] Erreur pendant l\'authentification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer la méta globale du catalogue Meditect.
     * L'endpoint /products expose total / totalPages, ce qui permet d'obtenir
     * le vrai nombre d'articles visibles côté Meditect.
     */
    public function getProductsMeta(array|string $tokens): ?array
    {
        $tokenList = is_array($tokens) ? array_filter([$tokens['idToken'] ?? null, $tokens['finalFirebaseToken'] ?? null, $tokens['customToken'] ?? null]) : [$tokens];

        foreach ($tokenList as $token) {
            try {
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])->get('https://api-315096745494.europe-west1.run.app/pharmacy-products/', [
                    'page' => 1,
                    'size' => 1,
                ]);

                if (!$res->successful()) {
                    continue;
                }

                $json = $res->json();
                if (!is_array($json)) {
                    continue;
                }

                $items = [];
                if (isset($json['items']) && is_array($json['items'])) {
                    $items = $json['items'];
                }

                $size = (int) ($json['size'] ?? max(1, count($items)));
                $total = (int) ($json['total'] ?? count($items));
                $totalPages = (int) ($json['totalPages'] ?? max(1, (int) ceil(max($total, 1) / max($size, 1))));

                return [
                    'page' => (int) ($json['page'] ?? 1),
                    'size' => $size,
                    'total' => $total,
                    'totalPages' => $totalPages,
                ];
            } catch (\Exception $e) {
                Log::error('[MeditectService] Erreur lors de la récupération de la méta produits: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Récupération de TOUS les produits d'officine depuis Meditect.
     */
    public function getAllProducts(array|string $tokens): ?array
    {
        $tokenList = is_array($tokens) ? array_filter([$tokens['idToken'] ?? null, $tokens['finalFirebaseToken'] ?? null, $tokens['customToken'] ?? null]) : [$tokens];

        foreach ($tokenList as $token) {
            try {
                $allProducts = [];
                $cursor = null;
                $seenCursors = [];
                $attemptedCursorPagination = false;

                do {
                    $attemptedCursorPagination = true;

                    $request = Http::withHeaders([
                        'Authorization' => "Bearer {$token}",
                    ]);

                    $res = $request->get('https://api-315096745494.europe-west1.run.app/pharmacy-products/all', array_filter([
                        'cursor' => $cursor,
                    ], static fn ($value) => $value !== null && $value !== ''));

                    if ($res->failed()) {
                        break;
                    }

                    $json = $res->json();
                    $batch = [];

                    if (is_array($json) && isset($json['items']) && is_array($json['items'])) {
                        $batch = $json['items'];
                    } elseif (is_array($json) && isset($json['data']) && is_array($json['data'])) {
                        $batch = $json['data'];
                    } elseif (is_array($json) && isset($json['products']) && is_array($json['products'])) {
                        $batch = $json['products'];
                    } elseif (is_array($json)) {
                        $batch = $json;
                    }

                    if (!empty($batch)) {
                        $allProducts = array_merge($allProducts, $batch);
                    }

                    $nextCursor = is_array($json) ? ($json['nextCursor'] ?? null) : null;
                    $nextCursor = is_string($nextCursor) && trim($nextCursor) !== '' ? trim($nextCursor) : null;

                    if ($nextCursor && in_array($nextCursor, $seenCursors, true)) {
                        Log::warning('[MeditectService] Cursor pagination loop detected, stopping', [
                            'cursor' => $nextCursor,
                        ]);
                        break;
                    }

                    if ($nextCursor) {
                        $seenCursors[] = $nextCursor;
                    }

                    $cursor = $nextCursor;
                } while (!empty($cursor));

                if ($attemptedCursorPagination && !empty($allProducts)) {
                    return $allProducts;
                }

                // Fallback historique si l'endpoint cursor n'est pas dispo sur une instance donnée.
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])->get('https://api-315096745494.europe-west1.run.app/pharmacy-products/');

                if ($res->successful()) {
                    $json = $res->json();
                    if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
                        return $json['data'];
                    }
                    if (is_array($json) && isset($json['items']) && is_array($json['items'])) {
                        return $json['items'];
                    }
                    if (is_array($json) && isset($json['products']) && is_array($json['products'])) {
                        return $json['products'];
                    }

                    return is_array($json) ? $json : [];
                }
            } catch (\Exception $e) {
                Log::error('[MeditectService] Erreur lors de la récupération des produits: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Récupérer les produits Meditect avec leur méta distante.
     */
    public function getAllProductsWithMeta(array|string $tokens): ?array
    {
        $products = $this->getAllProducts($tokens) ?? [];
        $meta = $this->getProductsMeta($tokens) ?? [];
        $catalogSize = (int) ($meta['size'] ?? max(1, count($products)));
        $catalogTotal = (int) ($meta['total'] ?? count($products));
        $catalogTotalPages = (int) ($meta['totalPages'] ?? max(1, (int) ceil(max($catalogTotal, 1) / max($catalogSize, 1))));

        return [
            'products' => $products,
            'page' => (int) ($meta['page'] ?? 1),
            'size' => $catalogSize,
            'total' => $catalogTotal,
            'totalPages' => $catalogTotalPages,
            'meta' => $meta,
        ];
    }

    /**
     * Récupérer une page de produits Meditect via cursor.
     */
    public function getProductsPage(array|string $tokens, int $page = 1, int $size = 250): ?array
    {
        $tokenList = is_array($tokens) ? array_filter([$tokens['idToken'] ?? null, $tokens['finalFirebaseToken'] ?? null, $tokens['customToken'] ?? null]) : [$tokens];

        foreach ($tokenList as $token) {
            try {
                $res = Http::timeout(15)->withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])->get('https://api-315096745494.europe-west1.run.app/pharmacy-products/', [
                    'page' => max(1, $page),
                    'size' => max(1, $size),
                ]);

                if ($res->failed()) {
                    continue;
                }

                $json = $res->json();
                if (!is_array($json)) {
                    continue;
                }

                $items = [];
                if (isset($json['items']) && is_array($json['items'])) {
                    $items = $json['items'];
                } elseif (isset($json['data']) && is_array($json['data'])) {
                    $items = $json['data'];
                } elseif (isset($json['products']) && is_array($json['products'])) {
                    $items = $json['products'];
                } elseif (array_is_list($json)) {
                    $items = $json;
                }

                return [
                    'items' => $items,
                    'page' => (int) ($json['page'] ?? $page),
                    'size' => (int) ($json['size'] ?? count($items)),
                    'total' => (int) ($json['total'] ?? count($items)),
                    'totalPages' => (int) ($json['totalPages'] ?? max(1, (int) ceil(max((int) ($json['total'] ?? count($items)), 1) / max((int) ($json['size'] ?? count($items)), 1)))),
                ];
            } catch (\Exception $e) {
                Log::error('[MeditectService] Erreur lors de la récupération d\'une page produits: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Récupérer une page de produits filtrée par rayon (storageId).
     */
    public function getProductsPageByStorageId(array|string $tokens, string $storageId, int $page = 1, int $size = 100): ?array
    {
        $tokenList = is_array($tokens) ? array_filter([$tokens['idToken'] ?? null, $tokens['finalFirebaseToken'] ?? null, $tokens['customToken'] ?? null]) : [$tokens];

        foreach ($tokenList as $token) {
            try {
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])->get('https://api-315096745494.europe-west1.run.app/pharmacy-products/', [
                    'storageId' => $storageId,
                    'page' => max(1, $page),
                    'size' => max(1, $size),
                ]);

                if ($res->failed()) {
                    continue;
                }

                $json = $res->json();
                if (!is_array($json)) {
                    continue;
                }

                $items = [];
                if (isset($json['items']) && is_array($json['items'])) {
                    $items = $json['items'];
                } elseif (isset($json['data']) && is_array($json['data'])) {
                    $items = $json['data'];
                } elseif (isset($json['products']) && is_array($json['products'])) {
                    $items = $json['products'];
                } elseif (array_is_list($json)) {
                    $items = $json;
                }

                return [
                    'items' => $items,
                    'page' => (int) ($json['page'] ?? $page),
                    'size' => (int) ($json['size'] ?? count($items)),
                    'total' => (int) ($json['total'] ?? count($items)),
                    'totalPages' => (int) ($json['totalPages'] ?? max(1, (int) ceil(max((int) ($json['total'] ?? count($items)), 1) / max((int) ($json['size'] ?? count($items)), 1)))),
                ];
            } catch (\Exception $e) {
                Log::error('[MeditectService] Erreur lors de la récupération filtrée des produits: ' . $e->getMessage());
            }
        }

        return null;
    }

    private function normalizeMeditectValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value !== '' ? $value : null;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            foreach (['label', 'name', 'value', 'code', 'id'] as $key) {
                if (array_key_exists($key, $value)) {
                    $normalized = $this->normalizeMeditectValue($value[$key]);
                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeMeditectKey(mixed $value): string
    {
        $normalized = $this->normalizeMeditectValue($value);
        return $normalized !== null ? Str::lower($normalized) : '';
    }

    private function buildImportTaskPayload(array $product): array
    {
        $stockItems = [];
        if (isset($product['stockItems']) && is_array($product['stockItems'])) {
            foreach ($product['stockItems'] as $stockItem) {
                $stockItems[] = [
                    'id' => $this->normalizeMeditectValue($stockItem['id'] ?? null),
                    'quantity' => (int) ($stockItem['quantity'] ?? 0),
                    'salePrice' => is_numeric($stockItem['salePrice'] ?? null) ? (float) $stockItem['salePrice'] : null,
                    'purchasePrice' => is_numeric($stockItem['purchasePrice'] ?? null) ? (float) $stockItem['purchasePrice'] : null,
                    'location' => $this->normalizeMeditectValue($stockItem['location'] ?? null),
                    'storage' => is_array($stockItem['storage'] ?? null) ? [
                        'id' => $this->normalizeMeditectValue($stockItem['storage']['id'] ?? null),
                        'name' => $this->normalizeMeditectValue($stockItem['storage']['name'] ?? null),
                    ] : null,
                ];
            }
        }

        return [
            'id' => $this->normalizeMeditectValue($product['id'] ?? null),
            'productId' => $this->normalizeMeditectValue($product['productId'] ?? null),
            'pharmacyId' => $this->normalizeMeditectValue($product['pharmacyId'] ?? null),
            'hasSaleDetails' => (bool) ($product['hasSaleDetails'] ?? false),
            'taxRate' => $product['taxRate'] ?? null,
            'isRefundable' => (bool) ($product['isRefundable'] ?? false),
            'product' => [
                'id' => $this->normalizeMeditectValue($product['product']['id'] ?? null),
                'label' => $this->normalizeMeditectValue($product['product']['label'] ?? null),
                'statut' => $this->normalizeMeditectValue($product['product']['statut'] ?? null),
                'downloadURL' => $this->normalizeMeditectValue($product['product']['downloadURL'] ?? null),
                'codes' => is_array($product['product']['codes'] ?? null) ? $product['product']['codes'] : [],
                'listings' => is_array($product['product']['listings'] ?? null) ? $product['product']['listings'] : [],
                'category' => is_array($product['product']['category'] ?? null) ? $product['product']['category'] : null,
                'brand' => is_array($product['product']['brand'] ?? null) ? $product['product']['brand'] : null,
            ],
            'stockItems' => $stockItems,
            'quantity' => (int) ($product['quantity'] ?? 0),
            'quantityInReserve' => (int) ($product['quantityInReserve'] ?? 0),
            'quantitiesByPackaging' => (int) ($product['quantitiesByPackaging'] ?? 0),
            'gap' => (int) ($product['gap'] ?? 0),
            'purchasePrices' => is_array($product['purchasePrices'] ?? null) ? $product['purchasePrices'] : [],
            'depackagedPurchasePrices' => is_array($product['depackagedPurchasePrices'] ?? null) ? $product['depackagedPurchasePrices'] : [],
            'averagePurchasePrice' => $product['averagePurchasePrice'] ?? null,
            'averageDepackagedPurchasePrice' => $product['averageDepackagedPurchasePrice'] ?? null,
            'salePrices' => is_array($product['salePrices'] ?? null) ? $product['salePrices'] : [],
            'depackagedSalePrices' => is_array($product['depackagedSalePrices'] ?? null) ? $product['depackagedSalePrices'] : [],
            'averageSalePrice' => $product['averageSalePrice'] ?? null,
            'averageDepackagedSalePrice' => $product['averageDepackagedSalePrice'] ?? null,
            'storages' => $this->extractProductStorages($product),
        ];
    }

    private function mergeTaskRayonValues(array $existing, array $incoming): array
    {
        $merged = array_values(array_unique(array_filter(array_map('strval', array_merge($existing, $incoming)))));
        sort($merged);

        return $merged;
    }

    private function resolveMeditectProductReferenceId(array $payload): ?string
    {
        return $this->normalizeMeditectValue($payload['product']['id'] ?? null)
            ?? $this->normalizeMeditectValue($payload['productId'] ?? null);
    }

    private function stageMeditectImportTasks(int $entityId, int $sessionId, int $credentialId, array $tokens, array $selectedRayons): array
    {
        $taskBuffer = [];
        $rayonStats = [];
        $totalTasks = 0;
        $seenExternalIds = [];

        // Préparer la map des statistiques par rayon
        foreach ($selectedRayons as $rayon) {
            $rId = $this->normalizeMeditectValue($rayon['id'] ?? null);
            if ($rId !== null && $rId !== '') {
                $rName = $this->normalizeMeditectValue($rayon['name'] ?? null) ?? $rId;
                $rayonStats[$rId] = [
                    'id' => $rId,
                    'name' => $rName,
                    'count' => 0,
                ];
            }
        }

        // Récupérer TOUT le catalogue Meditect en 1 seul appel API (endpoint global /pharmacy-products/all)
        $allProducts = $this->getAllProducts($tokens) ?? [];

        foreach ($allProducts as $product) {
            $matchedRayonKeys = $this->getMatchingRayonKeysForProduct($product, $selectedRayons);
            if (empty($matchedRayonKeys)) {
                continue;
            }

            $externalId = $this->resolveMeditectProductReferenceId($product);

            if ($externalId === null || $externalId === '') {
                continue;
            }

            $productStorages = $this->extractProductStorages($product);
            $matchedRayonIds = [];
            $matchedRayonNames = [];

            foreach ($productStorages as $st) {
                $stId = $this->normalizeMeditectValue($st['id'] ?? null);
                $stName = $this->normalizeMeditectValue($st['name'] ?? null) ?? $stId;
                $stIdKey = $this->normalizeMeditectKey($stId);
                $stNameKey = $this->normalizeMeditectKey($stName);

                foreach ($rayonStats as $rId => &$rStat) {
                    $rIdKey = $this->normalizeMeditectKey($rId);
                    $rNameKey = $this->normalizeMeditectKey($rStat['name'] ?? null);
                    if (($stIdKey !== '' && ($stIdKey === $rIdKey || $stIdKey === $rNameKey)) ||
                        ($stNameKey !== '' && ($stNameKey === $rIdKey || $stNameKey === $rNameKey))) {
                        $matchedRayonIds[] = $rId;
                        $matchedRayonNames[] = $rStat['name'];
                        $rStat['count']++;
                    }
                }
                unset($rStat);
            }

            if (isset($seenExternalIds[$externalId])) {
                continue;
            }
            $seenExternalIds[$externalId] = true;

            $payload = $this->buildImportTaskPayload($product);
            $totalTasks++;
            $taskBuffer[$externalId] = [
                'entity_id' => $entityId,
                'extension_credential_id' => $credentialId,
                'meditect_import_session_id' => $sessionId,
                'external_item_id' => $externalId,
                'status' => 'pending',
                'rayon_ids' => !empty($matchedRayonIds) ? array_values(array_unique($matchedRayonIds)) : null,
                'rayon_names' => !empty($matchedRayonNames) ? array_values(array_unique($matchedRayonNames)) : null,
                'summary' => $payload,
                'details' => null,
                'shop_item_id' => null,
                'attempts' => 0,
                'error_message' => null,
                'started_at' => null,
                'processed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($taskBuffer) >= 500) {
                $this->flushMeditectImportTaskBuffer($taskBuffer);
                $taskBuffer = [];
            }
        }

        if (!empty($taskBuffer)) {
            $this->flushMeditectImportTaskBuffer($taskBuffer);
            $taskBuffer = [];
        }

        return [
            'total_tasks' => $totalTasks,
            'rayon_stats' => $rayonStats,
        ];
    }

    private function flushMeditectImportTaskBuffer(array $taskBuffer): void
    {
        $tasks = array_values($taskBuffer);
        if (empty($tasks)) {
            return;
        }

        foreach (array_chunk($tasks, 500) as $chunk) {
            foreach ($chunk as &$t) {
                if (isset($t['rayon_ids']) && is_array($t['rayon_ids'])) {
                    $t['rayon_ids'] = json_encode($t['rayon_ids']);
                }
                if (isset($t['rayon_names']) && is_array($t['rayon_names'])) {
                    $t['rayon_names'] = json_encode($t['rayon_names']);
                }
                if (isset($t['summary']) && is_array($t['summary'])) {
                    $t['summary'] = json_encode($t['summary']);
                }
                if (isset($t['details']) && is_array($t['details'])) {
                    $t['details'] = json_encode($t['details']);
                }
            }
            unset($t);
            MeditectImportTask::upsert(
                $chunk,
                ['entity_id', 'external_item_id'],
                ['extension_credential_id', 'meditect_import_session_id', 'status', 'rayon_ids', 'rayon_names', 'summary', 'details', 'shop_item_id', 'attempts', 'error_message', 'updated_at']
            );
        }
    }

    private function buildStableReference(string $prefix, int $entityId, string $value): string
    {
        return $prefix . '-' . $entityId . '-' . substr(sha1($entityId . '|' . Str::lower($value)), 0, 12);
    }

    private function extractProductStorages(array $product): array
    {
        $storages = [];

        foreach (['storages', 'stockItems'] as $field) {
            if (!isset($product[$field]) || !is_array($product[$field])) {
                continue;
            }

            foreach ($product[$field] as $entry) {
                if ($field === 'storages') {
                    $id = $this->normalizeMeditectValue($entry['id'] ?? null);
                    $name = $this->normalizeMeditectValue($entry['name'] ?? null);
                    if ($id !== null) {
                        $storages[] = ['id' => $id, 'name' => $name];
                    }
                    continue;
                }

                $id = $this->normalizeMeditectValue($entry['storage']['id'] ?? null);
                $name = $this->normalizeMeditectValue($entry['storage']['name'] ?? null);
                if ($id !== null) {
                    $storages[] = ['id' => $id, 'name' => $name];
                }
            }
        }

        return array_values(array_reduce($storages, function (array $carry, array $storage) {
            $key = $storage['id'] ?: $storage['name'];
            if ($key && !isset($carry[$key])) {
                $carry[$key] = $storage;
            }

            return $carry;
        }, []));
    }

    private function getMatchingRayonKeysForProduct(array $product, array $selectedRayons): array
    {
        if (empty($selectedRayons)) {
            return [];
        }

        $productStorages = $this->extractProductStorages($product);
        if (empty($productStorages)) {
            return [];
        }

        $selectedKeys = [];
        foreach ($selectedRayons as $rayon) {
            $selectedKeys[$this->normalizeMeditectKey($rayon['id'] ?? null)] = true;
            $selectedKeys[$this->normalizeMeditectKey($rayon['name'] ?? null)] = true;
        }

        $matchedKeys = [];
        foreach ($productStorages as $storage) {
            $storageIdKey = $this->normalizeMeditectKey($storage['id'] ?? null);
            if ($storageIdKey !== '' && isset($selectedKeys[$storageIdKey])) {
                $matchedKeys[$storageIdKey] = true;
            }

            $storageNameKey = $this->normalizeMeditectKey($storage['name'] ?? null);
            if ($storageNameKey !== '' && isset($selectedKeys[$storageNameKey])) {
                $matchedKeys[$storageNameKey] = true;
            }
        }

        return array_keys($matchedKeys);
    }

    private function productMatchesSelectedRayons(array $product, array $selectedRayons): bool
    {
        return !empty($this->getMatchingRayonKeysForProduct($product, $selectedRayons));
    }

    private function resolveCategoryLabel(array $summary, array $details): ?string
    {
        return $this->normalizeMeditectValue($details['category']['label'] ?? null)
            ?? $this->normalizeMeditectValue($details['category']['name'] ?? null)
            ?? $this->normalizeMeditectValue($details['category']['parent']['label'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['atcClassification']['label'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['atcClassification']['name'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['pharmaClass']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['category']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['category']['parent']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['category']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['category'] ?? null);
    }

    private function resolveBrandLabel(array $summary, array $details): ?string
    {
        return $this->normalizeMeditectValue($details['manufacturer']['name'] ?? null)
            ?? $this->normalizeMeditectValue($details['manufacturer']['label'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['authHolder'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['holder']['name'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['holder']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['manufacturer']['name'] ?? null)
            ?? $this->normalizeMeditectValue($details['brand']['name'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['brand']['name'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['company']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['company']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['brand'] ?? null)
            ?? $this->normalizeMeditectValue($summary['brand'] ?? null);
    }

    private function resolveProductName(array $summary, array $details): ?string
    {
        return $this->normalizeMeditectValue($details['product']['label'] ?? null)
            ?? $this->normalizeMeditectValue($details['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['label'] ?? null)
            ?? $this->normalizeMeditectValue($summary['name'] ?? null)
            ?? $this->normalizeMeditectValue($summary['title'] ?? null);
    }

    private function resolveProductImage(array $summary, array $details): ?string
    {
        return $this->normalizeMeditectValue($summary['product']['downloadURL'] ?? null)
            ?? $this->normalizeMeditectValue($summary['downloadURL'] ?? null)
            ?? $this->normalizeMeditectValue($details['downloadURL'] ?? null);
    }

    private function resolveProductReference(array $summary, array $details, int $entityId, string $externalId): string
    {
        $externalReference = $this->normalizeMeditectValue($summary['product']['codes'][0]['code'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['listings'][0]['sku'] ?? null)
            ?? $this->normalizeMeditectValue($summary['barcode'] ?? null)
            ?? $this->normalizeMeditectValue($summary['code'] ?? null);

        if ($externalReference) {
            return $this->buildStableReference('MED', $entityId, $externalReference);
        }

        return $this->buildStableReference('MED', $entityId, $externalId);
    }

    private function resolveProductExternalReference(array $summary, array $details): ?string
    {
        return $this->normalizeMeditectValue($summary['product']['codes'][0]['code'] ?? null)
            ?? $this->normalizeMeditectValue($summary['product']['listings'][0]['sku'] ?? null)
            ?? $this->normalizeMeditectValue($summary['barcode'] ?? null)
            ?? $this->normalizeMeditectValue($summary['code'] ?? null)
            ?? $this->normalizeMeditectValue($details['codes'][0]['code'] ?? null);
    }

    private function resolveProductStock(array $summary, array $details): int
    {
        if (isset($summary['stockItems']) && is_array($summary['stockItems'])) {
            $total = 0;
            foreach ($summary['stockItems'] as $stockItem) {
                $total += (int) ($stockItem['quantity'] ?? 0);
            }
            if ($total > 0) {
                return $total;
            }
        }

        if (isset($summary['quantity'])) {
            return (int) $summary['quantity'];
        }

        if (isset($summary['stock'])) {
            return (int) $summary['stock'];
        }

        if (isset($details['stock'])) {
            return (int) $details['stock'];
        }

        return 0;
    }

    private function resolveProductPrice(array $summary, array $details): float
    {
        $candidates = [
            $summary['stockItems'][0]['salePrice'] ?? null,
            $summary['stockItems'][0]['purchasePrice'] ?? null,
            $summary['publicPrice'] ?? null,
            $summary['price'] ?? null,
            $summary['salePrice'] ?? null,
            $details['salePrices'][0] ?? null,
            $details['averageSalePrice'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return 0.0;
    }

    private function resolveProductPromoPrice(array $summary, array $details): ?float
    {
        $candidates = [
            $details['depackagedSalePrices'][0] ?? null,
            $details['averageDepackagedSalePrice'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return null;
    }

    private function resolveProductDescription(array $summary, array $details): ?string
    {
        return $this->normalizeMeditectValue($details['spc']['content'] ?? null)
            ?? $this->normalizeMeditectValue($details['spc']['description'] ?? null)
            ?? $this->normalizeMeditectValue($details['description'] ?? null)
            ?? $this->normalizeMeditectValue($summary['description'] ?? null);
    }

    private function findOrCreateCategory(int $entityId, ?string $label): ?ShopCategory
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $name = trim($label);

        $existing = ShopCategory::where('entity_id', $entityId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ShopCategory::create([
            'entity_id' => $entityId,
            'name' => $name,
            'slug' => Str::slug($name),
            'reference' => $this->buildStableReference('SHC-MED', $entityId, $name),
            'status' => 'active',
        ]);
    }

    private function findOrCreateBrand(int $entityId, ?string $label): ?ShopBrand
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $name = trim($label);

        $existing = ShopBrand::where('entity_id', $entityId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ShopBrand::create([
            'entity_id' => $entityId,
            'name' => $name,
            'slug' => Str::slug($name),
            'reference' => $this->buildStableReference('SHB-MED', $entityId, $name),
            'status' => 'active',
        ]);
    }

    private function upsertMeditectShopItem(int $entityId, array $summary, array $details): ?ShopItem
    {
        $externalId = $this->resolveMeditectProductReferenceId($summary)
            ?? $this->resolveMeditectProductReferenceId($details);

        if ($externalId === null) {
            return null;
        }

        $name = $this->resolveProductName($summary, $details) ?? $this->buildStableReference('MED', $entityId, $externalId);
        $category = $this->findOrCreateCategory($entityId, $this->resolveCategoryLabel($summary, $details));
        $brand = $this->findOrCreateBrand($entityId, $this->resolveBrandLabel($summary, $details));
        $reference = $this->resolveProductReference($summary, $details, $entityId, $externalId);
        $externalReference = $this->resolveProductExternalReference($summary, $details);
        $image = $this->resolveProductImage($summary, $details);
        $description = $this->resolveProductDescription($summary, $details);
        $stock = $this->resolveProductStock($summary, $details);
        $price = $this->resolveProductPrice($summary, $details);
        $promoPrice = $this->resolveProductPromoPrice($summary, $details);
        $storages = $this->extractProductStorages($summary);
        $storageIds = array_values(array_unique(array_filter(array_map(static function (array $storage) {
            return $storage['id'] ?? $storage['name'] ?? null;
        }, $storages))));

        $existingItem = ShopItem::where('entity_id', $entityId)
            ->where('external_source', 'meditect')
            ->where('external_item_id', $externalId)
            ->first();

        $isEnriched = $existingItem ? $existingItem->is_enriched : false;
        $needsEnrichUpdate = $existingItem ? true : false;

        $updateAttributes = [
            'is_enriched' => $isEnriched,
            'needs_enrich_update' => $needsEnrichUpdate,
            'reference' => $reference,
            'external_reference' => $externalReference,
            'external_meta' => [
                'meditect_product_id' => $externalId,
                'meditect_pharmacy_product_id' => $this->normalizeMeditectValue($summary['id'] ?? null),
                'storages' => $storages,
                'storage_ids' => $storageIds,
                'matched_rayons' => [],
            ],
            'name' => $name,
            'price' => $price,
            'promo_price' => $promoPrice,
            'stock' => $stock,
            'description' => $description,
            'image' => $image,
            'gallery' => [],
            'status' => 'active',
        ];

        if (!$existingItem || !$existingItem->category_id) {
            $updateAttributes['category_id'] = $category?->id;
        }

        if ($brand && (!$existingItem || !$existingItem->brand_id)) {
            $updateAttributes['brand_id'] = $brand->id;
        }

        return ShopItem::updateOrCreate(
            [
                'entity_id' => $entityId,
                'external_source' => 'meditect',
                'external_item_id' => $externalId,
            ],
            $updateAttributes
        );
    }

    public function enrichMeditectShopItem(ShopItem $item): array
    {
        $credential = ExtensionCredential::where('entity_id', $item->entity_id)
            ->where('extension', 'meditect')
            ->first();

        $credentials = array_merge([
            'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
            'email' => 'pharmaciekhadijaba@gmail.com',
            'password' => 'meditect2025',
            'pin' => '202600',
        ], array_filter($credential?->data ?? []));

        $tokens = $this->authenticate($credentials);
        if (!$tokens) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Échec d\'authentification Meditect',
            ];
        }

        $targetId = $item->external_item_id
            ?? $item->external_meta['meditect_product_id'] ?? null
            ?? $item->external_meta['product']['id'] ?? null
            ?? $item->external_meta['productId'] ?? null;

        if (!$targetId) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Aucun identifiant Meditect associé à cet article',
            ];
        }

        $details = $this->getProductDetails($tokens, (string) $targetId);

        if (!$details) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Impossible de récupérer les détails Meditect pour cet article',
            ];
        }

        $category = $this->findOrCreateCategory($item->entity_id, $this->resolveCategoryLabel([], $details));
        $brand = $this->findOrCreateBrand($item->entity_id, $this->resolveBrandLabel([], $details));
        $image = $this->resolveProductImage([], $details);
        $description = $this->resolveProductDescription([], $details);

        $updateData = [
            'is_enriched' => true,
            'needs_enrich_update' => false,
        ];

        if ($category) {
            $updateData['category_id'] = $category->id;
        }

        if ($brand) {
            $updateData['brand_id'] = $brand->id;
        }

        if ($image) {
            $updateData['image'] = $image;
        }

        if ($description) {
            $updateData['description'] = $description;
        }

        $item->update($updateData);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Article actualisé avec succès avec Meditect !',
            'data' => $item->load(['category', 'brand']),
        ];
    }

    private function buildSelectedRayonsConfig(int $entityId, array $selectedRayons, array $existingConfig = [], ?array $storages = null, bool $resetProgress = false): array
    {
        $storages = $storages ?? [];
        $storageMap = [];

        foreach ($storages as $storage) {
            $id = $this->normalizeMeditectValue($storage['id'] ?? null);
            $name = $this->normalizeMeditectValue($storage['name'] ?? null);
            if ($id !== null) {
                $storageMap[$this->normalizeMeditectKey($id)] = $storage;
            }
            if ($name !== null) {
                $storageMap[$this->normalizeMeditectKey($name)] = $storage;
            }
        }

        $existingRayons = [];
        foreach (($existingConfig['selected_rayons'] ?? []) as $existingRayon) {
            $existingKey = $this->normalizeMeditectKey($existingRayon['id'] ?? $existingRayon['name'] ?? null);
            if ($existingKey !== '') {
                $existingRayons[$existingKey] = $existingRayon;
            }
        }

        $normalized = [];
        foreach ($selectedRayons as $row) {
            $rawId = is_array($row) ? ($row['id'] ?? $row['storage_id'] ?? $row['value'] ?? null) : $row;
            if ($rawId === null || trim((string) $rawId) === '') {
                continue;
            }

            $lookupKey = $this->normalizeMeditectKey($rawId);
            $storage = $storageMap[$lookupKey] ?? null;
            $existing = $existingRayons[$lookupKey] ?? [];
            $name = $this->normalizeMeditectValue(is_array($row) ? ($row['name'] ?? null) : null)
                ?? $this->normalizeMeditectValue($storage['name'] ?? null)
                ?? $this->normalizeMeditectValue($existing['name'] ?? null)
                ?? (string) $rawId;
            $count = (int) (
                (is_array($row) ? ($row['count'] ?? null) : null)
                ?? ($storage['productsCount'] ?? $storage['count'] ?? null)
                ?? ($existing['count'] ?? 0)
            );
            $synched = $resetProgress ? 0 : (int) ($existing['synched'] ?? 0);
            $normalized[] = [
                'id' => (string) $rawId,
                'name' => $name,
                'count' => $count,
                'synched' => $synched,
                'status' => $existing['status'] ?? 'pending',
            ];
        }

        return array_values($normalized);
    }

    private function persistImportConfig(int $entityId, array $selectedRayons, array $importState, ?array $baseConfig = null): ?array
    {
        $credential = ExtensionCredential::where('entity_id', $entityId)
            ->where('extension', 'meditect')
            ->first();

        if (!$credential) {
            return null;
        }

        $config = $baseConfig ?? ($credential->config ?? []);
        $config['selected_rayons'] = $selectedRayons;
        $config['import_state'] = array_merge($config['import_state'] ?? [], $importState);
        $credential->config = $config;
        $credential->save();

        return $config;
    }

    public function normalizeMeditectConfigForEntity(int $entityId, array $config): array
    {
        $selectedRayons = is_array($config['selected_rayons'] ?? null) ? $config['selected_rayons'] : [];
        unset($config['import_state']);
        if (empty($selectedRayons)) {
            return $config;
        }

        $credential = ExtensionCredential::where('entity_id', $entityId)
            ->where('extension', 'meditect')
            ->first();

        $existingConfig = $credential?->config ?? [];
        $config['selected_rayons'] = $this->buildSelectedRayonsConfig($entityId, $selectedRayons, $existingConfig, null, false);

        return $config;
    }

    public function getMeditectImportStatus(int $entityId): ?array
    {
        $credential = ExtensionCredential::where('entity_id', $entityId)
            ->where('extension', 'meditect')
            ->first();

        if (!$credential) {
            return null;
        }

        $session = MeditectImportSession::where('entity_id', $entityId)->first();
        $queueCounts = MeditectImportTask::where('entity_id', $entityId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'credential' => $credential,
            'config' => $credential->config ?? [],
            'session' => $session,
            'queue' => [
                'pending' => (int) ($queueCounts['pending'] ?? 0),
                'processing' => (int) ($queueCounts['processing'] ?? 0),
                'completed' => (int) ($queueCounts['completed'] ?? 0),
                'failed' => (int) ($queueCounts['failed'] ?? 0),
                'total' => array_sum(array_map('intval', $queueCounts)),
            ],
        ];
    }

    public function startMeditectImportSession(int $entityId, bool $withDebug = false): array
    {
        $debugTrace = [];
        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'start.request', 'Demande de démarrage de la synchro Meditect', [
                'entity_id' => $entityId,
            ]);
        }

        $credential = ExtensionCredential::where('entity_id', $entityId)
            ->where('extension', 'meditect')
            ->first();

        if (!$credential || !$credential->status) {
            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'start.blocked', 'Meditect n\'est pas actif pour cette boutique.');
            }

            return ['success' => false, 'message' => 'Meditect n\'est pas actif pour cette boutique.', 'debug_trace' => $debugTrace];
        }

        $existingSession = MeditectImportSession::where('entity_id', $entityId)->first();
        if ($existingSession && $existingSession->status === 'processing') {
            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'start.already_processing', 'Une session Meditect est déjà en cours.', [
                    'session_id' => $existingSession->id,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Import Meditect déjà en cours.',
                'data' => $existingSession->fresh(),
                'debug_trace' => $debugTrace,
            ];
        }

        $credentials = array_merge([
            'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
            'email' => 'pharmaciekhadijaba@gmail.com',
            'password' => 'meditect2025',
            'pin' => '202600',
        ], $credential->data ?? []);

        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'auth.start', 'Authentification Meditect en cours.');
        }

        $tokens = $this->authenticate($credentials);
        if (!$tokens) {
            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'auth.failed', 'Authentification Meditect échouée.');
            }

            return ['success' => false, 'message' => 'Authentification Meditect échouée.', 'debug_trace' => $debugTrace];
        }

        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'auth.success', 'Authentification Meditect réussie.');
        }

        MeditectImportTask::where('entity_id', $entityId)->delete();
        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'queue.cleared', 'Anciennes tâches Meditect supprimées.', [
                'entity_id' => $entityId,
            ]);
        }

        $config = $credential->config ?? [];
        $selectedRayons = is_array($config['selected_rayons'] ?? null) ? $config['selected_rayons'] : [];
        if (empty($selectedRayons)) {
            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'rayons.empty', 'Aucun rayon sélectionné.');
            }

            return ['success' => false, 'message' => 'Aucun rayon sélectionné.', 'debug_trace' => $debugTrace];
        }

        $storages = $this->getStorages($tokens) ?? [];
        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'storages.loaded', 'Rayons Meditect chargés depuis l\'API distante.', [
                'count' => count($storages),
            ]);
        }
        $selectedRayons = $this->buildSelectedRayonsConfig($entityId, $selectedRayons, $config, $storages, true);

        $session = MeditectImportSession::updateOrCreate(
            ['entity_id' => $entityId],
            [
                'extension_credential_id' => $credential->id,
                'status' => 'pending',
                'cursor' => null,
                'buffer' => [],
                'buffer_index' => 0,
                'batch_size' => 1000,
                'processed_count' => 0,
                'imported_count' => 0,
                'matched_count' => 0,
                'total_count' => 0,
                'selected_rayons_snapshot' => $selectedRayons,
                'meta' => ['stage' => 'pending'],
                'error_message' => null,
                'started_at' => now(),
                'paused_at' => null,
                'finished_at' => null,
                'last_run_at' => null,
            ]
        );
        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'session.created', 'Session d\'import Meditect créée.', [
                'session_id' => $session->id,
            ]);
        }

        $stage = $this->stageMeditectImportTasks($entityId, (int) $session->id, (int) $credential->id, $tokens, $selectedRayons);
        $totalTasks = (int) ($stage['total_tasks'] ?? 0);
        $rayonStats = is_array($stage['rayon_stats'] ?? null) ? $stage['rayon_stats'] : [];
        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'tasks.staged', 'Tâches Meditect préparées.', [
                'total_tasks' => $totalTasks,
                'rayons' => array_values(array_map(static fn (array $rayon) => [
                    'id' => $rayon['id'] ?? null,
                    'name' => $rayon['name'] ?? null,
                    'count' => $rayon['count'] ?? 0,
                ], $rayonStats)),
            ]);
        }

        if ($totalTasks <= 0) {
            $selectedRayons = array_map(static function (array $rayon) {
                $rayon['count'] = (int) ($rayon['count'] ?? 0);
                $rayon['synched'] = 0;
                $rayon['status'] = 'completed';
                $rayon['item_ids'] = [];
                return $rayon;
            }, $selectedRayons);

            $session->status = 'completed';
            $session->total_count = 0;
            $session->processed_count = 0;
            $session->imported_count = 0;
            $session->matched_count = 0;
            $session->selected_rayons_snapshot = $selectedRayons;
            $session->meta = array_merge($session->meta ?? [], [
                'stage' => 'completed',
                'staged_total' => 0,
                'rayon_stats' => $rayonStats,
            ]);
            $session->finished_at = now();
            $session->last_run_at = now();
            $session->save();

            $this->persistImportConfig($entityId, $selectedRayons, [
                'status' => 'completed',
                'cursor' => null,
                'processed' => 0,
                'imported' => 0,
                'matched' => 0,
                'total' => 0,
                'batch_size' => 10,
                'started_at' => now()->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'paused_at' => null,
                'error' => null,
                'staged_total' => 0,
                'pending_tasks' => 0,
                'processing_tasks' => 0,
                'completed_tasks' => 0,
                'failed_tasks' => 0,
            ], $config);

            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'tasks.none', 'Aucun produit à importer pour les rayons sélectionnés.');
            }

            return [
                'success' => true,
                'message' => 'Aucun produit à importer pour les rayons sélectionnés.',
                'data' => $session->fresh(),
                'debug_trace' => $debugTrace,
            ];
        }

        ShopItem::where('entity_id', $entityId)
            ->where('external_source', 'meditect')
            ->delete();

        $selectedRayons = array_map(static function (array $rayon) use ($rayonStats) {
            $rayonId = (string) ($rayon['id'] ?? '');
            $matchedCount = (int) ($rayonStats[$rayonId]['count'] ?? 0);
            $rayon['count'] = (int) ($rayon['count'] ?? $matchedCount ?? 0);
            $rayon['matched_count'] = $matchedCount;
            $rayon['synched'] = 0;
            $rayon['status'] = 'pending';
            return $rayon;
        }, $selectedRayons);

        $session->status = 'processing';
        $session->total_count = $totalTasks;
        $session->processed_count = 0;
        $session->imported_count = 0;
        $session->matched_count = $totalTasks;
        $session->selected_rayons_snapshot = $selectedRayons;
        $session->meta = array_merge($session->meta ?? [], [
            'stage' => 'completed',
            'staged_total' => $totalTasks,
            'rayon_stats' => $rayonStats,
        ]);
        $session->last_run_at = now();
        $session->save();

        $this->persistImportConfig($entityId, $selectedRayons, [
            'status' => 'processing',
            'cursor' => null,
            'processed' => 0,
            'imported' => 0,
            'matched' => $totalTasks,
            'total' => $totalTasks,
            'batch_size' => 10,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'paused_at' => null,
            'error' => null,
            'staged_total' => $totalTasks,
            'pending_tasks' => $totalTasks,
            'processing_tasks' => 0,
            'completed_tasks' => 0,
            'failed_tasks' => 0,
        ], $config);

        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'process.start', 'Traitement de la première vague de tâches Meditect.', [
                'batch_size' => $session->batch_size,
            ]);
        }

        $sessionResult = $this->processMeditectImportSession($session->fresh(), $withDebug);
        $processDebugTrace = is_array($sessionResult['debug_trace'] ?? null) ? $sessionResult['debug_trace'] : [];
        $debugTrace = array_merge($debugTrace, $processDebugTrace);
        $session = $sessionResult;

        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'process.done', 'Traitement initial Meditect terminé.', [
                'success' => (bool) ($session['success'] ?? true),
                'message' => $session['message'] ?? null,
            ]);
        }

        return [
            'success' => $session['success'] ?? true,
            'message' => $session['message'] ?? 'Import Meditect démarré.',
            'data' => $session['data'] ?? $session,
            'debug_trace' => $debugTrace,
        ];
    }

    public function pauseMeditectImportSession(int $entityId): array
    {
        $session = MeditectImportSession::where('entity_id', $entityId)->first();
        if (!$session) {
            return ['success' => false, 'message' => 'Aucune session active.'];
        }

        $session->status = 'paused';
        $session->paused_at = now();
        $session->save();

        return [
            'success' => true,
            'message' => 'Import Meditect mis en pause.',
            'data' => $session->fresh(),
        ];
    }

    public function resumeMeditectImportSession(int $entityId): array
    {
        $session = MeditectImportSession::where('entity_id', $entityId)->first();
        if (!$session) {
            return ['success' => false, 'message' => 'Aucune session trouvée.'];
        }

        $session->status = 'processing';
        $session->paused_at = null;
        $session->save();

        return [
            'success' => true,
            'message' => 'Import Meditect repris.',
            'data' => $session->fresh(),
        ];
    }

    public function cancelMeditectImportSession(int $entityId): array
    {
        $session = MeditectImportSession::where('entity_id', $entityId)->first();

        MeditectImportTask::where('entity_id', $entityId)->delete();

        if ($session) {
            $session->status = 'stopped';
            $session->finished_at = now();
            $session->save();
        }

        return [
            'success' => true,
            'message' => 'Import Meditect arrêté et réinitialisé.',
        ];
    }

    public function processPendingMeditectImportSessions(): array
    {
        $results = [];
        $sessions = MeditectImportSession::where('status', 'processing')->get();

        foreach ($sessions as $session) {
            $results[] = $this->processMeditectImportSession($session);
        }

        return $results;
    }

    public function processMeditectImportSession(MeditectImportSession $session, bool $withDebug = false): array
    {
        $debugTrace = [];
        $session = $session->fresh() ?? $session;
        $credential = ExtensionCredential::where('entity_id', $session->entity_id)
            ->where('extension', 'meditect')
            ->first();

        if (!$credential || !$credential->status) {
            $session->status = 'error';
            $session->error_message = 'Meditect inactif.';
            $session->save();

            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'process.blocked', 'Meditect inactif pendant le traitement.');
            }

            return ['success' => false, 'message' => 'Meditect inactif.', 'debug_trace' => $debugTrace];
        }

        if ($session->status === 'paused') {
            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'process.paused', 'Session Meditect en pause.');
            }

            return ['success' => true, 'message' => 'Session en pause.', 'data' => $session->fresh(), 'debug_trace' => $debugTrace];
        }

        $credentials = array_merge([
            'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
            'email' => 'pharmaciekhadijaba@gmail.com',
            'password' => 'meditect2025',
            'pin' => '202600',
        ], $credential->data ?? []);

        $tokens = $this->authenticate($credentials);
        if (!$tokens) {
            $session->status = 'error';
            $session->error_message = 'Authentification Meditect échouée.';
            $session->last_run_at = now();
            $session->save();

            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'process.auth_failed', 'Authentification Meditect échouée au moment du traitement.');
            }

            return ['success' => false, 'message' => 'Authentification Meditect échouée.', 'debug_trace' => $debugTrace];
        }

        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'process.auth_success', 'Authentification de traitement Meditect réussie.');
        }

        $config = $credential->config ?? [];
        $selectedRayons = is_array($session->selected_rayons_snapshot ?? null) && !empty($session->selected_rayons_snapshot)
            ? $session->selected_rayons_snapshot
            : (is_array($config['selected_rayons'] ?? null) ? $config['selected_rayons'] : []);

        if (empty($selectedRayons)) {
            $session->status = 'error';
            $session->error_message = 'Aucun rayon sélectionné.';
            $session->last_run_at = now();
            $session->save();

            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'process.rayons_empty', 'Aucun rayon sélectionné pendant le traitement.');
            }

            return ['success' => false, 'message' => 'Aucun rayon sélectionné.', 'debug_trace' => $debugTrace];
        }

        MeditectImportTask::where('entity_id', $session->entity_id)
            ->where('status', 'processing')
            ->whereNotNull('started_at')
            ->where('started_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'pending',
                'started_at' => null,
            ]);

        $batchSize = max(1, (int) ($session->batch_size ?: 1000));
        $pendingTasks = MeditectImportTask::where('entity_id', $session->entity_id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($pendingTasks->isEmpty()) {
            $pendingCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'pending')->count();
            $processingCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'processing')->count();
            $completedCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'completed')->count();
            $failedCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'failed')->count();

            if ($pendingCount === 0 && $processingCount === 0) {
                $selectedRayons = $this->markSelectedRayonsStatus($selectedRayons, true);
                $session->status = 'completed';
                $session->finished_at = now();
                $session->last_run_at = now();
                $session->meta = array_merge($session->meta ?? [], [
                    'last_processed' => 0,
                    'last_imported' => 0,
                    'last_failed' => 0,
                    'pending_tasks' => 0,
                    'processing_tasks' => 0,
                    'completed_tasks' => $completedCount,
                    'failed_tasks' => $failedCount,
                ]);
                $session->save();

                $this->persistImportConfig($session->entity_id, $selectedRayons, [
                    'status' => 'completed',
                    'cursor' => null,
                    'processed' => $session->processed_count,
                    'imported' => $session->imported_count,
                    'matched' => $session->matched_count,
                    'total' => $session->total_count,
                    'batch_size' => $session->batch_size,
                    'started_at' => optional($session->started_at)->toIso8601String(),
                    'finished_at' => optional($session->finished_at)->toIso8601String(),
                    'paused_at' => null,
                    'error' => null,
                    'staged_total' => $session->total_count,
                    'pending_tasks' => 0,
                    'processing_tasks' => 0,
                    'completed_tasks' => $completedCount,
                    'failed_tasks' => $failedCount,
                ], $config);

                if ($failedCount === 0) {
                    $this->cleanupObsoleteMeditectItems($session->entity_id);
                }

                if ($withDebug) {
                    $this->meditectDebug($debugTrace, 'process.complete', 'Import Meditect terminé sans tâches en attente.', [
                        'completed' => $completedCount,
                        'failed' => $failedCount,
                    ]);
                }

                return ['success' => true, 'message' => 'Import Meditect terminé.', 'data' => $session->fresh(), 'debug_trace' => $debugTrace];
            }

            $session->last_run_at = now();
            $session->save();

            $this->persistImportConfig($session->entity_id, $selectedRayons, [
                'status' => 'processing',
                'cursor' => null,
                'processed' => $session->processed_count,
                'imported' => $session->imported_count,
                'matched' => $session->matched_count,
                'total' => $session->total_count,
                'batch_size' => $session->batch_size,
                'started_at' => optional($session->started_at)->toIso8601String(),
                'finished_at' => optional($session->finished_at)->toIso8601String(),
                'paused_at' => null,
                'error' => null,
                'staged_total' => $session->total_count,
                'pending_tasks' => $pendingCount,
                'processing_tasks' => $processingCount,
                'completed_tasks' => $completedCount,
                'failed_tasks' => $failedCount,
            ], $config);

            if ($withDebug) {
                $this->meditectDebug($debugTrace, 'process.waiting', 'Import Meditect encore en cours, des tâches restent à traiter.', [
                    'pending' => $pendingCount,
                    'processing' => $processingCount,
                    'completed' => $completedCount,
                    'failed' => $failedCount,
                ]);
            }

            return ['success' => true, 'message' => 'Import Meditect en cours.', 'data' => $session->fresh(), 'debug_trace' => $debugTrace];
        }

        $processedThisRun = 0;
        $importedThisRun = 0;
        $failedThisRun = 0;

        Log::info("[MeditectImportSession] Traitement des tâches ({$session->entity_id}): batchSize={$batchSize}, pendingTasks=" . $pendingTasks->count());
        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'process.batch', 'Traitement d\'un lot de tâches Meditect.', [
                'batch_size' => $batchSize,
                'pending_tasks' => $pendingTasks->count(),
            ]);
        }

        foreach ($pendingTasks as $task) {
            try {
                $task->status = 'processing';
                $task->attempts = (int) ($task->attempts ?? 0) + 1;
                $task->started_at = $task->started_at ?? now();
                $task->save();

                $summary = is_array($task->summary ?? null) ? $task->summary : [];
                $details = $summary['product'] ?? $summary;
                $item = $this->upsertMeditectShopItem($session->entity_id, $summary, $details);

                if ($item) {
                    $importedThisRun++;
                    $session->imported_count++;
                    $selectedRayons = $this->registerSyncedItemOnRayonsByIds(
                        $selectedRayons,
                        $task->external_item_id,
                        is_array($task->rayon_ids ?? null) ? $task->rayon_ids : []
                    );
                }

                $task->details = !empty($details) ? $details : $summary;
                $task->shop_item_id = $item?->id;
                $task->status = 'completed';
                $task->processed_at = now();
                $task->error_message = null;
                $task->save();

                $processedThisRun++;
                $session->processed_count++;
                Log::info("[MeditectImportSession] Produit importé en BD locale ID={$item->id}, Name={$item->name}, ExternalId={$task->external_item_id}");
                if ($withDebug) {
                    $this->meditectDebug($debugTrace, 'process.task.completed', 'Produit Meditect importé en base locale.', [
                        'external_item_id' => $task->external_item_id,
                        'shop_item_id' => $item?->id,
                        'name' => $item?->name,
                    ]);
                }
            } catch (\Throwable $e) {
                $failedThisRun++;
                $processedThisRun++;
                $session->processed_count++;
                $task->status = 'failed';
                $task->error_message = $e->getMessage();
                $task->processed_at = now();
                $task->save();

                Log::warning('[MeditectService] Produit Meditect ignoré pendant l\'import', [
                    'entity_id' => $session->entity_id,
                    'product' => $task->external_item_id,
                    'message' => $e->getMessage(),
                ]);
                if ($withDebug) {
                    $this->meditectDebug($debugTrace, 'process.task.failed', 'Échec lors du traitement d\'un produit Meditect.', [
                        'external_item_id' => $task->external_item_id,
                        'error' => $e->getMessage(),
                    ]);
                }
                continue;
            }
        }

        $session->last_run_at = now();
        $pendingCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'pending')->count();
        $processingCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'processing')->count();
        $completedCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'completed')->count();
        $failedCount = MeditectImportTask::where('entity_id', $session->entity_id)->where('status', 'failed')->count();

        $session->meta = array_merge($session->meta ?? [], [
            'last_processed' => $processedThisRun,
            'last_imported' => $importedThisRun,
            'last_failed' => $failedThisRun,
            'pending_tasks' => $pendingCount,
            'processing_tasks' => $processingCount,
            'completed_tasks' => $completedCount,
            'failed_tasks' => $failedCount,
        ]);

        if ($pendingCount === 0 && $processingCount === 0) {
            $session->status = 'completed';
            $session->finished_at = now();
        } else {
            $session->status = 'processing';
        }

        $session->save();

        $selectedRayons = $this->markSelectedRayonsStatus($selectedRayons, $session->status === 'completed');
        $this->persistImportConfig($session->entity_id, $selectedRayons, [
            'status' => $session->status,
            'cursor' => null,
            'processed' => $session->processed_count,
            'imported' => $session->imported_count,
            'matched' => $session->matched_count,
            'total' => $session->total_count,
            'batch_size' => $session->batch_size,
            'started_at' => optional($session->started_at)->toIso8601String(),
            'finished_at' => optional($session->finished_at)->toIso8601String(),
            'paused_at' => null,
            'error' => null,
            'staged_total' => $session->total_count,
            'pending_tasks' => $pendingCount,
            'processing_tasks' => $processingCount,
            'completed_tasks' => $completedCount,
            'failed_tasks' => $failedCount,
        ], $config);

        if ($session->status === 'completed' && $failedCount === 0) {
            $this->cleanupObsoleteMeditectItems($session->entity_id);
        }

        if ($withDebug) {
            $this->meditectDebug($debugTrace, 'process.batch.done', 'Lot de tâches Meditect traité.', [
                'processed_this_run' => $processedThisRun,
                'imported_this_run' => $importedThisRun,
                'failed_this_run' => $failedThisRun,
                'session_status' => $session->status,
            ]);
        }

        return [
            'success' => true,
            'message' => $session->status === 'completed' ? 'Import Meditect terminé.' : 'Import Meditect en cours.',
            'data' => $session->fresh(),
            'debug_trace' => $debugTrace,
        ];
    }

    private function registerSyncedItemOnRayonsByIds(array $selectedRayons, string $productId, array $rayonIds): array
    {
        $selectedIds = array_flip(array_map(fn ($rayonId) => $this->normalizeMeditectKey($rayonId), $rayonIds));

        foreach ($selectedRayons as &$rayon) {
            $rayonIdKey = $this->normalizeMeditectKey($rayon['id'] ?? null);
            if ($rayonIdKey === '' || !isset($selectedIds[$rayonIdKey])) {
                continue;
            }

            $itemIds = array_values(array_unique(array_map('strval', is_array($rayon['item_ids'] ?? null) ? $rayon['item_ids'] : [])));
            if (!in_array($productId, $itemIds, true)) {
                $itemIds[] = $productId;
                $rayon['synched'] = (int) ($rayon['synched'] ?? 0) + 1;
                $rayon['item_ids'] = $itemIds;
            }
        }
        unset($rayon);

        return $selectedRayons;
    }

    private function cleanupObsoleteMeditectItems(int $entityId): void
    {
        $importedExternalIds = MeditectImportTask::where('entity_id', $entityId)
            ->where('status', 'completed')
            ->pluck('external_item_id')
            ->all();

        if (empty($importedExternalIds)) {
            return;
        }

        ShopItem::where('entity_id', $entityId)
            ->where('external_source', 'meditect')
            ->whereNotIn('external_item_id', $importedExternalIds)
            ->delete();
    }

    private function markSelectedRayonsStatus(array $selectedRayons, bool $isSessionCompleted = false): array
    {
        foreach ($selectedRayons as &$rayon) {
            $count = (int) ($rayon['count'] ?? 0);
            $synched = (int) ($rayon['synched'] ?? 0);
            if ($isSessionCompleted) {
                $rayon['status'] = 'completed';
                $rayon['synched'] = $count;
            } else {
                $rayon['status'] = $count > 0 && $synched >= $count ? 'completed' : 'processing';
            }
        }
        unset($rayon);

        return $selectedRayons;
    }

    /**
     * Récupérer les détails complets d'un produit (Référentiel, Catégorie, Fabricant/Brand, SPC) par son ID Meditect.
     * Endpoint API Meditect: GET /products/{productId}/details
     */
    public function getProductDetails(array|string $tokens, string $productId): ?array
    {
        $tokenList = is_array($tokens) ? array_filter([$tokens['idToken'] ?? null, $tokens['finalFirebaseToken'] ?? null, $tokens['customToken'] ?? null]) : [$tokens];

        foreach ($tokenList as $token) {
            try {
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])->get("https://api-315096745494.europe-west1.run.app/products/{$productId}/details");

                if ($res->successful()) {
                    return $res->json();
                }
            } catch (\Exception $e) {
                Log::error("[MeditectService] Erreur getProductDetails ({$productId}): {$e->getMessage()}");
            }
        }

        return null;
    }

    /**
     * Récupérer la liste des rayons (storages) depuis Meditect.
     */
    public function getStorages(array|string $tokens): ?array
    {
        $tokenList = is_array($tokens) ? array_filter([$tokens['idToken'] ?? null, $tokens['finalFirebaseToken'] ?? null, $tokens['customToken'] ?? null]) : [$tokens];

        foreach ($tokenList as $token) {
            try {
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])->get("https://api-315096745494.europe-west1.run.app/pharmacy-product-storages/");

                if ($res->successful() && !empty($res->json())) {
                    return $res->json();
                }
            } catch (\Exception $e) {
                Log::error("[MeditectService] Erreur getStorages direct: {$e->getMessage()}");
            }
        }

        // Stratégie de secours : Extraction dynamique depuis la liste des produits
        foreach ($tokenList as $token) {
            $products = $this->getAllProducts($token);
            if (!empty($products)) {
                $map = [];
                foreach ($products as $p) {
                    if (!empty($p['storages']) && is_array($p['storages'])) {
                        foreach ($p['storages'] as $st) {
                            if (!empty($st['id']) && !empty($st['name']) && !isset($map[$st['id']])) {
                                $map[$st['id']] = [
                                    'id' => $st['id'],
                                    'name' => $st['name'],
                                    'productsCount' => 1,
                                ];
                            } elseif (!empty($st['id']) && isset($map[$st['id']])) {
                                $map[$st['id']]['productsCount']++;
                            }
                        }
                    }
                    if (!empty($p['stockItems']) && is_array($p['stockItems'])) {
                        foreach ($p['stockItems'] as $si) {
                            if (!empty($si['storage']['id']) && !empty($si['storage']['name'])) {
                                $stId = $si['storage']['id'];
                                $stName = $si['storage']['name'];
                                if (!isset($map[$stId])) {
                                    $map[$stId] = [
                                        'id' => $stId,
                                        'name' => $stName,
                                        'productsCount' => 1,
                                    ];
                                } else {
                                    $map[$stId]['productsCount']++;
                                }
                            }
                        }
                    }
                }
                return array_values($map);
            }
        }

        return [];
    }

    /**
     * Enrichir un lot de produits Meditect non encore enrichis (is_enriched = false).
     */
    public function enrichPendingMeditectItems(int $batchSize = 30, bool $force = false, ?callable $logger = null): array
    {
        $query = ShopItem::query();

        if (!$force) {
            $query->where(function ($q) {
                $q->where('external_source', 'meditect')
                  ->orWhere('reference', 'LIKE', 'MED-%')
                  ->orWhereNotNull('external_item_id');
            })
            ->where(function ($q) {
                $q->where('is_enriched', false)
                  ->orWhere('needs_enrich_update', true);
            });
        } else {
            $query->where(function ($q) {
                $q->where('external_source', 'meditect')
                  ->orWhere('reference', 'LIKE', 'MED-%')
                  ->orWhereNotNull('external_item_id');
            });
        }

        $items = $query->limit($batchSize)->get();

        $stats = [
            'total' => $items->count(),
            'success' => 0,
            'failed' => 0,
            'ignored' => 0,
        ];

        if ($items->isEmpty()) {
            return $stats;
        }

        $enrichedCount = 0;

        foreach ($items as $item) {
            try {
                $credential = ExtensionCredential::where('entity_id', $item->entity_id)
                    ->where('extension', 'meditect')
                    ->first();

                $credentialData = $credential?->data ?? [];

                $credentials = array_merge([
                    'api_key' => 'AIzaSyB1E1Xsuda9MPItNw1hlRVrCuDhl5LFijk',
                    'email' => 'pharmaciekhadijaba@gmail.com',
                    'password' => 'meditect2025',
                    'pin' => '202600',
                ], array_filter($credentialData));

                $tokens = $this->authenticate($credentials);
                if (!$tokens) {
                    $stats['failed']++;
                    if ($logger) $logger("Article ID {$item->id} [{$item->name}] : Échec d'authentification Meditect", 'error');
                    continue;
                }

                $targetId = $item->external_item_id
                    ?? $item->external_meta['meditect_product_id'] ?? null
                    ?? $item->external_meta['product']['id'] ?? null
                    ?? $item->external_meta['productId'] ?? null;

                if (!$targetId) {
                    $stats['ignored']++;
                    if ($logger) $logger("Article ID {$item->id} [{$item->name}] : Ignoré (Aucun identifiant produit disponible)", 'warn');
                    continue;
                }

                $details = $this->getProductDetails($tokens, (string) $targetId);

                // Pause de 2 secondes entre chaque article pour préserver le serveur Meditect
                sleep(2);

                if (!$details) {
                    $item->update([
                        'is_enriched' => true,
                        'needs_enrich_update' => false,
                    ]);
                    $stats['ignored']++;
                    if ($logger) $logger("Article ID {$item->id} [{$item->name}] : Ignoré (Détails non trouvés sur API Meditect)", 'warn');
                    continue;
                }

                $entityId = $item->entity_id;
                $categoryLabel = $this->resolveCategoryLabel([], $details);
                $brandLabel = $this->resolveBrandLabel([], $details);

                $category = $this->findOrCreateCategory($entityId, $categoryLabel);
                $brand = $this->findOrCreateBrand($entityId, $brandLabel);

                $image = $this->resolveProductImage([], $details);
                $description = $this->resolveProductDescription([], $details);

                $updateData = [
                    'is_enriched' => ($category !== null || $brand !== null),
                    'needs_enrich_update' => false,
                ];

                if ($category) {
                    $updateData['category_id'] = $category->id;
                }

                if ($brand) {
                    $updateData['brand_id'] = $brand->id;
                }

                if ($image && empty($item->image)) {
                    $updateData['image'] = $image;
                }

                if ($description && empty($item->description)) {
                    $updateData['description'] = $description;
                }

                $item->update($updateData);
                $stats['success']++;

                if ($logger) {
                    $catName = $category?->name ?? 'Aucune';
                    $brandName = $brand?->name ?? 'Aucun';
                    $logger("Article ID {$item->id} [{$item->name}] -> Catégorie: {$catName} | Laboratoire: {$brandName}", 'info');
                }

                Log::info("[MeditectEnrich] Produit ID={$item->id} enrichi: Category=" . ($category?->name ?? 'N/A') . ", Brand=" . ($brand?->name ?? 'N/A'));
            } catch (\Throwable $e) {
                $stats['failed']++;
                if ($logger) $logger("Article ID {$item->id} [{$item->name}] : Erreur ({$e->getMessage()})", 'error');
                Log::error("[MeditectEnrich] Échec enrichissement produit ID={$item->id}: {$e->getMessage()}");
            }
        }

        return $stats;
    }
}
