<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AlertMessage extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'title',
        'image',
        'description',
        'entity_id',
        'read',
    ];

    protected $searchableFields = ['*'];

    protected $table = 'alert_messages';

    protected $casts = [
        'read' => 'boolean',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
