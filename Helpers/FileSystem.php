<?php
namespace Helpers;

class FileSystem {
    public static function ensureDir(string $absDir): void {
        if (!is_dir($absDir)) {
            mkdir($absDir, 0777, true);
        }
    }

    public static function mimeToExt(?string $ct): string {
        return match (true) {
            $ct && str_contains($ct, 'image/jpeg') => 'jpg',
            $ct && str_contains($ct, 'image/png') => 'png',
            $ct && str_contains($ct, 'image/webp') => 'webp',
            $ct && str_contains($ct, 'image/gif') => 'gif',
            $ct && str_contains($ct, 'image/bmp') => 'bmp',
            $ct && str_contains($ct, 'image/svg+xml') => 'svg',
            $ct && str_contains($ct, 'image/avif') => 'avif',
            $ct && str_contains($ct, 'image/apng') => 'apng',
            $ct && str_contains($ct, 'image/x-icon') => 'ico',
            $ct && str_contains($ct, 'image/vnd.microsoft.icon') => 'ico',
            $ct && str_contains($ct, 'image/tiff') => 'tif',
            $ct && str_contains($ct, 'image/heic') => 'heic',
            $ct && str_contains($ct, 'image/heif') => 'heif',
            $ct && str_contains($ct, 'image/jxl') => 'jxl',
            default => 'bin',
        };
    }
    
    public static function slug(string $name): string|false {
        $ascii = Text::toAscii($name);
        if ($ascii === false)
            return false;

        $s = mb_strtolower($ascii, 'UTF-8');
        $s = preg_replace('~[^a-z0-9]+~', '_', $s);
        $s = trim($s, '_');
        return $s !== '' ? $s : false;
    }

    public static function createSymlink(string $absTarget, string $absLink): bool {
        try {
            if (realpath($absTarget) === false)
                return false;
        
            if ($absTarget !== realpath($absLink)) {
                @unlink($absLink);
                symlink($absTarget, $absLink);
            }
        } catch (\Exception $e) {
            @unlink($absLink);
            return false;
        }
        return true;
    }
}
