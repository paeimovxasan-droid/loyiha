<?php
// Test auth.php without initData
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
ob_start();
include __DIR__ . '/../api/v1/auth.php';
$out = ob_get_clean();
echo $out;
