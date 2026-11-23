<?php

namespace App\Utils;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Resolves MIME type without requiring ext-fileinfo (Symfony's File::getMimeType() throws otherwise).
 */
final class UploadedMimeType
{
    /**
     * @return string|null Normalized MIME type, or null if unknown
     */
    public static function guess(UploadedFile $file): ?string
    {
        try {
            $mime = $file->getMimeType();
        } catch (\Throwable) {
            $mime = null;
        }

        if (!\is_string($mime) || $mime === '') {
            $mime = $file->getClientMimeType();
        }

        if (!\is_string($mime) || $mime === '') {
            $mime = self::fromExtension($file->getClientOriginalExtension());
        }

        return self::normalize($mime);
    }

    private static function fromExtension(string $ext): ?string
    {
        return match (strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    private static function normalize(?string $mime): ?string
    {
        if ($mime === null || $mime === '') {
            return null;
        }

        return match ($mime) {
            'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            default => $mime,
        };
    }
}
