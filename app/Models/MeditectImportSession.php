<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeditectImportSession extends Model
{
    use HasFactory;

    protected $table = 'meditect_import_sessions';

    protected $fillable = [
        'entity_id',
        'extension_credential_id',
        'status',
        'cursor',
        'buffer',
        'buffer_index',
        'batch_size',
        'processed_count',
        'imported_count',
        'matched_count',
        'total_count',
        'selected_rayons_snapshot',
        'meta',
        'error_message',
        'started_at',
        'paused_at',
        'finished_at',
        'last_run_at',
    ];

    protected $casts = [
        'buffer' => 'array',
        'selected_rayons_snapshot' => 'array',
        'meta' => 'array',
        'buffer_index' => 'integer',
        'batch_size' => 'integer',
        'processed_count' => 'integer',
        'imported_count' => 'integer',
        'matched_count' => 'integer',
        'total_count' => 'integer',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function extensionCredential()
    {
        return $this->belongsTo(ExtensionCredential::class);
    }
}
