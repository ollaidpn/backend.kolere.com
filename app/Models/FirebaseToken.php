<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirebaseToken extends Model
{
    protected $table = 'firebase_tokens';

    protected $fillable = [
        'user_id',
        'manager_id',
        'admin_id',
        'token',
        'device_type',
        'device_id',
        'device_name',
        'app_version',
        'app_platform',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Enregistrer ou mettre à jour un token FCM pour un compte (User, Manager, ou Admin).
     */
    public static function registerToken(
        $authenticatable,
        string $token,
        ?string $deviceType = null,
        ?string $deviceId = null,
        ?string $deviceName = null,
        ?string $appVersion = null,
        ?string $appPlatform = null
    ): self {
        $attributes = ['token' => $token];
        
        if ($authenticatable instanceof User) {
            $attributes['user_id'] = $authenticatable->id;
        } elseif ($authenticatable instanceof Manager) {
            $attributes['manager_id'] = $authenticatable->id;
        } elseif ($authenticatable instanceof Admin) {
            $attributes['admin_id'] = $authenticatable->id;
        } else {
            $attributes['user_id'] = $authenticatable->id ?? null;
        }

        return self::updateOrCreate(
            $attributes,
            [
                'device_type' => $deviceType,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'app_version' => $appVersion,
                'app_platform' => $appPlatform,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Désactiver un token (ex: logout).
     */
    public static function deactivateToken($authenticatable, string $token): bool
    {
        $query = self::where('token', $token);

        if ($authenticatable instanceof User) {
            $query->where('user_id', $authenticatable->id);
        } elseif ($authenticatable instanceof Manager) {
            $query->where('manager_id', $authenticatable->id);
        } elseif ($authenticatable instanceof Admin) {
            $query->where('admin_id', $authenticatable->id);
        } else {
            $query->where('user_id', $authenticatable->id ?? null);
        }

        return $query->update(['is_active' => false]) > 0;
    }

    /**
     * Récupérer tous les tokens actifs pour un compte.
     */
    public static function getActiveTokensForTarget($authenticatable): array
    {
        $query = self::where('is_active', true);

        if ($authenticatable instanceof User) {
            $query->where('user_id', $authenticatable->id);
        } elseif ($authenticatable instanceof Manager) {
            $query->where('manager_id', $authenticatable->id);
        } elseif ($authenticatable instanceof Admin) {
            $query->where('admin_id', $authenticatable->id);
        } else {
            $query->where('user_id', is_numeric($authenticatable) ? $authenticatable : ($authenticatable->id ?? null));
        }

        return $query->pluck('token')->toArray();
    }

    /**
     * Supprimer les tokens invalides (après échec d'envoi FCM).
     */
    public static function removeInvalidTokens(array $invalidTokens): int
    {
        return self::whereIn('token', $invalidTokens)->delete();
    }
}
