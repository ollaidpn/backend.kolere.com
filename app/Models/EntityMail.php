<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntityMail extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'username',
        'at_domain',
        'status',
        'host',
        'server',
        'password',
        'webmail_link',
        'requested_at',
        'activated_at',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'requested_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    protected $appends = [
        'email_address',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function getEmailAddressAttribute(): string
    {
        $username = trim((string) $this->username);
        $atDomain = trim((string) $this->at_domain);

        return $username . $atDomain;
    }
}
