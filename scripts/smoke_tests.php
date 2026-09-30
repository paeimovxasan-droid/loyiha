<?php
// Simple smoke tests runner for key endpoints
require_once __DIR__ . '/../bootstrap.php';

function runDebugWrongKey() {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['key'] = 'wrong';
    ob_start();
    include __DIR__ . '/../api/v1/debug.php';
    $out = ob_get_clean();
    return $out;
}

function runResetAdminWrongKey() {
    $tmp = __DIR__ . '/.tmp_reset.php';
    $code = <<<'PHP'
<?php
$_GET['secret'] = 'wrong';
include __DIR__ . '/../admin/reset_admin.php';
PHP;
    file_put_contents($tmp, $code);
    $out = shell_exec('php ' . escapeshellarg($tmp) . ' 2>&1');
    @unlink($tmp);
    return $out;
}

echo "DEBUG wrong key test:\n";
echo runDebugWrongKey();

echo "\nRESET_ADMIN wrong key test:\n";
echo runResetAdminWrongKey();
