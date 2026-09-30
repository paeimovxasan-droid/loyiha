<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, DB, Helpers};
use Tortinmang\Models\User;

$req    = new Request();
$action = $req->action('list');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'list'  => handleList($user),
    'claim' => handleClaim($req, $user),
    default => Response::error("Noma'lum amal"),
};

// ─────────────────────────────────────────────────────────────

function checkRequirement(array $user, array $ach): bool
{
    $db     = DB::get();
    $userId = (int) $user['id'];
    $val    = (float) $ach['requirement_value'];

    return match ($ach['requirement_type']) {
        'deposits_count'    => (int) $db->fetchColumn("SELECT COUNT(*) FROM deposits WHERE user_id=? AND status='approved'", [$userId]) >= $val,
        'referrals_count'   => (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE referrer_id=?", [$userId]) >= $val,
        'streak_days'       => (int) $user['daily_bonus_streak'] >= $val,
        'total_earned'      => (float) $user['total_earned'] >= $val,
        'investments_count' => (int) $db->fetchColumn("SELECT COUNT(*) FROM investments WHERE user_id=?", [$userId]) >= $val,
        default             => false,
    };
}

function handleList(array $user): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];

    $achievements = $db->fetchAll(
        "SELECT a.*,
                ua.id        AS ua_id,
                ua.claimed   AS claimed,
                ua.unlocked_at
         FROM achievements a
         LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
         WHERE a.is_active = 1
         ORDER BY a.sort_order ASC, a.id ASC",
        [$userId]
    );

    // Pre-calculate all progress values in one pass (avoid N+1 queries)
    $progressCache = [
        'deposits_count'    => (int)   $db->fetchColumn("SELECT COUNT(*) FROM deposits WHERE user_id=? AND status='approved'", [$userId]),
        'referrals_count'   => (int)   $db->fetchColumn("SELECT COUNT(*) FROM users WHERE referrer_id=?", [$userId]),
        'streak_days'       => (int)   $user['daily_bonus_streak'],
        'total_earned'      => (float) $user['total_earned'],
        'investments_count' => (int)   $db->fetchColumn("SELECT COUNT(*) FROM investments WHERE user_id=?", [$userId]),
    ];

    $result   = [];
    $unlocked = 0;

    foreach ($achievements as $ach) {
        $isUnlocked  = !empty($ach['ua_id']);
        $isClaimed   = (int) ($ach['claimed'] ?? 0) === 1;
        $canClaim    = false;

        if (!$isUnlocked) {
            $canClaim = checkRequirement($user, $ach);
        }

        if ($isUnlocked) $unlocked++;

        $progress = $progressCache[$ach['requirement_type']] ?? 0;

        $result[] = [
            'id'               => (int) $ach['id'],
            'key'              => $ach['achievement_key'],
            'name'             => $ach['name'],
            'description'      => $ach['description'],
            'icon'             => $ach['icon'],
            'reward'           => (float) $ach['reward'],
            'progress'         => $progress,
            'requirement_type' => $ach['requirement_type'],
            'requirement_value'=> (float) $ach['requirement_value'],
            'is_unlocked'      => $isUnlocked,
            'is_claimed'       => $isClaimed,
            'can_claim'        => $canClaim,
            'unlocked_at'      => $ach['unlocked_at'] ?? null,
        ];
    }

    Response::success([
        'achievements'      => $result,
        'total_unlocked'    => $unlocked,
        'total_achievements'=> count($result),
    ]);
}

function handleClaim(Request $req, array $user): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];
    $key    = $req->getString('achievement_key');

    if (empty($key)) Response::error('achievement_key kiriting');

    $ach = $db->fetch(
        "SELECT * FROM achievements WHERE achievement_key = ? AND is_active = 1",
        [$key]
    );
    if (!$ach) Response::error('Yutuq topilmadi');

    // Check already claimed
    $existing = $db->fetch(
        "SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?",
        [$userId, $ach['id']]
    );

    if ($existing && (int) $existing['claimed'] === 1) {
        Response::error('Bu yutuq allaqachon olindi');
    }

    // Check requirement
    if (!checkRequirement($user, $ach)) {
        Response::error('Yutuq shartlari bajarilmagan');
    }

    $reward = (float) $ach['reward'];

    $db->beginTransaction();
    try {
        if ($existing) {
            $db->update('user_achievements', [
                'claimed'    => 1,
                'claimed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $existing['id']]);
        } else {
            $db->insert('user_achievements', [
                'user_id'        => $userId,
                'achievement_id' => (int) $ach['id'],
                'claimed'        => 1,
                'unlocked_at'    => date('Y-m-d H:i:s'),
                'claimed_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        if ($reward > 0) {
            User::addEarnings($userId, $reward);
        }

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        Response::error('Xatolik yuz berdi');
    }

    Response::success(
        ['reward' => $reward],
        "🏆 Yutuq olindi: {$ach['name']}! +" . Helpers::money($reward)
    );
}
