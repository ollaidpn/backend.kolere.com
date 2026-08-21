<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'user_id',
        'manager_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function manager()
    {
        return $this->belongsTo(Manager::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
