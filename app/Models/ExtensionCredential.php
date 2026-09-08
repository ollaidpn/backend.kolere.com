<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionCredential extends Model
{
    use HasFactory;

    protected $table = 'extension_credentials';

    protected $fillable = [
        'entity_id',
        'extension',
        'data',
        'config',
        'status',
    ];

    protected $casts = [
        'data' => 'array',
        'config' => 'array',
        'status' => 'boolean',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
