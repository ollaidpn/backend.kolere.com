<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Entity;
use App\Models\Admin;
use App\Models\Manager;
use App\Models\Card;
use App\Models\CardType;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\ShopMailFromResolver;

class AuthController extends Controller
{
    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function buildPhoneValue(?string $ccphone, ?string $phone): ?string
    {
        $ccphone = $this->normalizeOptionalString($ccphone);
        $phone = $this->normalizeOptionalString($phone);

        if ($phone === null) {
            return null;
        }

        return $ccphone ? trim($ccphone . ' ' . $phone) : $phone;
    }

    public function registerClient(Request $request): JsonResponse
    {
        Log::info('[AuthController@registerClient] Attempt', ['email' => $request->email]);

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string|max:30',
                'address' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $this->normalizeOptionalString($request->phone),
                'address' => $this->normalizeOptionalString($request->address),
            ]);

            $token = $user->createToken('client-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user->makeHidden(['password']),
                'role' => 'client',
            ], 201);
        } catch (\Exception $e) {
            Log::error('[AuthController@registerClient] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function requestClientRegistrationOtp(Request $request): JsonResponse
    {
        Log::info('[AuthController@requestClientRegistrationOtp] Attempt', ['email' => $request->email]);

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'ccphone' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:30',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = mb_strtolower(trim((string) $request->input('email')));
            $otp = (string) random_int(100000, 999999);
            $cacheKey = 'client_register_otp:' . $email;

            Cache::put($cacheKey, [
                'name' => trim((string) $request->input('name')),
                'email' => $email,
                'ccphone' => $this->normalizeOptionalString($request->input('ccphone')),
                'phone' => $this->normalizeOptionalString($request->input('phone')),
                'otp' => $otp,
            ], now()->addMinutes(15));

            try {
                Mail::raw(
                    "Votre code OTP d'inscription est : {$otp}\nCe code expire dans 15 minutes.",
                    function ($message) use ($email, $request) {
                        $message->to($email)->subject("Code OTP d'inscription");
                        app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($message) {
                            $message->from($address, $name);
                        }, null, $request);
                    }
                );
            } catch (\Throwable $mailError) {
                Log::warning('[AuthController@requestClientRegistrationOtp] Mail send failed', [
                    'message' => $mailError->getMessage(),
                ]);
            }

            return response()->json(['message' => 'Code OTP envoyé par email']);
        } catch (\Exception $e) {
            Log::error('[AuthController@requestClientRegistrationOtp] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'envoi du code OTP'], 500);
        }
    }

    public function verifyClientRegistrationOtp(Request $request): JsonResponse
    {
        Log::info('[AuthController@verifyClientRegistrationOtp] Attempt', ['email' => $request->email]);

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'ccphone' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:30',
                'otp' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = mb_strtolower(trim((string) $request->input('email')));
            $cacheKey = 'client_register_otp:' . $email;
            $pending = Cache::get($cacheKey);

            if (!$pending || !is_array($pending)) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            if (!hash_equals((string) ($pending['otp'] ?? ''), (string) $request->input('otp'))) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            $requestName = trim((string) $request->input('name'));
            $requestCcphone = $this->normalizeOptionalString($request->input('ccphone'));
            $requestPhone = $this->normalizeOptionalString($request->input('phone'));

            if (
                trim((string) ($pending['name'] ?? '')) !== $requestName ||
                mb_strtolower(trim((string) ($pending['email'] ?? ''))) !== $email ||
                $this->normalizeOptionalString($pending['ccphone'] ?? null) !== $requestCcphone ||
                $this->normalizeOptionalString($pending['phone'] ?? null) !== $requestPhone
            ) {
                return response()->json(['message' => 'Les données d\'inscription ont changé. Recommencez le processus.'], 400);
            }

            return response()->json([
                'message' => 'Code OTP validé',
                'status' => 'otp_verified',
            ]);
        } catch (\Exception $e) {
            Log::error('[AuthController@verifyClientRegistrationOtp] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la vérification du code OTP'], 500);
        }
    }

    public function confirmClientRegistration(Request $request): JsonResponse
    {
        Log::info('[AuthController@confirmClientRegistration] Attempt', ['email' => $request->email]);

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'ccphone' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:30',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = mb_strtolower(trim((string) $request->input('email')));
            $cacheKey = 'client_register_otp:' . $email;
            $pending = Cache::get($cacheKey);

            if (!$pending || !is_array($pending)) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            if (!hash_equals((string) ($pending['otp'] ?? ''), (string) $request->input('otp'))) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            $requestName = trim((string) $request->input('name'));
            $requestCcphone = $this->normalizeOptionalString($request->input('ccphone'));
            $requestPhone = $this->normalizeOptionalString($request->input('phone'));

            if (
                trim((string) ($pending['name'] ?? '')) !== $requestName ||
                mb_strtolower(trim((string) ($pending['email'] ?? ''))) !== $email ||
                $this->normalizeOptionalString($pending['ccphone'] ?? null) !== $requestCcphone ||
                $this->normalizeOptionalString($pending['phone'] ?? null) !== $requestPhone
            ) {
                return response()->json(['message' => 'Les données d\'inscription ont changé. Recommencez le processus.'], 400);
            }

            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return response()->json(['message' => 'Cet email est déjà utilisé'], 422);
            }

            $user = User::create([
                'name' => $requestName,
                'email' => $email,
                'password' => Hash::make($request->input('password')),
                'phone' => $this->buildPhoneValue($requestCcphone, $requestPhone),
            ]);

            Cache::forget($cacheKey);

            $token = $user->createToken('client-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user->makeHidden(['password']),
                'role' => 'client',
            ], 201);
        } catch (\Exception $e) {
            Log::error('[AuthController@confirmClientRegistration] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du compte'], 500);
        }
    }

    public function loginClient(Request $request): JsonResponse
    {
        Log::info('[AuthController@loginClient] Attempt', ['email' => $request->email]);
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'entity_reference' => 'nullable|string|max:255',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('[AuthController@loginClient] Invalid credentials', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $entity = null;
            if ($request->filled('entity_reference')) {
                $entityReference = mb_strtolower(trim((string) $request->input('entity_reference')));
                $entity = Entity::with('domain')->whereRaw('LOWER(reference) = ?', [$entityReference])->first();

                if (!$entity) {
                    throw ValidationException::withMessages([
                        'entity_reference' => ['La boutique demandée est introuvable.'],
                    ]);
                }

                $hasEntityCard = $user->cards()
                    ->where('entity_id', $entity->id)
                    ->where('status', 'active')
                    ->exists();

                if (!$hasEntityCard) {
                    return response()->json([
                        'status' => 'need_access',
                        'message' => 'Vos identifiants sont corrects mais vous n\'avez pas de carte de fidélité active pour cette boutique.',
                        'role' => 'client',
                        'user' => $user->makeHidden(['password']),
                        'entity_reference' => $entity->reference,
                        'entity' => [
                            'id' => $entity->id,
                            'reference' => $entity->reference,
                            'subdomain' => $entity->subdomain,
                            'website_status' => $entity->website_status,
                            'name' => $entity->name,
                            'logo' => $entity->logo,
                            'logo_url' => $entity->logo ? (str_starts_with($entity->logo, 'http') ? $entity->logo : (new FileUploadService())->getUrl($entity->logo)) : null,
                            'primary_color' => $entity->primary_color,
                            'secondary_color' => $entity->secondary_color,
                            'web_slider' => $entity->web_slider,
                            'address' => $entity->address,
                            'town' => $entity->town,
                            'country' => $entity->country,
                            'email' => $entity->email,
                            'ccphone' => $entity->ccphone,
                            'phone' => $entity->phone,
                            'domain' => $entity->domain,
                        ],
                    ], 409);
                }
            }

            $token = $user->createToken('client-token')->plainTextToken;
            Log::info('[AuthController@loginClient] Success', ['user_id' => $user->id]);

            return response()->json([
                'token' => $token,
                'user' => $user->makeHidden(['password']),
                'role' => 'client',
                'status' => 'authenticated',
                'entity_reference' => $entity?->reference ?? $user->card?->entity?->reference,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@loginClient] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function claimClientCard(Request $request): JsonResponse
    {
        Log::info('[AuthController@claimClientCard] Attempt', ['email' => $request->email]);

        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'entity_reference' => 'required|string|max:255',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $entityReference = mb_strtolower(trim((string) $request->input('entity_reference')));
            $entity = Entity::with('domain')->whereRaw('LOWER(reference) = ?', [$entityReference])->first();

            if (!$entity) {
                throw ValidationException::withMessages([
                    'entity_reference' => ['La boutique demandée est introuvable.'],
                ]);
            }

            $existingCard = $user->cards()
                ->where('entity_id', $entity->id)
                ->where('status', 'active')
                ->first();

            if (!$existingCard) {
                $cardType = CardType::where('status', 'active')->orderBy('id')->first();

                if (!$cardType) {
                    return response()->json(['message' => 'Aucun type de carte actif disponible.'], 422);
                }

                $existingCard = DB::transaction(function () use ($user, $entity, $cardType) {
                    return Card::create([
                        'user_id' => $user->id,
                        'entity_id' => $entity->id,
                        'card_type_id' => $cardType->id,
                        'status' => 'active',
                        'credit' => 0,
                    ]);
                });
            }

            $token = $user->createToken('client-token')->plainTextToken;

            return response()->json([
                'status' => 'authenticated',
                'token' => $token,
                'user' => $user->makeHidden(['password']),
                'role' => 'client',
                'entity_reference' => $entity->reference,
                'entity' => [
                    'id' => $entity->id,
                    'reference' => $entity->reference,
                    'subdomain' => $entity->subdomain,
                    'website_status' => $entity->website_status,
                    'name' => $entity->name,
                    'logo' => $entity->logo,
                    'logo_url' => $entity->logo ? (str_starts_with($entity->logo, 'http') ? $entity->logo : (new FileUploadService())->getUrl($entity->logo)) : null,
                    'primary_color' => $entity->primary_color,
                    'secondary_color' => $entity->secondary_color,
                    'web_slider' => $entity->web_slider,
                    'address' => $entity->address,
                    'town' => $entity->town,
                    'country' => $entity->country,
                    'email' => $entity->email,
                    'ccphone' => $entity->ccphone,
                    'phone' => $entity->phone,
                    'domain' => $entity->domain,
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@claimClientCard] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function loginManager(Request $request): JsonResponse
    {
        Log::info('[AuthController@loginManager] Attempt', ['email' => $request->email]);
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'entity_reference' => 'nullable|string|max:255',
            ]);

            $manager = Manager::where('email', $request->email)->first();

            if (!$manager || !Hash::check($request->password, $manager->password)) {
                Log::warning('[AuthController@loginManager] Invalid credentials', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $entity = null;
            if ($request->filled('entity_reference')) {
                $entityReference = mb_strtolower(trim((string) $request->input('entity_reference')));
                $entity = Entity::whereRaw('LOWER(reference) = ?', [$entityReference])->first();

                if (!$entity) {
                    throw ValidationException::withMessages([
                        'entity_reference' => ['La boutique demandée est introuvable.'],
                    ]);
                }

                $linkedEntity = $manager->currentLink()->with('entity')->first()?->entity;
                if (!$linkedEntity || $linkedEntity->id !== $entity->id) {
                    throw ValidationException::withMessages([
                        'entity_reference' => ['Ce manager n\'est pas lié à cette boutique.'],
                    ]);
                }
            }

            $token = $manager->createToken('manager-token')->plainTextToken;
            Log::info('[AuthController@loginManager] Success', ['manager_id' => $manager->id]);

            return response()->json([
                'token' => $token,
                'user' => $manager->makeHidden(['password']),
                'role' => 'manager',
                'entity_reference' => $entity?->reference ?? $manager->currentLink()->with('entity')->first()?->entity?->reference,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@loginManager] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function loginAdmin(Request $request): JsonResponse
    {
        Log::info('[AuthController@loginAdmin] Attempt', ['email' => $request->email]);
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                Log::warning('[AuthController@loginAdmin] Invalid credentials', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            $token = $admin->createToken('admin-token')->plainTextToken;
            Log::info('[AuthController@loginAdmin] Success', ['admin_id' => $admin->id]);

            return response()->json([
                'token' => $token,
                'user' => $admin->makeHidden(['password']),
                'role' => 'admin',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AuthController@loginAdmin] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function requestClientPasswordResetOtp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = mb_strtolower(trim((string) $request->input('email')));
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user) {
                return response()->json(['message' => 'Si le compte existe, un code OTP a été envoyé par email.']);
            }

            $otp = (string) random_int(100000, 999999);
            $cacheKey = 'client_reset_otp:' . $user->id;

            Cache::put($cacheKey, $otp, now()->addMinutes(10));

            try {
                Mail::raw(
                    "Votre code OTP de réinitialisation est : {$otp}\nCe code expire dans 10 minutes.",
                    function ($message) use ($user, $request) {
                        $message->to($user->email)->subject('Code OTP de réinitialisation');
                        app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($message) {
                            $message->from($address, $name);
                        }, null, $request);
                    }
                );
            } catch (\Throwable $mailError) {
                Log::warning('[AuthController@requestClientPasswordResetOtp] Mail send failed', [
                    'message' => $mailError->getMessage(),
                ]);
            }

            return response()->json(['message' => 'Si le compte existe, un code OTP a été envoyé par email.']);
        } catch (\Exception $e) {
            Log::error('[AuthController@requestClientPasswordResetOtp] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'envoi du code OTP'], 500);
        }
    }

    public function resetClientPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = mb_strtolower(trim((string) $request->input('email')));
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            $cacheKey = 'client_reset_otp:' . $user->id;
            $storedOtp = Cache::get($cacheKey);

            if (!$storedOtp || !hash_equals((string) $storedOtp, (string) $request->input('otp'))) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            $user->update([
                'password' => Hash::make($request->input('password')),
            ]);

            $user->tokens()->delete();
            Cache::forget($cacheKey);

            return response()->json(['message' => 'Mot de passe réinitialisé avec succès']);
        } catch (\Exception $e) {
            Log::error('[AuthController@resetClientPassword] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la réinitialisation du mot de passe'], 500);
        }
    }

    public function requestPhoneOtp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ccphone' => 'required|string',
                'phone' => 'required|string|min:9|max:9',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Le numéro de téléphone doit comporter exactement 9 chiffres.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $ccphone = trim((string) $request->input('ccphone', '+221'));
            $rawPhone = trim((string) $request->input('phone'));
            $fullPhone = $ccphone . preg_replace('/^\+221/', '', $rawPhone);
            $cleanDigits = preg_replace('/\D/', '', $rawPhone);

            // Vérifier existence dans Manager & User
            $hasManager = Manager::where('phone', 'LIKE', "%{$cleanDigits}")
                ->orWhere('phone', $fullPhone)
                ->exists();

            $hasUser = User::where('phone', 'LIKE', "%{$cleanDigits}")
                ->orWhere('phone', $fullPhone)
                ->exists();

            if (!$hasManager && !$hasUser) {
                return response()->json(['message' => 'Aucun compte associé à ce numéro de téléphone.'], 404);
            }

            $otp = (string) random_int(100000, 999999);
            $cacheKey = 'phone_login_otp:' . $cleanDigits;

            Cache::put($cacheKey, [
                'ccphone' => $ccphone,
                'phone' => $rawPhone,
                'full_phone' => $fullPhone,
                'otp' => $otp,
                'has_manager' => $hasManager,
                'has_user' => $hasUser,
            ], now()->addMinutes(10));

            // Envoi SMS via Diotko
            try {
                $pubKey = env('DIOTKO_SMS_PUBLIC_KEY');
                $secKey = env('DIOTKO_SMS_SECRET_KEY');
                if ($pubKey && $secKey) {
                    $smsService = new \App\Services\NotificationsService($pubKey, $secKey);
                    $smsService->sendSmsNow([$fullPhone], "Votre code de connexion Kolere est : {$otp}");
                }
            } catch (\Throwable $sErr) {
                Log::warning('[AuthController@requestPhoneOtp] SMS fail', ['error' => $sErr->getMessage()]);
            }

            return response()->json([
                'message' => 'Code OTP envoyé par SMS',
                'otp_debug' => config('app.debug') ? $otp : null,
            ]);
        } catch (\Exception $e) {
            Log::error('[AuthController@requestPhoneOtp] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'envoi du code OTP'], 500);
        }
    }

    public function verifyPhoneOtp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string',
                'otp' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Code OTP invalide'], 422);
            }

            $rawPhone = trim((string) $request->input('phone'));
            $cleanDigits = preg_replace('/\D/', '', $rawPhone);
            $cacheKey = 'phone_login_otp:' . $cleanDigits;

            $pending = Cache::get($cacheKey);
            if (!$pending || !hash_equals((string) ($pending['otp'] ?? ''), (string) $request->input('otp'))) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
            }

            $hasManager = (bool) ($pending['has_manager'] ?? false);
            $hasUser = (bool) ($pending['has_user'] ?? false);

            if ($hasManager && $hasUser) {
                return response()->json([
                    'status' => 'multiple_accounts',
                    'message' => 'Votre numéro est lié à un compte Espace Backoffice (Gestionnaire) et un compte Espace Client.',
                    'accounts' => [
                        ['role' => 'manager', 'title' => 'Espace Backoffice', 'description' => 'Gérer votre boutique, clients et ventes'],
                        ['role' => 'client', 'title' => 'Espace Client', 'description' => 'Consulter vos points et carte de fidélité'],
                    ],
                ]);
            }

            $selectedRole = $hasManager ? 'manager' : 'client';
            return $this->authenticatePhoneUser($cleanDigits, $pending['full_phone'], $selectedRole);
        } catch (\Exception $e) {
            Log::error('[AuthController@verifyPhoneOtp] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur de vérification OTP'], 500);
        }
    }

    public function selectPhoneAccount(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string',
                'role' => 'required|in:manager,client',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Sélection invalide'], 422);
            }

            $rawPhone = trim((string) $request->input('phone'));
            $cleanDigits = preg_replace('/\D/', '', $rawPhone);
            $selectedRole = $request->input('role');
            $fullPhone = '+221' . $cleanDigits;

            return $this->authenticatePhoneUser($cleanDigits, $fullPhone, $selectedRole);
        } catch (\Exception $e) {
            Log::error('[AuthController@selectPhoneAccount] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la sélection de l\'espace'], 500);
        }
    }

    private function authenticatePhoneUser(string $cleanDigits, string $fullPhone, string $role): JsonResponse
    {
        if ($role === 'manager') {
            $manager = Manager::where('phone', 'LIKE', "%{$cleanDigits}")
                ->orWhere('phone', $fullPhone)
                ->first();

            if (!$manager) {
                return response()->json(['message' => 'Compte Backoffice non trouvé.'], 404);
            }

            $token = $manager->createToken('manager-token')->plainTextToken;
            return response()->json([
                'status' => 'authenticated',
                'token' => $token,
                'user' => $manager->makeHidden(['password']),
                'role' => 'manager',
            ]);
        }

        $user = User::where('phone', 'LIKE', "%{$cleanDigits}")
            ->orWhere('phone', $fullPhone)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Compte Client non trouvé.'], 404);
        }

        $token = $user->createToken('client-token')->plainTextToken;
        return response()->json([
            'status' => 'authenticated',
            'token' => $token,
            'user' => $user->makeHidden(['password']),
            'role' => 'client',
        ]);
    }
}

