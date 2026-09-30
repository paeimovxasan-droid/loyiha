<?php
// Test lottery.php status without initData (should unauthorized)
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'status';
ob_start();
include __DIR__ . '/../api/v1/lottery.php';
$out = ob_get_clean();
echo $out;
