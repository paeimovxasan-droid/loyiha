<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Response
{
    public static function json(
        bool   $success,
        string $message = '',
        array  $data = [],
        int    $statusCode = 200
    ): never {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = ['success' => $success];

        if ($message !== '') {
            $response['message'] = $message;
        }

        if (!empty($data)) {
            $response = array_merge($response, $data);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], string $message = ''): never
    {
        self::json(true, $message, $data);
    }

    public static function error(string $message, int $statusCode = 200): never
    {
        self::json(false, $message, [], $statusCode);
    }

    public static function notFound(string $message = 'Topilmadi'): never
    {
        self::json(false, $message, [], 404);
    }

    public static function unauthorized(string $message = 'Avtorizatsiya talab qilinadi'): never
    {
        self::json(false, $message, [], 401);
    }

    public static function tooManyRequests(string $message = 'Juda ko\'p so\'rovlar. Biroz kuting.'): never
    {
        self::json(false, $message, [], 429);
    }

    public static function serverError(string $message = 'Server xatosi'): never
    {
        self::json(false, $message, [], 500);
    }

    public static function cors(): void
    {
        if (headers_sent()) return;
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }

    public static function handleOptions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
