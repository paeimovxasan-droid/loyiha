<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Logger
{
    private static string $logDir = '';

    public static function init(string $logDir): void
    {
        self::$logDir = rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (Config::get('app.debug', false)) {
            self::write('DEBUG', $message, $context);
        }
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $dir = self::$logDir !== ''
            ? self::$logDir
            : (Config::get('storage.log_dir', __DIR__ . '/../storage/logs/'));

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $filename = $dir . date('Y-m-d') . '.log';
        $ctx      = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line     = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $ctx
        );

        @file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }
}
