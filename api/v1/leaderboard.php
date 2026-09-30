<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Cache, DB, Config};

$req    = new Request();
$action = $req->action('top_earners');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'top_earners'   => handleTopEarners($user),
    'top_referrals' => handleTopReferrals($user),
    'weekly'        => handleWeekly($user),
    'my_rank'       => handleMyRank($user),
    default         => Response::error("Noma'lum amal"),
};

// ─────────────────────────────────────────────────────────────

function handleTopEarners(array $user): never
{
    $leaders = Cache::remember('leaderboard:earners', 300, fn () =>
        DB::get()->fetchAll(
            "SELECT first_name, username, level, badge, total_earned
             FROM users WHERE is_blocked = 0
             ORDER BY total_earned DESC LIMIT 20"
        )
    );

    // Add level_name from config
    $levelNames = Config::get('level_names', []);
    $leaders = array_map(function (array $u) use ($levelNames): array {
        $u['level_name'] = $levelNames[(int)$u['level']] ?? ('Level ' . $u['level']);
        return $u;
    }, $leaders);

    $myRank = (int) DB::get()->fetchColumn(
        "SELECT COUNT(*) + 1 FROM users WHERE total_earned > ? AND is_blocked = 0",
        [$user['total_earned']]
    );

    Response::success([
        'leaders' => $leaders,
        'my_rank' => $myRank,
        'type'    => 'total_earned',
    ]);
}

function handleTopReferrals(array $user): never
{
    $leaders = Cache::remember('leaderboard:referrals', 300, fn () =>
        DB::get()->fetchAll(
            "SELECT u.first_name, u.username, u.level, u.badge,
                    COUNT(r.id) as referral_count
             FROM users u
             LEFT JOIN users r ON r.referrer_id = u.id
             WHERE u.is_blocked = 0
             GROUP BY u.id
             ORDER BY referral_count DESC
             LIMIT 20"
        )
    );

    $myRank = (int) DB::get()->fetchColumn(
        "SELECT COUNT(*) + 1 FROM (
             SELECT referrer_id, COUNT(*) as cnt
             FROM users WHERE referrer_id IS NOT NULL
             GROUP BY referrer_id
         ) t WHERE t.cnt > (
             SELECT COUNT(*) FROM users WHERE referrer_id = ?
         )",
        [$user['id']]
    );

    Response::success([
        'leaders' => $leaders,
        'my_rank' => $myRank,
        'type'    => 'referrals',
    ]);
}

function handleWeekly(array $user): never
{
    $leaders = Cache::remember('leaderboard:weekly', 120, fn () =>
        DB::get()->fetchAll(
            "SELECT u.first_name, u.username, u.level,
                    COALESCE(SUM(i.total_earned), 0) as weekly_earned
             FROM users u
             LEFT JOIN investments i ON i.user_id = u.id
                 AND i.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             WHERE u.is_blocked = 0
             GROUP BY u.id
             ORDER BY weekly_earned DESC
             LIMIT 20"
        )
    );

    $prizes = [
        ['place' => '🥇 1-o\'rin', 'prize' => '500 000 so\'m'],
        ['place' => '🥈 2-o\'rin', 'prize' => '300 000 so\'m'],
        ['place' => '🥉 3-o\'rin', 'prize' => '100 000 so\'m'],
        ['place' => '4-10 o\'rin', 'prize' => '25 000 so\'m'],
    ];

    Response::success([
        'leaders' => $leaders,
        'prizes'  => $prizes,
        'type'    => 'weekly',
        'ends_at' => date('Y-m-d', strtotime('next monday')),
    ]);
}

function handleMyRank(array $user): never
{
    $earnerRank = (int) DB::get()->fetchColumn(
        "SELECT COUNT(*) + 1 FROM users WHERE total_earned > ? AND is_blocked = 0",
        [$user['total_earned']]
    );
    $refCount = (int) DB::get()->fetchColumn(
        'SELECT COUNT(*) FROM users WHERE referrer_id = ?',
        [$user['id']]
    );
    $refRank = (int) DB::get()->fetchColumn(
        "SELECT COUNT(*) + 1 FROM (
             SELECT referrer_id, COUNT(*) as cnt
             FROM users WHERE referrer_id IS NOT NULL
             GROUP BY referrer_id
         ) t WHERE t.cnt > ?",
        [$refCount]
    );

    Response::success([
        'earner_rank'   => $earnerRank,
        'referral_rank' => $refRank,
        'total_earned'  => (float) $user['total_earned'],
        'referral_count'=> $refCount,
    ]);
}
