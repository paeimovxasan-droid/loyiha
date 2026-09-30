<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Config
{
    private static array $data = [];
    private static bool  $loaded = false;

    public static function load(string $file): void
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Config file not found: {$file}");
        }
        self::$data   = require $file;
        self::$loaded = true;
        // Allow overriding DB config via environment variables for quick testing
        $envHost = getenv('DB_HOST');
        $envName = getenv('DB_NAME');
        $envUser = getenv('DB_USER');
        $envPass = getenv('DB_PASS');
        if ($envHost !== false || $envName !== false || $envUser !== false || $envPass !== false) {
            $db = self::$data['db'] ?? [];
            if ($envHost !== false) $db['host'] = $envHost;
            if ($envName !== false) $db['name'] = $envName;
            if ($envUser !== false) $db['user'] = $envUser;
            if ($envPass !== false) $db['pass'] = $envPass;
            self::$data['db'] = $db;
        }

        // Allow overriding Telegram bot token via environment variable (useful on shared hosting)
        $envTelegramToken = getenv('TELEGRAM_BOT_TOKEN');
        if ($envTelegramToken !== false && $envTelegramToken !== '') {
            if (!isset(self::$data['telegram'])) self::$data['telegram'] = [];
            self::$data['telegram']['bot_token'] = $envTelegramToken;
        }
    }

    /**
     * Get config value using dot notation.
     * Example: Config::get('db.host'), Config::get('finance.min_deposit')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load(__DIR__ . '/../config.php');
        }

        $keys    = explode('.', $key);
        $current = self::$data;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public static function set(string $key, mixed $value): void
    {
        $keys    = explode('.', $key);
        $current = &self::$data;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    public static function all(): array
    {
        return self::$data;
    }
}
