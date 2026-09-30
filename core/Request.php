<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Request
{
    private array  $data   = [];
    private string $method = '';

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $this->data = $json;
                return;
            }
        }

        // fallback to POST
        $this->data = $_POST ?: [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $val = $this->data[$key] ?? $default;
        return trim((string) $val);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->data[$key] ?? $default;
        return (int) $val;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->data[$key] ?? $default;
        return (float) $val;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        if (!isset($this->data[$key])) return $default;
        return filter_var($this->data[$key], FILTER_VALIDATE_BOOLEAN);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key] !== '';
    }

    public function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = Config::get('app.trusted_proxies', []);

        if (!empty($trusted) && in_array($remote, $trusted, true)) {
            $ips = [];
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = array_merge($ips, array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ips[] = trim($_SERVER['HTTP_X_REAL_IP']);
            }
            $ips = array_filter($ips);
            if (!empty($ips)) {
                return end($ips);
            }
        }

        return $remote;
    }

    /** Get initData (Telegram WebApp) */
    public function initData(): string
    {
        return $this->getString('initData');
    }

    /** Get action parameter */
    public function action(string $default = ''): string
    {
        return $this->getString('action', $default);
    }
}
