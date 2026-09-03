<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserHealth extends Model
{
    use HasFactory;

    protected $table = 'user_health';

    protected $fillable = [
        'user_id',
        'blood_type',
        'weight_kg',
        'height_cm',
        'medical_history',
        'chronic_diseases',
        'current_treatments',
        'emergency_notes',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'allergies',
    ];

    protected $casts = [
        'allergies' => 'array',
        'weight_kg' => 'float',
        'height_cm' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
