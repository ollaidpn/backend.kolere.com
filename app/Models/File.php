<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file',
        'path',
        'type',
        'extension',
        'size_byte',
        'size_name',
        'is_private',
        'reference',
        'user_id',
        'project_id',
        'task_id',
        'client_id',
        'message_id',
        'folder_id',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'size_byte' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($file) {
            if (empty($file->reference)) {
                $file->reference = 'FILE-' . strtoupper(\Str::random(8));
            }
        });
    }

    public function getPublicUrlAttribute(): string
    {
        if (str_starts_with($this->file, 'http')) {
            return $this->file;
        }
        
        $disk = config('filesystems.default', 's3');
        if ($disk === 's3' && empty(config('filesystems.disks.s3.bucket'))) {
            $disk = 'public';
        }
        return \Storage::disk($disk)->url($this->path ?? $this->file);
    }
}
