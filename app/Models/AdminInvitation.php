<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminInvitation extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'name', 'ccphone', 'phone', 'token', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
