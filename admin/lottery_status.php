<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Session boshlash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin-only read-only diagnostics for lottery
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
try {
    $db = $db ?? \Tortinmang\Core\DB::get();
    $prizes = $db->fetchAll('SELECT id,name,prize_type,amount,weight FROM lottery_prizes WHERE is_active = 1 ORDER BY weight DESC');
    $activePrizes = count($prizes);

    $spinsToday = (int)$db->fetchColumn("SELECT COUNT(*) FROM lottery_spins WHERE DATE(spun_at)=CURDATE()", []);

    $topWinners = $db->fetchAll(
        "SELECT u.username, u.first_name, SUM(ls.result_amount) as total_won FROM lottery_spins ls JOIN users u ON ls.user_id=u.id WHERE ls.result_amount>0 AND DATE(ls.spun_at)=CURDATE() GROUP BY ls.user_id ORDER BY total_won DESC LIMIT 10"
    );

    echo json_encode([
        'active_prizes' => $activePrizes,
        'prizes' => $prizes,
        'spins_today' => $spinsToday,
        'top_winners_today' => $topWinners,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    \Tortinmang\Core\Logger::error('admin.lottery_status failed: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error'], JSON_UNESCAPED_UNICODE);
    exit;
}
