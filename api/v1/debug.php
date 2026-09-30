<?php
/**
 * DEBUG endpoint — faqat muammolarni aniqlash uchun.
 * Ishlatilgandan keyin O'CHIRING: /api/v1/debug.php
 *
 * https://app.tortinmang.uz/api/v1/debug.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Config, DB, Auth, Logger};

$secret = $_GET['key'] ?? '';
$cfgSecret = Config::get('app.debug_secret', '');
// Reject access when debug secret is unset or left as default placeholder
if (empty($secret) || empty($cfgSecret) || str_contains($cfgSecret, 'CHANGE_ME') || $secret !== $cfgSecret) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    Logger::warning('Unauthorized debug.php access attempt', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$results = [];

// 1. PHP versiyasi
$results['php_version'] = PHP_VERSION;
$results['php_ok']      = version_compare(PHP_VERSION, '8.0.0', '>=');

// 2. DB ulanishi
try {
    $db = DB::get();
    $db->fetchColumn('SELECT 1');
    $results['db_connection'] = 'OK';

    // Jadvallar mavjudmi?
    $tables = $db->fetchAll("SHOW TABLES");
    $results['tables_count'] = count($tables);
    $results['tables'] = array_map('current', $tables);
} catch (\Exception $e) {
    $results['db_connection'] = 'ERROR: ' . $e->getMessage();
}

// 3. Config tekshirish
$token = Config::get('telegram.bot_token', '');
$results['bot_token_set']     = !empty($token) && !str_contains($token, 'CHANGE_ME');
$results['bot_token_preview'] = $token ? (substr($token, 0, 10) . '...') : 'NOT SET';
$results['app_url']           = Config::get('app.url');
$results['miniapp_url']       = Config::get('app.miniapp_url');
$results['app_env']           = Config::get('app.env');
$results['min_deposit']       = Config::get('finance.min_deposit');
$results['min_withdraw']      = Config::get('finance.min_withdraw');

// 4. Storage papkalariga yozish huquqi
$cacheDir = Config::get('storage.cache_dir');
$logDir   = Config::get('storage.log_dir');
$results['cache_dir']       = $cacheDir;
$results['cache_writable']  = is_writable($cacheDir);
$results['log_dir']         = $logDir;
$results['log_writable']    = is_writable($logDir);

// 5. Admin users
try {
    $admins = DB::get()->fetchAll('SELECT id, username, login_attempts, locked_until, last_login FROM admin_users');
    $results['admin_users'] = $admins;
} catch (\Exception $e) {
    $results['admin_users'] = 'ERROR: ' . $e->getMessage();
}

// 6. Extensions
$results['curl_enabled']    = function_exists('curl_init');
$results['json_enabled']    = function_exists('json_encode');
$results['hash_enabled']    = function_exists('hash_hmac');
$results['openssl_enabled'] = extension_loaded('openssl');

// 7. initData test (agar berilsa)
$testInitData = $_GET['initData'] ?? $_POST['initData'] ?? '';
if ($testInitData) {
    $params = [];
    parse_str($testInitData, $params);
    $results['initdata_parsed_keys'] = array_keys($params);
    $results['initdata_has_user']    = isset($params['user']);
    $results['initdata_has_hash']    = isset($params['hash']);
    $results['initdata_auth_date']   = $params['auth_date'] ?? 'missing';
    if (!empty($params['auth_date'])) {
        $results['initdata_age_seconds'] = time() - (int)$params['auth_date'];
    }

    // Auth test
    $user = Auth::getUser($testInitData);
    $results['auth_result'] = $user ? [
        'success'     => true,
        'telegram_id' => $user['telegram_id'],
        'first_name'  => $user['first_name'],
    ] : ['success' => false];
}

// 8. Son'gi log satrlari
try {
    $todayLog = Config::get('storage.log_dir') . date('Y-m-d') . '.log';
    if (file_exists($todayLog)) {
        $lines = file($todayLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $results['recent_logs'] = array_slice($lines ?? [], -20);
    } else {
        $results['recent_logs'] = 'Log fayl mavjud emas: ' . $todayLog;
    }
} catch (\Exception $e) {
    $results['recent_logs'] = 'ERROR: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
