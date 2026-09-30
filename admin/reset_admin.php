<?php
/**
 * Admin parolni tiklash uchun skript.
 * Faqat maxfiy kalit bilan va ishlatib bo'lgach DARHOL o'chiring!
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tortinmang\Core\{Config, DB};

$secret = $_GET['secret'] ?? '';
$cfgSecret = Config::get('app.debug_secret', '');
if (empty($secret) || empty($cfgSecret) || str_contains($cfgSecret, 'CHANGE_ME') || $secret !== $cfgSecret) {
    http_response_code(403);

    // Minimal logging and stop
    if (function_exists('file_put_contents')) {
        @file_put_contents(__DIR__ . '/../storage/logs/reset_admin_unauth.log', date('c') . " - Forbidden reset_admin attempt from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
    }
    die('Forbidden');
}

$db = DB::get();

// Yangi parol
$newPassword = trim($_GET['newpass'] ?? '');
if ($newPassword === '') {
    $newPassword = bin2hex(random_bytes(8));
}
$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$existing = $db->fetch('SELECT id FROM admin_users WHERE username = ?', ['admin']);
if ($existing) {
    $db->update('admin_users', [
        'password_hash'  => $hash,
        'login_attempts' => 0,
        'locked_until'   => null,
    ], 'username = ?', ['admin']);
    echo "<h2 style='font-family:monospace;color:green'>✅ Admin paroli yangilandi!</h2>";
} else {
    $db->insert('admin_users', [
        'username'      => 'admin',
        'password_hash' => $hash,
    ]);
    echo "<h2 style='font-family:monospace;color:green'>✅ Admin yaratildi!</h2>";
}

echo "<p style='font-family:monospace'>Login: <b>admin</b><br>Parol: <b>" . htmlspecialchars($newPassword) . "</b></p>";
echo "<p style='font-family:monospace;color:red'>⚠️ Bu faylni DARHOL o'chiring: <code>/admin/reset_admin.php</code></p>";
echo "<p><a href='/admin/'>Admin panelga o'tish →</a></p>";
