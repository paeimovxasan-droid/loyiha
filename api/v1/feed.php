<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Request, Response, RateLimiter, Cache, DB};

$req    = new Request();
$action = $req->action('list');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

match ($action) {
    'list'  => handleList(),
    'stats' => handleStats(),
    default => Response::error("Noma'lum amal"),
};

function handleList(): never
{
    $feeds = Cache::remember('feed:list', 30, fn () =>
        DB::get()->fetchAll(
            "SELECT user_display_name, amount, feed_type, created_at
             FROM payment_feed
             ORDER BY created_at DESC
             LIMIT 30"
        )
    );

    Response::success(['feeds' => $feeds]);
}

function handleStats(): never
{
    $stats = Cache::remember('feed:stats', 120, function (): array {
        $db = DB::get();

        $totalUsers = (int) $db->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = 'fake_total_users'"
        );
        if (!$totalUsers) {
            $realUsers  = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_blocked = 0");
            $totalUsers = $realUsers + 14500; // fake boost
        }

        $totalPaid = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'approved'"
        );
        $fakePaid  = (float) ($db->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = 'fake_total_paid'"
        ) ?: 0);

        $onlineMin = (int) ($db->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = 'fake_online_min'"
        ) ?: 847);
        $onlineMax = (int) ($db->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = 'fake_online_max'"
        ) ?: 2341);

        return [
            'total_users'       => $totalUsers,
            'online_users'      => random_int($onlineMin, $onlineMax),
            'total_deposits'    => (float) $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status='approved'"),
            'total_withdrawals' => $totalPaid + $fakePaid,
        ];
    });

    Response::success($stats);
}
