<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'type',
        'title',
        'message',
        'send_to',
        'status',
        'scheduled_at',
    ];

    protected $casts = [
        'send_to' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
