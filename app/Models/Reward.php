<?php

namespace App\Models;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = ['entity_id', 'name', 'description', 'points_required', 'value', 'stock', 'status'];

    protected $casts = [
        'points_required' => 'integer',
        'value' => 'integer',
        'stock' => 'integer',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
