<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Config, DB, Helpers, TelegramBot};
use Tortinmang\Models\User;

$req    = new Request();
$action = $req->action('tiers');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'tiers'     => handleTiers($user),
    'my_status' => handleMyStatus($user),
    'purchase'  => handlePurchase($req, $user),
    default     => Response::error("Noma'lum amal"),
};

// ─────────────────────────────────────────────────────────────

function getCurrentVip(int $userId): ?array
{
    $row = DB::get()->fetch(
        "SELECT * FROM vip_memberships WHERE user_id = ? AND is_active = 1 AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1",
        [$userId]
    );
    return $row ?: null;
}

function handleTiers(array $user): never
{
    $tiers      = Config::get('vip_tiers', []);
    $currentVip = getCurrentVip((int) $user['id']);

    Response::success([
        'tiers'       => array_values($tiers),
        'current_vip' => $currentVip ? [
            'tier'       => $currentVip['tier'],
            'expires_at' => $currentVip['expires_at'],
            'started_at' => $currentVip['started_at'],
        ] : null,
        'user_balance' => (float) $user['balance'],
    ]);
}

function handleMyStatus(array $user): never
{
    $currentVip = getCurrentVip((int) $user['id']);

    if (!$currentVip) {
        Response::success(['has_vip' => false]);
    }

    $tiers    = Config::get('vip_tiers', []);
    $tierInfo = $tiers[$currentVip['tier']] ?? null;  // @phpstan-ignore-line (never null here)

    Response::success([
        'has_vip'    => true,
        'tier'       => $currentVip['tier'],
        'tier_info'  => $tierInfo,
        'expires_at' => $currentVip['expires_at'],
        'started_at' => $currentVip['started_at'],
    ]);
}

function handlePurchase(Request $req, array $user): never
{
    $tierId  = $req->getString('tier_id');
    $tiers   = Config::get('vip_tiers', []);
    $db      = DB::get();

    if (!isset($tiers[$tierId])) {
        Response::error("Noto'g'ri VIP daraja");
    }

    $tierInfo = $tiers[$tierId];
    $price    = (float) $tierInfo['price'];
    $userId   = (int) $user['id'];

    if ((float) $user['balance'] < $price) {
        Response::error('Balans yetarli emas. Kerakli summa: ' . Helpers::money($price));
    }

    // Check already active
    $current = getCurrentVip($userId);
    if ($current && $current['tier'] === $tierId) {
        Response::error("Bu VIP daraja allaqachon faol (tugash: {$current['expires_at']})");
    }

    $expiresAt = '';
    $db->beginTransaction();
    try {
        User::deductBalance($userId, $price);

        // Deactivate old VIP
        $db->query(
            "UPDATE vip_memberships SET is_active = 0 WHERE user_id = ? AND is_active = 1",
            [$userId]
        );

        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int) $tierInfo['duration_days'] . ' days'));
        $db->insert('vip_memberships', [
            'user_id'    => $userId,
            'tier'       => $tierId,
            'expires_at' => $expiresAt,
            'started_at' => date('Y-m-d H:i:s'),
            'is_active'  => 1,
        ]);

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        Response::error('Xatolik yuz berdi');
    }

    // Notify
    (new TelegramBot())->notifyVipPurchase((int) $user['telegram_id'], $tierInfo['name']);

    Response::success([
        'tier'       => $tierId,
        'tier_name'  => $tierInfo['name'],
        'expires_at' => $expiresAt ?? '',
    ], "🎉 Tabriklaymiz! {$tierInfo['name']} faollashtirildi! 30 kun davomida barcha imtiyozlar sizniki!");
}
