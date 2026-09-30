<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Cache
{
    private static function dir(): string
    {
        $dir = Config::get('storage.cache_dir', __DIR__ . '/../storage/cache/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    }

    private static function file(string $key): string
    {
        return self::dir() . md5($key) . '.cache';
    }

    public static function get(string $key): mixed
    {
        $file = self::file($key);
        if (!file_exists($file)) return null;

        $content = @file_get_contents($file);
        if ($content === false) return null;

        $data = json_decode($content, true);
        if (!is_array($data)) return null;

        if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = 300): void
    {
        $data = [
            'value'      => $value,
            'expires_at' => $ttl === 0 ? 0 : time() + $ttl,
            'created_at' => time(),
        ];

        @file_put_contents(self::file($key), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function delete(string $key): void
    {
        $file = self::file($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function flush(): void
    {
        $dir   = self::dir();
        $files = glob($dir . '*.cache');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Get cached value or execute callback and cache result.
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /** Check if key exists and not expired */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /** Purge expired cache files */
    public static function purgeExpired(): int
    {
        $dir   = self::dir();
        $files = glob($dir . '*.cache');
        $count = 0;

        if (!is_array($files)) return 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            $data = json_decode($content, true);
            if (!is_array($data) || ($data['expires_at'] !== 0 && $data['expires_at'] < time())) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
