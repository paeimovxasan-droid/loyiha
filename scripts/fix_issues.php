<?php
declare(strict_types=1);

// fix_issues.php
// Usage: php scripts/fix_issues.php [--apply] [--set-db-host=127.0.0.1] [--test-telegram] [--admin-chat=CHAT_ID]

$options = [];
foreach ($argv as $a) {
    if (strpos($a, '--') === 0) {
        [$k,$v] = array_pad(explode('=', $a, 2), 2, null);
        $options[$k] = $v;
    }
}
$apply = array_key_exists('--apply', $options);
$setHost = $options['--set-db-host'] ?? null;
$testTelegram = array_key_exists('--test-telegram', $options);
$adminChat = $options['--admin-chat'] ?? null;

$root = dirname(__DIR__);
$configFile = $root . '/config.php';
if (!file_exists($configFile)) {
    echo "config.php not found at {$configFile}\n";
    exit(2);
}

echo "Loading config...\n";
$config = require $configFile;
$dbCfg = $config['db'] ?? [];

echo "Current DB config:\n";
echo " host: " . ($dbCfg['host'] ?? 'n/a') . "\n";
echo " name: " . ($dbCfg['name'] ?? 'n/a') . "\n";
echo " user: " . ($dbCfg['user'] ?? 'n/a') . "\n";

// Ensure storage dirs
$storageDirs = [
    $root . '/storage',
    $root . '/storage/logs',
    $root . '/storage/cache',
];
foreach ($storageDirs as $d) {
    if (!is_dir($d)) {
        if ($apply) {
            mkdir($d, 0755, true);
            echo "Created dir: {$d}\n";
        } else {
            echo "Missing dir: {$d}\n";
        }
    }
    if (!is_writable($d)) {
        if ($apply) {
            @chmod($d, 0755);
            echo "Set permissions 0755 on {$d}\n";
        } else {
            echo "Not writable: {$d}\n";
        }
    }
}

// DB connectivity test
$host = $dbCfg['host'] ?? 'localhost';
$name = $dbCfg['name'] ?? '';
$user = $dbCfg['user'] ?? '';
$pass = $dbCfg['pass'] ?? '';
$charset = $dbCfg['charset'] ?? 'utf8mb4';
$port = $dbCfg['port'] ?? null;

echo "\nTesting DB connection to {$host} (db: {$name})...\n";
$dsn = "mysql:host={$host};dbname={$name};charset={$charset}" . ($port ? ";port={$port}" : '');
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    echo "OK: connected. Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    // show current user identity
    $row = $pdo->query("SELECT USER() as user, CURRENT_USER() as current_user")->fetch(PDO::FETCH_ASSOC);
    echo "MySQL reported USER(): " . ($row['user'] ?? '') . " CURRENT_USER(): " . ($row['current_user'] ?? '') . "\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    // Suggest fixes for Access denied
    if (stripos($e->getMessage(), 'access denied') !== false) {
        // attempt to extract username@host
        if (preg_match("/for user '([^']+)'@'([^']+)'/i", $e->getMessage(), $m)) {
            $badUser = $m[1]; $badHost = $m[2];
            echo "Detected MySQL error for user@host: {$badUser}@{$badHost}\n";
            echo "Suggested SQL to run as MySQL root (phpMyAdmin or mysql root):\n";
            // produce GRANT for host used by PHP (127.0.0.1)
            $grantHost = '127.0.0.1';
            echo "-- create or grant for PHP web context:\n";
            echo "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER ON `{$name}`.* TO '{$badUser}'@'{$grantHost}' IDENTIFIED BY 'YOUR_DB_PASSWORD';\n";
            echo "FLUSH PRIVILEGES;\n";
        } else {
            echo "Access denied but could not parse user@host from message. Review MySQL user grants.\n";
        }
    }
    // Offer to change config host if user used 127.0.0.1 vs localhost
    echo "You can try switching DB host between 'localhost' and '127.0.0.1'.\n";
    if ($setHost) {
        echo "-- apply requested: set db.host to {$setHost}\n";
    }
}

// If requested, set config db.host to provided host
if ($setHost) {
    // backup config
    $bak = $configFile . '.bak.' . date('YmdHis');
    if (!copy($configFile, $bak)) {
        echo "Failed to create backup {$bak}\n";
    } else {
        echo "Backup created: {$bak}\n";
    }
    // modify array and write
    $config['db']['host'] = $setHost;
    $export = var_export($config, true);
    $new = "<?php\ndeclare(strict_types=1);\n\nreturn " . $export . ";\n";
    if (file_put_contents($configFile, $new) !== false) {
        echo "Updated config.php db.host => {$setHost}\n";
    } else {
        echo "Failed to write config.php\n";
    }
}

// Telegram token test (getMe) if requested
$token = getenv('TELEGRAM_BOT_TOKEN') ?: ($config['telegram']['bot_token'] ?? '') ?: null;
if ($testTelegram) {
    if (empty($token) || str_starts_with($token, 'CHANGE_ME')) {
        echo "Telegram token not set (config or TELEGRAM_BOT_TOKEN).\n";
    } else {
        echo "Testing Telegram token using getMe()...\n";
        $url = "https://api.telegram.org/bot{$token}/getMe";
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            echo "cURL error: {$err}\n";
        } else {
            $j = json_decode($res, true);
            if (is_array($j) && !empty($j['ok'])) {
                echo "Telegram token valid. Bot: " . ($j['result']['username'] ?? 'unknown') . "\n";
                if ($adminChat) {
                    // try sendMessage
                    $msg = urlencode("Test xabar from fix_issues.php - " . date('c'));
                    $sendUrl = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$adminChat}&text={$msg}&parse_mode=Markdown";
                    $r = file_get_contents($sendUrl);
                    echo "sendMessage response: {$r}\n";
                }
            } else {
                echo "Telegram API returned error: " . ($j['description'] ?? json_encode($j)) . "\n";
            }
        }
    }
}

// Lint important files
echo "\nRunning php -l on core files for sanity...\n";
$toLint = [
    $root . '/core/DB.php',
    $root . '/core/TelegramBot.php',
    $root . '/admin/reveal_card.php',
    $root . '/api/v1/auth.php',
];
foreach ($toLint as $f) {
    if (file_exists($f)) {
        $out = null; $rc = null;
        exec("php -l " . escapeshellarg($f) . " 2>&1", $out, $rc);
        echo implode("\n", $out) . "\n";
    }
}

echo "\nDone. Review output and apply suggested SQL changes in your hosting panel or contact host support if needed.\n";

return 0;
