<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeditectImportTask extends Model
{
    use HasFactory;

    protected $table = 'meditect_import_tasks';

    protected $fillable = [
        'entity_id',
        'extension_credential_id',
        'meditect_import_session_id',
        'external_item_id',
        'status',
        'rayon_ids',
        'rayon_names',
        'summary',
        'details',
        'shop_item_id',
        'attempts',
        'error_message',
        'started_at',
        'processed_at',
    ];

    protected $casts = [
        'rayon_ids' => 'array',
        'rayon_names' => 'array',
        'summary' => 'array',
        'details' => 'array',
        'attempts' => 'integer',
        'started_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function extensionCredential()
    {
        return $this->belongsTo(ExtensionCredential::class);
    }

    public function session()
    {
        return $this->belongsTo(MeditectImportSession::class, 'meditect_import_session_id');
    }

    public function shopItem()
    {
        return $this->belongsTo(ShopItem::class);
    }
}
