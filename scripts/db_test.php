<?php
declare(strict_types=1);

// DB connectivity test script — run from CLI: php scripts/db_test.php
// Or open in browser (only for short testing, don't leave it exposed in production).

require_once __DIR__ . '/../bootstrap.php';

use Tortinmang\Core\Config;

$cfg = Config::get('db');
$host = $cfg['host'] ?? '';
$name = $cfg['name'] ?? '';
$user = $cfg['user'] ?? '';
$pass = $cfg['pass'] ?? '';
$charset = $cfg['charset'] ?? 'utf8mb4';

echo "Testing DB connection to {$host} (db: {$name})\n";
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

try {
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opts);
    echo "PDO connection ok. Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    // show used host/port from PDO info if available
    $attrs = @json_encode([PDO::ATTR_SERVER_VERSION => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)]);
    echo "Details: {$attrs}\n";
} catch (Throwable $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "DSN: {$dsn}\n";
    exit(1);
}

return 0;
