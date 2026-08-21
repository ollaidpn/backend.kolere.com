<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\ShopPayment;

use App\Models\ShopPaymentLog;
use App\Models\ShopOrder;
use App\Services\FaykoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopOrderController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    private function normalizeGatewayMethod(?string $method): string
    {
        return match ($method) {
            'orange_money_senegal' => 'orange_money',
            'wave_senegal' => 'wave',
            default => 'wave',
        };
    }

    private function generatePaymentReference(): string
    {
        do {
            $reference = 'PAY-' . now()->format('ymdHis') . '-' . strtoupper(Str::random(6));
        } while (ShopPayment::where('reference', $reference)->exists());

        return $reference;
    }

    private function generatePaymentLogReference(): string
    {
        do {
            $reference = 'PLOG-' . now()->format('ymdHis') . '-' . strtoupper(Str::random(6));
        } while (ShopPaymentLog::where('reference', $reference)->exists());

        return $reference;
    }

    private function changeOrderStock(ShopOrder $order, int $direction): void
    {
        $items = is_array($order->items) ? $order->items : [];

        foreach ($items as $itemLine) {
            $itemId = (int) ($itemLine['id'] ?? 0);
            $quantity = (int) ($itemLine['quantity'] ?? 0);

            if ($itemId <= 0 || $quantity <= 0) {
                continue;
            }

            $item = \App\Models\ShopItem::where('entity_id', $order->entity_id)->find($itemId);
            if (!$item || $item->stock === null) {
                continue;
            }

            if ($direction < 0) {
                if ($item->stock > 0) {
                    $item->decrement('stock', min($quantity, $item->stock));
                }
                continue;
            }

            $item->increment('stock', $quantity);
        }
    }


    private function createAttemptLog(int $entityId, ShopOrder $order, array $clientInfos, float|int $amount, string $paymentMethod, ?string $paidBy): ShopPaymentLog
    {
        return ShopPaymentLog::create([
            'entity_id' => $entityId,
            'shop_order_id' => $order->id,
            'reference' => $this->generatePaymentLogReference(),
            'client_infos' => $clientInfos,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'paid_by' => $paidBy,
            'status' => 'pending',
        ]);
    }

    private function paymentDataForOrder(ShopOrder $order): array
    {
        return [
            'reference' => $order->reference,
            'status_payment' => $order->status_payment,
            'status_delivery' => $order->status_delivery,
            'status_order' => $order->status_order,
            'payment_method' => $order->payment_method,
            'paid_by' => $order->paid_by,
            'payment_reference' => $order->payment_reference,
            'payment_link' => $order->payment_link,
            'payment_qrcode_base64' => $order->payment_qrcode_base64,
            'payment_expires_at' => $order->payment_expires_at,
        ];
    }

    private function resolveOrderByReference(string $reference): ?ShopOrder
    {
        return ShopOrder::where('reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopOrder::query();

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('status_payment', 'like', "%{$search}%")
                        ->orWhere('status_delivery', 'like', "%{$search}%")
                        ->orWhere('status_order', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            }

            $orders = $query->orderByDesc('created_at')->get();

            return response()->json(['data' => $orders]);
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des commandes'], 500);
        }
    }

    public function show(Request $request, ShopOrder $order): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $order->entity_id === (int) $entityId, 404);
            }

            return response()->json(['data' => $order]);
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@show] Error', ['id' => $order->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }
    }

    public function update(Request $request, ShopOrder $order): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $order->entity_id === (int) $entityId, 404);
            }

            $validated = $request->validate([
                'status_payment' => 'required|in:pending,paid,refunded',
                'status_delivery' => 'required|in:pending,preparing,shipped,delivered,cancelled',
                'status_order' => 'required|in:pending,confirmed,processing,completed,cancelled',
            ]);

            $order->update($validated);

            return response()->json(['message' => 'Commande mise à jour', 'data' => $order]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@update] Error', ['id' => $order->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de la commande'], 500);
        }
    }

    public function storePublic(Request $request): JsonResponse
    {
        try {
            $entityId = $this->currentEntityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité introuvable'], 400);
            }

            $validated = $request->validate([
                'client_infos' => 'required|array',
                'client_infos.name' => 'required|string|max:255',
                'client_infos.ccphone' => 'required|string|max:10',
                'client_infos.phone' => 'required|string|max:20',
                'client_infos.email' => 'required|email|max:255',
                'client_infos.address' => 'nullable|string|max:500',
                'client_infos.city' => 'nullable|string|max:255',
                'payment_method' => 'required|string|in:online,recorded',
                'paid_by' => 'required_if:payment_method,online|nullable|string|in:wave_senegal,orange_money_senegal',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:shop_items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'promo_code' => 'nullable|string|max:255',
                'note' => 'nullable|string|max:1000',
            ]);


            $amount = 0;
            $orderItems = [];

            foreach ($validated['items'] as $cartLine) {
                $item = \App\Models\ShopItem::where('entity_id', $entityId)->find($cartLine['item_id']);
                if (!$item) {
                    return response()->json(['message' => 'L\'un des articles n\'est pas disponible pour cette boutique.'], 400);
                }

                if ($item->status !== 'active') {
                    return response()->json(['message' => "L'article {$item->name} n'est pas actif."], 400);
                }

                $price = $item->promo_price ?? $item->price;
                $lineTotal = $price * $cartLine['quantity'];
                $amount += $lineTotal;

                // Soustraction du stock s'il est supérieur à 0 (sans bloquer la commande si stock à 0)
                if ($item->stock !== null && $item->stock > 0) {
                    $item->decrement('stock', min($cartLine['quantity'], $item->stock));
                }



                $orderItems[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'image' => $item->image,
                    'price' => (float)$price,
                    'quantity' => $cartLine['quantity'],
                    'total' => (float)$lineTotal,
                ];
            }

            $promoCodeStr = isset($validated['promo_code']) ? strtoupper(trim((string) $validated['promo_code'])) : null;
            $discount = 0;

            if ($promoCodeStr) {
                $promo = \App\Models\ShopPromoCode::where('entity_id', $entityId)
                    ->where('code', $promoCodeStr)
                    ->where('status', 'active')
                    ->first();

                if ($promo && (!$promo->valid_until || now()->lte($promo->valid_until)) && ($promo->max_uses == 0 || $promo->uses < $promo->max_uses)) {
                    if ($promo->min_amount == 0 || $amount >= $promo->min_amount) {
                        if ($promo->type === 'percentage') {
                            $discount = ($amount * (float)$promo->value) / 100;
                        } else {
                            $discount = min((float)$promo->value, $amount);
                        }
                        $promo->increment('uses');
                    }
                }
            }

            $totalTtc = max(0, $amount - $discount);
            $paymentMethod = $validated['payment_method'];
            $gatewayMethod = $paymentMethod === 'online'
                ? $this->normalizeGatewayMethod($validated['paid_by'] ?? null)
                : 'cash_on_delivery';
            $paymentReference = $this->generatePaymentReference();

            $order = DB::transaction(function () use ($entityId, $validated, $amount, $discount, $totalTtc, $orderItems, $paymentMethod, $gatewayMethod, $paymentReference) {
                return ShopOrder::create([
                    'entity_id' => $entityId,
                    'amount' => $amount,
                    'discount' => $discount,
                    'total' => $totalTtc,
                    'client_infos' => $validated['client_infos'],
                    'payment_method' => $paymentMethod,
                    'paid_by' => $gatewayMethod,
                    'payment_reference' => $paymentReference,
                    'payment_link' => null,
                    'payment_qrcode_base64' => null,
                    'payment_expires_at' => null,
                    'status_payment' => 'pending',
                    'status_delivery' => 'pending',
                    'status_order' => 'pending',
                    'items' => $orderItems,
                    'note' => $validated['note'] ?? null,
                ]);
            });

            // Envoi des notifications par mail (client & admin)
            try {
                $entity = Entity::find($entityId);

                // 1. Mail au client
                $clientEmail = data_get($validated['client_infos'], 'email');
                if ($clientEmail && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                    \Illuminate\Support\Facades\Mail::to($clientEmail)
                        ->send(new \App\Mail\OrderConfirmationMail($order, $entity, 'client'));
                }

                // 2. Mail à l'administrateur / boutique
                $adminEmail = $entity?->email ?: config('mail.from.address');
                if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    \Illuminate\Support\Facades\Mail::to($adminEmail)
                        ->send(new \App\Mail\OrderConfirmationMail($order, $entity, 'admin'));
                }
            } catch (\Throwable $mailException) {
                Log::error('[ShopOrderController@storePublic] Erreur d\'envoi d\'email', [
                    'order_id' => $order->id,
                    'error' => $mailException->getMessage(),
                ]);
            }



            if ($paymentMethod === 'online') {
                $paymentLog = $this->createAttemptLog($entityId, $order, $validated['client_infos'], $amount, $paymentMethod, $gatewayMethod);

                try {
                    $entity = Entity::find($entityId);
                    $pubKey = $entity?->fayko_public_key ?: env('FAYKO_PUBLIC_KEY');
                    $secKey = $entity?->fayko_secret_key ?: env('FAYKO_SECRET_KEY');
                    $webKey = $entity?->fayko_webhook_key ?: env('FAYKO_WEBHOOK_KEY');
                    $faykoMode = $entity?->fayko_mode ?: env('FAYKO_MODE', 'live');

                    // Si mode dev, envoyer 10 FCFA à Fayko pour les tests (minimum requis par Fayko)
                    $faykoAmount = ($faykoMode === 'dev') ? 10 : $amount;

                    // Construction de l'URL publique du frontend (sans localhost)
                    $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://senepharma.com'));
                    if (str_contains($frontendUrl, 'localhost')) {
                        $frontendUrl = 'https://senepharma.com';
                    }
                    $statusUrl = rtrim($frontendUrl, '/') . '/order-status/' . $order->reference;


                    $paymentGateway = (new FaykoPaymentService($pubKey, $secKey, $webKey))->createCheckout([
                        'client_name' => $validated['client_infos']['name'],
                        'name' => 'Commande boutique ' . $order->reference,
                        'description' => 'Paiement de commande en ligne' . ($faykoMode === 'dev' ? ' (Mode Test 10F)' : ''),
                        'amount' => $faykoAmount,
                        'qty' => array_sum(array_map(static fn ($line) => (int) $line['quantity'], $validated['items'])),
                        'paid_by' => $gatewayMethod,
                        'ccphone' => $validated['client_infos']['ccphone'],
                        'phone' => $validated['client_infos']['phone'],
                        'error_url' => $statusUrl,
                        'success_url' => $statusUrl,
                        'extra_data' => [
                            'origin' => 'kolere-shop',
                            'entity_id' => $entityId,
                            'order_reference' => $order->reference,
                            'payment_reference' => $paymentReference,
                            'payment_log_reference' => $paymentLog->reference,
                        ],
                    ]);

                } catch (\Throwable $e) {
                    $paymentLog->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);

                    DB::transaction(function () use ($order, $validated) {
                        $this->changeOrderStock($order, 1);
                        $order->update([
                            'status_order' => 'cancelled',
                            'note' => trim(($validated['note'] ?? '') . ' [payment_failed]'),
                        ]);
                    });

                    return response()->json(['message' => 'Paiement en ligne indisponible : ' . $e->getMessage()], 502);
                }


                $order->update([
                    'payment_link' => $paymentGateway['payment_link'],
                    'payment_qrcode_base64' => $paymentGateway['payment_qrcode_base64'],
                    'payment_expires_at' => $paymentGateway['when_expires'],
                ]);

                $paymentLog->update([
                    'status' => 'processing',
                    'gateway_reference' => $paymentGateway['gateway_reference'],
                    'gateway_payload' => $paymentGateway['raw'],
                ]);

                return response()->json([
                    'message' => 'Commande créée, paiement en attente',
                    'data' => $order->fresh(),
                    'payment_link' => $paymentGateway['payment_link'],
                    'payment_qrcode_base64' => $paymentGateway['payment_qrcode_base64'],
                    'when_expires' => $paymentGateway['when_expires'],
                ], 201);
            }

            return response()->json([
                'message' => 'Commande passée avec succès',
                'data' => $order->fresh(),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@storePublic] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de la commande : ' . $e->getMessage()], 500);
        }
    }


    public function checkPublic(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_reference' => 'required|string',
            ]);

            $order = $this->resolveOrderByReference($validated['order_reference']);
            if (!$order) {
                return response()->json(['message' => 'Commande introuvable'], 404);
            }

            return response()->json([
                'message' => 'Statut de commande',
                'data' => $this->paymentDataForOrder($order->fresh()),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@checkPublic] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la vérification du paiement'], 500);
        }
    }

    public function cancelPublic(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_reference' => 'required|string',
            ]);

            $order = $this->resolveOrderByReference($validated['order_reference']);
            if (!$order) {
                return response()->json(['message' => 'Commande introuvable'], 404);
            }

            if ($order->status_payment === 'paid') {
                return response()->json(['message' => 'Impossible d\'annuler une commande déjà payée'], 400);
            }

            DB::transaction(function () use ($order) {
                $this->changeOrderStock($order, 1);
                $order->update([
                    'status_order' => 'cancelled',
                    'note' => trim(($order->note ?? '') . ' [annulé_par_client]'),
                ]);
            });

            return response()->json([
                'message' => 'Commande annulée avec succès',
                'data' => $order->fresh(),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@cancelPublic] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'annulation de la commande'], 500);
        }
    }

    public function webhookFayko(Request $request): JsonResponse

    {
        try {
            $payload = $request->all();
            $type = $payload['type'] ?? 'checkout';
            $event = $payload['event'] ?? '';

            Log::info('[ShopOrderController@webhookFayko] Webhook reçu', [
                'type' => $type,
                'event' => $event,
                'payload' => $payload,
            ]);

            // Webhook Payout (Retraits)
            if ($type === 'payout' || str_starts_with($event, 'payout.')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook payout recu.',
                ]);
            }

            // Webhook Checkout (Commandes & Paiements)
            $extraData = $payload['extra_data'] ?? null;
            if (is_string($extraData)) {
                $decoded = json_decode($extraData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $extraData = $decoded;
                }
            }

            $orderReference = data_get($payload, 'order_reference')
                ?? data_get($payload, 'transaction_id')
                ?? data_get($payload, 'reference')
                ?? data_get($payload, 'payment_reference')
                ?? data_get($extraData, 'order_reference')
                ?? data_get($extraData, 'payment_reference');

            if (!$orderReference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Référence de commande introuvable',
                ], 404);
            }

            $order = $this->resolveOrderByReference((string) $orderReference);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande introuvable',
                ], 404);
            }

            $paymentLog = ShopPaymentLog::where('shop_order_id', $order->id)->latest()->first();
            if (!$paymentLog) {
                $paymentLog = ShopPaymentLog::create([
                    'entity_id' => $order->entity_id,
                    'shop_order_id' => $order->id,
                    'reference' => $this->generatePaymentLogReference(),
                    'client_infos' => $order->client_infos,
                    'amount' => $order->total,
                    'payment_method' => $order->payment_method ?? 'online',
                    'paid_by' => $order->paid_by,
                    'status' => 'pending',
                    'gateway_payload' => $payload,
                ]);
            }

            $status = strtolower((string) (data_get($payload, 'status') ?? data_get($payload, 'payment_status') ?? ''));
            $isPaid = $event === 'checkout.succeeded'
                || in_array($status, ['paid', 'success', 'completed', 'confirmed'], true)
                || data_get($payload, 'paid') === true;

            $isFailedOrExpired = $event === 'checkout.failed'
                || $event === 'checkout.expired'
                || in_array($status, ['failed', 'cancelled', 'canceled', 'expired'], true);

            $gatewayReference = data_get($payload, 'transaction_id')
                ?? data_get($payload, 'payment_reference')
                ?? data_get($payload, 'reference')
                ?? $paymentLog?->gateway_reference;

            return DB::transaction(function () use ($payload, $order, $paymentLog, $status, $gatewayReference, $isPaid, $isFailedOrExpired) {
                if ($isPaid) {
                    if ($paymentLog) {
                        $paymentLog->update([
                            'status' => 'paid',
                            'gateway_reference' => $gatewayReference,
                            'gateway_payload' => $payload,
                        ]);
                    }

                    $payment = ShopPayment::firstOrCreate(
                        ['shop_order_id' => $order->id],
                        [
                            'entity_id' => $order->entity_id,
                            'reference' => $order->payment_reference ?: $this->generatePaymentReference(),
                            'client_infos' => $order->client_infos,
                            'amount' => $order->total,
                            'method' => $order->payment_method ?? 'online',
                            'paid_by' => $order->paid_by,
                            'status' => 'paid',
                            'gateway_reference' => $gatewayReference,
                        ]
                    );

                    $payment->update([
                        'status' => 'paid',
                        'gateway_reference' => $gatewayReference,
                    ]);

                    $order->update([
                        'status_payment' => 'paid',
                        'status_order' => in_array($order->status_order, ['pending', 'confirmed'], true) ? 'confirmed' : $order->status_order,
                        'payment_reference' => $gatewayReference ?: $order->payment_reference,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Webhook checkout recu.',
                    ]);
                }

                if ($isFailedOrExpired) {
                    if ($paymentLog) {
                        $paymentLog->update([
                            'status' => 'failed',
                            'gateway_reference' => $gatewayReference,
                            'gateway_payload' => $payload,
                            'error_message' => data_get($payload, 'error_message') ?? data_get($payload, 'message'),
                        ]);
                    }

                    if ($order->status_payment !== 'paid' && $order->status_order !== 'cancelled') {
                        $this->changeOrderStock($order, 1);
                        $order->update([
                            'status_order' => 'cancelled',
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook checkout recu.',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@webhookFayko] Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur webhook',
            ], 500);
        }
    }

}
