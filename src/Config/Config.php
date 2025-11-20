<?php

namespace LlmsApp\Config;

class Config
{
    public static function get(string $key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }

    public static function getBasePath(): string
    {
        $base = self::get('APP_BASE_PATH', '/');
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }

    public static function getStoragePath(): string
    {
        $storage = self::get('STORAGE_PATH', 'storage');
        return realpath(__DIR__ . '/../../' . $storage) ?: (__DIR__ . '/../../storage');
    }
}