<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Config, DB, Helpers};
use Tortinmang\Models\User;

$req    = new Request();
$action = $req->action('status');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'status' => handleStatus($user),
    'spin'   => handleSpin($req, $user),
    default  => Response::error("Noma'lum amal"),
};

// ─────────────────────────────────────────────────────────────

function getMaxSpins(array $user): int
{
    $vipTiers = Config::get('vip_tiers', []);
    $vip = DB::get()->fetch(
        "SELECT tier FROM vip_memberships WHERE user_id = ? AND is_active = 1 AND expires_at > NOW() LIMIT 1",
        [$user['id']]
    );

    if ($vip && isset($vipTiers[$vip['tier']])) {
        return (int) $vipTiers[$vip['tier']]['lottery_spins'];
    }
    return 1; // default 1 spin per day
}

function getSpinsToday(int $userId): int
{
    return (int) DB::get()->fetchColumn(
        "SELECT COUNT(*) FROM lottery_spins WHERE user_id = ? AND DATE(spun_at) = CURDATE()",
        [$userId]
    );
}

function getBonusSpins(int $userId): int
{
    return (int) DB::get()->fetchColumn(
        "SELECT COUNT(*) FROM lottery_spins ls
         JOIN lottery_prizes lp ON ls.prize_id = lp.id
         WHERE ls.user_id = ? AND lp.prize_type = 'spin' AND DATE(ls.spun_at) = CURDATE()",
        [$userId]
    );
}

function handleStatus(array $user): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];

    $maxSpins   = getMaxSpins($user);
    $usedSpins  = getSpinsToday($userId);
    $bonusSpins = getBonusSpins($userId);
    $spinsLeft  = max(0, $maxSpins - $usedSpins + $bonusSpins);

    $prizes = $db->fetchAll(
        "SELECT id, name, prize_type, amount, weight FROM lottery_prizes WHERE is_active = 1 ORDER BY weight DESC"
    );

    $totalWeight = array_sum(array_column($prizes, 'weight'));
    $prizesForDisplay = array_map(function (array $p) use ($totalWeight): array {
        $chance = $totalWeight > 0 ? round($p['weight'] / $totalWeight * 100, 1) : 0;
        return [
            'label'  => $p['name'],
            'amount' => (float) $p['amount'],
            'type'   => $p['prize_type'],
            'chance' => $chance . '%',
        ];
    }, $prizes);

    $recentWins = $db->fetchAll(
        "SELECT u.first_name, ls.result_amount
         FROM lottery_spins ls
         JOIN users u ON ls.user_id = u.id
         WHERE ls.result_amount > 0
         ORDER BY ls.spun_at DESC
         LIMIT 10"
    );

    Response::success([
        'spins_left'  => $spinsLeft,
        'max_spins'   => $maxSpins,
        'prizes'      => $prizesForDisplay,
        'recent_wins' => $recentWins,
    ]);
}

function handleSpin(Request $req, array $user): never
{
    if (!RateLimiter::throttle($req->ip(), 'lottery', 10, 86400)) {
        Response::tooManyRequests('Kunlik lotereya limitiga yetdingiz');
    }

    $db     = DB::get();
    $userId = (int) $user['id'];

    // Telefon tasdiqlanmagan bo'lsa spin yo'q
    if (empty($user['phone_verified'])) {
        Response::error("Lotereya uchun avval botda telefon raqamingizni tasdiqlang.");
    }

    $maxSpins   = getMaxSpins($user);
    $usedSpins  = getSpinsToday($userId);
    $bonusSpins = getBonusSpins($userId);

    // Bonus spin zanjiri: kuniga max 3 ta bonus spin (settings dan)
    $maxBonusRaw = $db->fetchColumn(
        "SELECT setting_value FROM settings WHERE setting_key='max_bonus_spins_per_day'"
    );
    $maxBonusSpins = $maxBonusRaw !== false ? (int)$maxBonusRaw : 3;
    $bonusSpins = min($bonusSpins, $maxBonusSpins);

    $available = max(0, $maxSpins - $usedSpins + $bonusSpins);

    if ($available <= 0) {
        Response::error("Bugungi spin tugadi. Ertaga {$maxSpins} ta spin bor! ⏰");
    }

    // Weighted random prize selection
    $prizes = $db->fetchAll(
        "SELECT * FROM lottery_prizes WHERE is_active = 1"
    );

    if (empty($prizes)) Response::error('Sovrinlar mavjud emas');

    $totalWeight = (int) array_sum(array_column($prizes, 'weight'));
    $rand        = random_int(1, max(1,$totalWeight));
    \Tortinmang\Core\Logger::info('lottery.spin.selection_debug', [
        'user_id' => $userId,
        'totalWeight' => $totalWeight,
        'rand' => $rand,
        'prizes' => array_map(fn($p)=>['id'=>$p['id'],'weight'=>(int)$p['weight']], $prizes),
    ]);
    $cumulative  = 0;
    $selectedPrize = null;

    foreach ($prizes as $prize) {
        $cumulative += (int) $prize['weight'];
        if ($rand <= $cumulative) {
            $selectedPrize = $prize;
            break;
        }
    }

    if (!$selectedPrize) {
        \Tortinmang\Core\Logger::warning('lottery.spin.no_selected_prize', ['user_id'=>$userId,'rand'=>$rand,'total'=>$totalWeight]);
        $selectedPrize = $prizes[0];
    }

    $prizeAmount = 0.0;
    $prizeType   = $selectedPrize['prize_type'];
    $message     = '';

    // Log diagnostic info for troubleshooting prize selection and spin accounting
    \Tortinmang\Core\Logger::info('lottery.spin.attempt', [
        'user_id' => $userId,
        'max_spins' => $maxSpins,
        'used_spins' => $usedSpins,
        'bonus_spins' => $bonusSpins,
        'available' => $available,
        'ip' => $req->ip(),
    ]);

    $db->beginTransaction();
    try {
        if ($prizeType === 'balance' && (float) $selectedPrize['amount'] > 0) {
            $prizeAmount = (float) $selectedPrize['amount'];
            User::addEarnings($userId, $prizeAmount);
            $message = "🎉 Omadingiz! +" . Helpers::money($prizeAmount);
        } elseif ($prizeType === 'spin') {
            $message = '🎰 +1 qo\'shimcha spin!';
        } else {
            $message = '😅 Omad kutib turing! Keyingi safar yutasiz!';
        }

        $db->insert('lottery_spins', [
            'user_id'       => $userId,
            'prize_id'      => (int) $selectedPrize['id'],
            'result_amount' => $prizeType === 'spin' ? 0.00 : $prizeAmount,
            'spun_at'       => date('Y-m-d H:i:s'),
        ]);

        \Tortinmang\Core\Logger::info('lottery.spin.result', [
            'user_id' => $userId,
            'prize_id' => (int)$selectedPrize['id'],
            'prize_type' => $prizeType,
            'prize_amount' => $prizeAmount,
            'spins_left_before' => $available,
        ]);

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        Response::error('Xatolik yuz berdi');
    }

    $newUsed   = $usedSpins + 1;
    $spinsLeft = max(0, $maxSpins + $bonusSpins - $newUsed);

    // Notify user on win
    if ($prizeType === 'balance' && $prizeAmount > 0) {
        $u = \Tortinmang\Core\DB::get()->fetch('SELECT telegram_id FROM users WHERE id=?', [$userId]);
        if ($u) {
            (new \Tortinmang\Core\TelegramBot())->notifyLotteryWin((int)$u['telegram_id'], $selectedPrize['name'], $prizeAmount);
        }
    }

    Response::success([
        'prize_name'   => $selectedPrize['name'],
        'prize_type'   => $prizeType,
        'prize_amount' => $prizeAmount,
        'spins_left'   => $spinsLeft,
    ], $message);
}
