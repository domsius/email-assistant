<?php

namespace App\Services;

use App\Models\EmailAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AttachmentStorageService
{
    private string $disk = 'local';
    private string $basePath = 'email-attachments';

    public function __construct()
    {
        // You can configure this to use 's3' or other disks
        $this->disk = config('mail.attachments.disk', 'local');
    }

    /**
     * Store an uploaded file and return the storage path
     */
    public function storeUploadedFile(UploadedFile $file, int $emailAccountId): array
    {
        // Generate a unique filename
        $filename = $this->generateFilename($file);
        
        // Create the storage path
        $path = $this->getStoragePath($emailAccountId, $filename);
        
        // Store the file
        Storage::disk($this->disk)->put($path, $file->get());
        
        return [
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'content_type' => $file->getMimeType(),
        ];
    }

    /**
     * Store raw content (for email sync)
     */
    public function storeRawContent(string $content, string $filename, int $emailAccountId, ?string $contentType = null): array
    {
        // Generate a unique filename if needed
        $storageName = $this->generateFilename(null, $filename);
        
        // Create the storage path
        $path = $this->getStoragePath($emailAccountId, $storageName);
        
        // Store the content
        Storage::disk($this->disk)->put($path, $content);
        
        return [
            'path' => $path,
            'filename' => $filename,
            'size' => strlen($content),
            'content_type' => $contentType ?? 'application/octet-stream',
        ];
    }

    /**
     * Get file content
     */
    public function getContent(string $path): ?string
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            \Log::warning('AttachmentStorage: File does not exist', [
                'path' => $path,
                'disk' => $this->disk,
                'full_path' => Storage::disk($this->disk)->path($path),
                'cwd' => getcwd(),
            ]);
            return null;
        }
        
        return Storage::disk($this->disk)->get($path);
    }

    /**
     * Get file stream for large files
     */
    public function getStream(string $path)
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }
        
        return Storage::disk($this->disk)->readStream($path);
    }

    /**
     * Delete attachment file
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Get public URL for attachment (if using public disk like S3)
     */
    public function getPublicUrl(string $path): ?string
    {
        if ($this->disk === 's3' || $this->disk === 'public') {
            return Storage::disk($this->disk)->url($path);
        }
        
        return null;
    }

    /**
     * Generate a thumbnail for image attachments
     */
    public function generateThumbnail(string $path, int $width = 200, int $height = 200): ?string
    {
        // This is a placeholder - in production you'd use Intervention Image or similar
        // For now, just return null
        return null;
    }

    /**
     * Generate unique filename
     */
    private function generateFilename(?UploadedFile $file = null, ?string $originalName = null): string
    {
        $extension = '';
        
        if ($file) {
            $extension = $file->getClientOriginalExtension();
        } elseif ($originalName) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        }
        
        $name = Str::uuid()->toString();
        
        return $extension ? "{$name}.{$extension}" : $name;
    }

    /**
     * Get storage path for attachment
     */
    private function getStoragePath(int $emailAccountId, string $filename): string
    {
        // Organize by account ID and date for easier management
        $date = now()->format('Y/m/d');
        
        return "{$this->basePath}/{$emailAccountId}/{$date}/{$filename}";
    }

    /**
     * Get file size in human readable format
     */
    public function getHumanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Store attachment content from string data
     */
    public function storeAttachmentContent(string $content, string $filename, int $emailAccountId): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $hash = hash('sha256', $content);
        $storagePath = "attachments/{$emailAccountId}/" . date('Y/m/d') . "/{$hash}.{$extension}";
        
        // Ensure directory exists (Storage::put should handle this, but let's be explicit)
        $directory = dirname($storagePath);
        Storage::disk($this->disk)->makeDirectory($directory);
        
        // Store the file
        if (Storage::disk($this->disk)->put($storagePath, $content)) {
            Log::info('Attachment saved to storage', [
                'filename' => $filename,
                'path' => $storagePath,
                'size' => strlen($content)
            ]);
            return $storagePath;
        }
        
        throw new \Exception('Failed to store attachment content');
    }
}