<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Start session for admin checks
if (session_status() === PHP_SESSION_NONE) session_start();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = $_POST;
header('Content-Type: application/json; charset=utf-8');
$csrf = $input['csrf'] ?? '';
$wid  = (int)($input['id'] ?? 0);

// Simple CSRF + session check: verify admin session exists
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF']);
    exit;
}
if (!$wid) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad request']);
    exit;
}

try {
    $db  = \Tortinmang\Core\DB::get();
    $row = $db->fetch("SELECT card_number,user_id FROM withdrawals WHERE id = ? LIMIT 1", [$wid]);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        exit;
    }
    // Log reveal action
    \Tortinmang\Core\Logger::info("Admin reveal card", ['admin_id' => $_SESSION['admin_id'] ?? null, 'withdrawal_id' => $wid, 'user_id' => $row['user_id']]);

    // Return masked and full (full only in JSON) — caller must not render without confirmation
    echo json_encode(['success' => true, 'card' => $row['card_number']]);
    exit;
} catch (Exception $e) {
    \Tortinmang\Core\Logger::error('reveal_card error', ['err' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}
