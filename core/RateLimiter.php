<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class RateLimiter
{
    private static function file(string $identifier, string $action): string
    {
        $dir = Config::get('storage.cache_dir', __DIR__ . '/../storage/cache/') . 'ratelimit/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . md5($identifier . ':' . $action) . '.rl';
    }

    private static function read(string $file): array
    {
        if (!file_exists($file)) {
            return ['attempts' => 0, 'window_start' => time()];
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return ['attempts' => 0, 'window_start' => time()];
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return ['attempts' => 0, 'window_start' => time()];
        }
        return $data;
    }

    private static function write(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Check if rate limit is exceeded.
     * Returns true if allowed, false if blocked.
     */
    public static function check(
        string $identifier,
        string $action,
        int    $maxAttempts,
        int    $windowSeconds
    ): bool {
        $file = self::file($identifier, $action);
        $data = self::read($file);

        // Reset window if expired
        if (time() - $data['window_start'] >= $windowSeconds) {
            $data = ['attempts' => 0, 'window_start' => time()];
            self::write($file, $data);
        }

        return $data['attempts'] < $maxAttempts;
    }

    public static function increment(
        string $identifier,
        string $action,
        int    $windowSeconds
    ): void {
        $file = self::file($identifier, $action);
        $data = self::read($file);

        if (time() - $data['window_start'] >= $windowSeconds) {
            $data = ['attempts' => 1, 'window_start' => time()];
        } else {
            $data['attempts']++;
        }

        self::write($file, $data);
    }

    public static function reset(string $identifier, string $action): void
    {
        $file = self::file($identifier, $action);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function remaining(
        string $identifier,
        string $action,
        int    $maxAttempts,
        int    $windowSeconds
    ): int {
        $file = self::file($identifier, $action);
        $data = self::read($file);

        if (time() - $data['window_start'] >= $windowSeconds) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $data['attempts']);
    }

    /**
     * Throttle helper: check + increment in one call.
     * Returns true if allowed, false if rate limited.
     */
    public static function throttle(
        string $identifier,
        string $action,
        int    $maxAttempts,
        int    $windowSeconds
    ): bool {
        if (!self::check($identifier, $action, $maxAttempts, $windowSeconds)) {
            return false;
        }
        self::increment($identifier, $action, $windowSeconds);
        return true;
    }
}
