<?php

namespace App\Models;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = ['entity_id', 'name', 'description', 'images', 'points_required', 'value', 'stock', 'status'];

    protected $casts = [
        'images' => 'array',
        'points_required' => 'integer',
        'value' => 'integer',
        'stock' => 'integer',
    ];

    protected $appends = ['images_urls', 'image_url'];

    public function getImagesUrlsAttribute(): array
    {
        if (empty($this->images) || !is_array($this->images)) {
            return [];
        }

        $fileService = new \App\Services\FileUploadService();
        return array_map(static function ($path) use ($fileService) {
            return str_starts_with($path, 'http') ? $path : $fileService->getUrl($path);
        }, $this->images);
    }

    public function getImageUrlAttribute(): ?string
    {
        $urls = $this->images_urls;
        return $urls[0] ?? null;
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
