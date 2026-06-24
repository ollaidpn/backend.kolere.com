<?php

namespace App\Models;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'entity_id', 'description', 'photo', 'status',
        'manager_comment', 'manager_amount', 'responded_at',
    ];

    protected $casts = [
        'responded_at'   => 'datetime',
        'manager_amount' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
