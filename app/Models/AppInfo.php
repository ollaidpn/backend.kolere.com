<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'payment_restriction_enabled',
    ];

    protected $casts = [
        'payment_restriction_enabled' => 'boolean',
    ];

    protected $table = 'app_infos';

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
