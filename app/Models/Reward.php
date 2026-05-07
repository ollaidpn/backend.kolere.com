<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'points_required', 'value', 'stock', 'status'];

    protected $casts = [
        'points_required' => 'integer',
        'value' => 'integer',
        'stock' => 'integer',
    ];
}
