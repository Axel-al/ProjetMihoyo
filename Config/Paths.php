<?php
namespace Config;

class Paths {
    private static ?string $projectRoot = null;
    private static ?string $publicSysAbs = null;
    private static ?string $publicUrl = null;
    private static ?string $imgSysAbs = null;
    private static ?string $imgUrl = null;
    private static null|string|false $caBundle = null;
    
    public static function projectRoot(): string {
        return self::$projectRoot ??= realpath(__DIR__ . '/..');
    }

    public static function publicSysAbs(): string {
        return self::$publicSysAbs ??= self::projectRoot() . '/public';
    }

    public static function publicUrl(): string {
        return self::$publicUrl ??= rtrim(Config::get('public_url', required: true), '/');
    }

    public static function imgSysAbs(): string {
        return self::$imgSysAbs ??= self::publicSysAbs() . '/img';
    }

    public static function imgUrl(): string {
        return self::$imgUrl ??= self::publicUrl() . '/img';
    }

    public static function caBundle(): string|false {
        return self::$caBundle ??= (function() {
            $relative = Config::get('ca_bundle');
            if (is_null($relative))
                return false;
            return self::projectRoot() . '/' . rtrim($relative, '/');
        })();
    }
}
