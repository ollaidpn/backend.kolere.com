<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AlertApp extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'entity_id',
        'title',
        'description',
        'read',
        'card_id',
        'manager_id',
    ];

    protected $searchableFields = ['*'];

    protected $table = 'alert_apps';

    protected $casts = [
        'read' => 'boolean',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function manager()
    {
        return $this->belongsTo(Manager::class);
    }
}
