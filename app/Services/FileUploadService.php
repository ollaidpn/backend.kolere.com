<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected string $disk;
    
    /**
     * Chemin de base pour l'application.
     * Pour Kolere.
     */
    protected const BASE_PATH = 'web-applications/esargal.com';

    public function __construct()
    {
        // Les médias Kolere doivent partir sur le stockage distant uniquement.
        $this->disk = 's3';
        
        \Log::info('🔧 [FileUploadService] Configuration S3 chargée', [
            'disk_default' => $this->disk,
            's3_config' => [
                'driver' => config('filesystems.disks.s3.driver'),
                'key' => substr(config('filesystems.disks.s3.key') ?? '', 0, 8) . '***',
                'region' => config('filesystems.disks.s3.region'),
                'bucket' => config('filesystems.disks.s3.bucket'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'url' => config('filesystems.disks.s3.url'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
            ],
        ]);
    }

    /**
     * Construit le chemin complet avec la structure de dossiers.
     */
    protected function buildPath(string $module, ?string $identifier = null): string
    {
        $path = self::BASE_PATH . '/' . $module;
        
        if ($identifier) {
            $path .= '/' . $identifier;
        }
        
        return $path;
    }

    /**
     * Upload un fichier et retourne les informations.
     */
    public function upload(UploadedFile $file, string $folder = 'uploads'): array
    {
        try {
            $this->assertS3Configured();

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            \Log::info('📤 [FileUploadService] Début upload', [
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'disk' => $this->disk,
            ]);
            
            $fullPath = $this->buildPath($folder);
            
            $path = $file->storeAs($fullPath, $filename, [
                'disk' => $this->disk,
                'visibility' => 'public',
            ]);
            
            \Log::info('✅ [FileUploadService] Upload réussi', [
                'path' => $path,
                'disk' => $this->disk,
            ]);
            
            $url = Storage::disk($this->disk)->url($path);
            
            return [
                'path' => $path,
                'url' => $url,
                'type' => $this->getFileType($file),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ];
        } catch (\Exception $e) {
            \Log::error('💥 [FileUploadService] Erreur upload', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'disk' => $this->disk,
            ]);
            
            throw $e;
        }
    }

    public function uploadMultiple(array $files, string $folder = 'uploads'): array
    {
        $uploaded = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploaded[] = $this->upload($file, $folder);
            }
        }
        
        return $uploaded;
    }

    public function delete(string $path): bool
    {
        try {
            $this->assertS3Configured();

            $deleted = Storage::disk($this->disk)->delete($path);
            return $deleted;
        } catch (\Exception $e) {
            \Log::error('💥 [FileUploadService] Erreur suppression', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getUrl(string $path): string
    {
        $this->assertS3Configured();

        return Storage::disk($this->disk)->url($path);
    }

    public function exists(string $path): bool
    {
        $this->assertS3Configured();

        return Storage::disk($this->disk)->exists($path);
    }

    private function assertS3Configured(): void
    {
        if (empty(config('filesystems.disks.s3.bucket'))) {
            throw new \RuntimeException('Le stockage S3 n\'est pas configuré.');
        }
    }

    private function getFileType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        
        return 'file';
    }
}
