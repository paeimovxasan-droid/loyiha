<?php
declare(strict_types=1);

// Autoloader (PSR-4 manual)
spl_autoload_register(function (string $class): void {
    $base    = __DIR__ . '/';
    $map     = [
        'Tortinmang\\Core\\'   => 'core/',
        'Tortinmang\\Models\\' => 'models/',
    ];

    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file     = $base . $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
            return;
        }
    }
});

// Load config
\Tortinmang\Core\Config::load(__DIR__ . '/config.php');

// Ensure storage directories exist and are writable
(function (): void {
    $dirs = [
        \Tortinmang\Core\Config::get('storage.cache_dir', __DIR__ . '/storage/cache/'),
        \Tortinmang\Core\Config::get('storage.log_dir',   __DIR__ . '/storage/logs/'),
        \Tortinmang\Core\Config::get('storage.cache_dir', __DIR__ . '/storage/cache/') . 'ratelimit/',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
})();

// Error handling
$debug = \Tortinmang\Core\Config::get('app.debug', false);
error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');

// Set exception handler
set_exception_handler(function (\Throwable $e) use ($debug): void {
    \Tortinmang\Core\Logger::error($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => substr($e->getTraceAsString(), 0, 500),
    ]);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Admin panel uchun HTML xato ko'rsat
    $isAdmin = str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin');
    if ($isAdmin) {
        $msg = $debug ? htmlspecialchars($e->getMessage()) : 'Serverda xatolik yuz berdi. Iltimos DB va config sozlamalarini tekshiring yoki hosting provayder bilan bog\'laning.';
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Xatolik</title>
        <style>body{background:#0d0f1a;color:#f87171;font-family:monospace;padding:40px;}</style></head>
        <body><h2>⚠️ Xatolik</h2><p>{$msg}</p></body></html>";
        exit;
    }

    $message = $debug ? $e->getMessage() : 'Xatolik yuz berdi';
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
});

// CORS + OPTIONS
\Tortinmang\Core\Response::cors();
\Tortinmang\Core\Response::handleOptions();
