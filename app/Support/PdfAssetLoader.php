<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PdfAssetLoader
{
    /** @return array{base64:string|null, mime:string|null} */
    public function branding(string $name): array
    {
        foreach (['local', (string) config('filesystems.uploads_disk', 'public')] as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $extension) {
                    $path = 'branding/'.$name.'.'.$extension;
                    if ($disk->exists($path)) {
                        return $this->encoded($disk->get($path), $extension);
                    }
                }
            } catch (\Throwable) {
            }
        }

        foreach ([storage_path('app/branding'), storage_path('app/private/branding'), public_path('branding'), public_path()] as $directory) {
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $extension) {
                $path = $directory.DIRECTORY_SEPARATOR.$name.'.'.$extension;
                if (is_file($path) && is_readable($path)) {
                    $contents = @file_get_contents($path);
                    if ($contents !== false) {
                        return $this->encoded($contents, $extension);
                    }
                }
            }
        }

        return ['base64' => null, 'mime' => null];
    }

    /** @return array{base64:string|null, mime:string|null} */
    public function uploadedImage(?string $path): array
    {
        if ($path === null || $path === '') {
            return ['base64' => null, 'mime' => null];
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return ['base64' => null, 'mime' => null];
        }

        try {
            $disk = Storage::disk((string) config('filesystems.uploads_disk', 'public'));
            if ($disk->exists($path)) {
                return $this->encoded($disk->get($path), $extension);
            }
        } catch (\Throwable) {
        }

        return ['base64' => null, 'mime' => null];
    }

    /** @return array{base64:string, mime:string} */
    private function encoded(string $contents, string $extension): array
    {
        return [
            'base64' => base64_encode($contents),
            'mime' => match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            },
        ];
    }
}
