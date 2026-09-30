<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Config, Helpers, DB, TelegramBot};
use Tortinmang\Models\{User, Transaction};

$req    = new Request();
$ip     = $req->ip();
$action = $req->action('balance');

// Global rate limit
if (!RateLimiter::throttle($ip, 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

// Authenticate for all actions
$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

$userId = (int) $user['id'];

match ($action) {
    'balance'         => handleBalance($user),
    'deposit_info'    => handleDepositInfo(),
    'deposit_create'  => handleDepositCreate($req, $user, $ip),
    'deposit_history' => handleDepositHistory($userId),
    'withdraw_create' => handleWithdrawCreate($req, $user, $ip),
    'withdraw_history'=> handleWithdrawHistory($userId),
    'daily_bonus'     => handleDailyBonus($user),
    default           => Response::error("Noma'lum amal"),
};

// ─── HANDLERS ────────────────────────────────────────────────

function handleBalance(array $user): never
{
    $userData = User::publicData($user);
    $invStats = \Tortinmang\Models\Investment::getStats((int) $user['id']);
    Response::success([
        'user'   => $userData,
        'invest' => $invStats,
    ]);
}

function handleDepositInfo(): never
{
    $cfg = Config::get('finance');
    $db  = DB::get();

    // Settings jadvaldan olish — false va '' ni to'g'ri handle qilish
    $getS = function(string $k, string $def) use ($db): string {
        $val = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key=?", [$k]);
        return ($val !== false && $val !== '') ? (string)$val : $def;
    };

    $uzcard = $getS('deposit_card_uzcard', (string)($cfg['deposit_card_uzcard'] ?? ''));
    $humo   = $getS('deposit_card_humo',   (string)($cfg['deposit_card_humo']   ?? ''));
    $holder = $getS('deposit_card_holder', (string)($cfg['deposit_card_holder'] ?? ''));

    // deposit_enabled: yo'q yoki '1' → true
    $enabledRaw = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='deposit_enabled'");
    $isEnabled  = ($enabledRaw === false) ? true : ((int)$enabledRaw === 1);

    Response::success([
        'card_uzcard' => $uzcard,
        'card_humo'   => $humo,
        'card_holder' => $holder,
        'min_amount'  => (int)($cfg['min_deposit'] ?? 10000),
        'max_amount'  => (int)($cfg['max_deposit'] ?? 50000000),
        'is_enabled'  => $isEnabled,
    ]);
}

function handleDepositCreate(Request $req, array $user, string $ip): never
{
    // Check deposit enabled
    $enabledRaw = DB::get()->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='deposit_enabled'");
    if ($enabledRaw !== false && (int)$enabledRaw < 1) {
        Response::error("Depozit hozircha o'chirilgan");
    }

    // Rate limit: 5 deposits per hour
    if (!RateLimiter::throttle($ip, 'deposit', 5, 3600)) {
        Response::tooManyRequests("Juda ko'p depozit so'rovi. 1 soat kuting.");
    }

    $validator = \Tortinmang\Core\Validator::make($req->all())
        ->required('amount', 'Summa')
        ->positive('amount', 'Summa')
        ->min('amount', Config::get('finance.min_deposit'), 'Summa')
        ->max('amount', Config::get('finance.max_deposit'), 'Summa')
        ->required('receipt_info', "Chek ma'lumoti")
        ->minLength('receipt_info', 3, "Chek ma'lumoti")
        ->in('card_type', ['uzcard', 'humo'], 'Karta turi');

    if ($validator->fails()) Response::error($validator->firstError());

    // Max 3 pending deposits per hour
    $pending = Transaction::getPendingCount((int) $user['id'], 'deposit');
    if ($pending >= 3) Response::error("Kutilayotgan so'rovlaringiz bor. Admin tasdiqlashini kuting.");

    $id = Transaction::createDeposit(
        (int) $user['id'],
        $req->getFloat('amount'),
        $req->getString('card_type', 'uzcard'),
        $req->getString('receipt_info')
    );

    Response::success(['deposit_id' => $id], "To'lov so'rovi yuborildi! Admin 1-2 soat ichida tasdiqlaydi. ✅");
}

function handleDepositHistory(int $userId): never
{
    Response::success([
        'deposits' => Transaction::getUserDeposits($userId)
    ]);
}

function handleWithdrawCreate(Request $req, array $user, string $ip): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];

    $enabledRaw = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='withdrawal_enabled'");
    if ($enabledRaw !== false && (int)$enabledRaw < 1) {
        Response::error("Yechish hozircha o'chirilgan");
    }

    // Telefon tasdiqlanmagan bo'lsa yechishga ruxsat yo'q
    if (empty($user['phone_verified'])) {
        Response::error("Yechish uchun avval botda telefon raqamingizni tasdiqlang. /start bosing.");
    }

    // Yechish uchun kamida 1 ta tasdiqlangan depozit bo'lishi shart
    $requireDepRaw = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='require_deposit_for_withdraw'");
    $requireDep    = $requireDepRaw !== false ? (int)$requireDepRaw : 1;
    if ($requireDep) {
        $depCount = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM deposits WHERE user_id=? AND status='approved'",
            [$userId]
        );
        if ($depCount < 1) {
            Response::error("Yechish uchun kamida 1 ta tasdiqlangan depozit bo'lishi kerak.");
        }
        // Minimal depozit summasi
        $minDepRaw = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='min_deposit_before_withdraw'");
        $minDep    = $minDepRaw !== false ? (float)$minDepRaw : 10000.0;
        $totalDep  = (float) $user['total_deposit'];
        if ($totalDep < $minDep) {
            Response::error("Yechish uchun kamida " . \Tortinmang\Core\Helpers::money($minDep) . " depozit qilingan bo'lishi kerak.");
        }
    }

    if (!RateLimiter::throttle($ip, 'withdraw', 5, 3600)) {
        Response::tooManyRequests("Juda ko'p yechish so'rovi. 1 soat kuting.");
    }

    $cfg    = Config::get('finance');
    $amount = $req->getFloat('amount');

    $validator = \Tortinmang\Core\Validator::make($req->all())
        ->required('amount', 'Summa')
        ->positive('amount', 'Summa')
        ->min('amount', $cfg['min_withdraw'], 'Summa')
        ->max('amount', $cfg['max_withdraw'], 'Summa')
        ->required('card_number', 'Karta raqami')
        ->cardNumber('card_number')
        ->in('card_type', ['uzcard', 'humo'], 'Karta turi');

    if ($validator->fails()) Response::error($validator->firstError());

    // Foydalanuvchi faqat ishlagan pulini yecha oladi (depozit qaytmaydi)
    $available = max(0.0, (float) $user['total_earned'] - (float) $user['total_withdraw']);
    if ($amount > $available) {
        Response::error('Yechish mumkin (faqat ishlagan): ' . Helpers::money($available));
    }

    // Balance tekshiruvi
    if ((float) $user['balance'] < $amount) {
        Response::error('Balansingiz yetarli emas: ' . Helpers::money((float) $user['balance']));
    }

    // Kunlik limit
    $todayTotal = Transaction::getTodayWithdrawalTotal($userId);
    if (($todayTotal + $amount) > $cfg['daily_withdraw_limit']) {
        $remaining = max(0.0, $cfg['daily_withdraw_limit'] - $todayTotal);
        Response::error('Kunlik limit: ' . Helpers::money($cfg['daily_withdraw_limit']) . '. Qolgan: ' . Helpers::money($remaining));
    }

    // Max 2 pending
    $pending = Transaction::getPendingCount($userId, 'withdrawal');
    if ($pending >= 2) Response::error("Kutilayotgan so'rovlaringiz bor. Admin tasdiqlashini kuting.");

    $id = Transaction::createWithdrawal(
        $userId,
        $amount,
        $req->getString('card_type', 'uzcard'),
        $req->getString('card_number')
    );

    if (!$id) Response::error("Balans yetarli emas");

    Response::success(['withdrawal_id' => $id], "Yechish so'rovi yuborildi! Admin 1-24 soat ichida ko'rib chiqadi. ⏳");
}

function handleWithdrawHistory(int $userId): never
{
    Response::success([
        'withdrawals' => Transaction::getUserWithdrawals($userId)
    ]);
}

function handleDailyBonus(array $user): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];
    $today  = date('Y-m-d');

    // Telefon tasdiqlanmagan bo'lsa bonus yo'q
    if (empty($user['phone_verified'])) {
        Response::error("Bonus olish uchun avval botda telefon raqamingizni tasdiqlang. /start bosing.");
    }

    if ($user['last_bonus_date'] === $today) {
        Response::error('Kunlik bonus allaqachon olindi. Ertaga qaytib keling! 🌅');
    }

    // VIP multiplier
    $vip = $db->fetch(
        "SELECT tier FROM vip_memberships WHERE user_id = ? AND is_active = 1 AND expires_at > NOW() LIMIT 1",
        [$userId]
    );
    $vipTiers   = Config::get('vip_tiers', []);
    $multiplier = 1;
    if ($vip && isset($vipTiers[$vip['tier']])) {
        $multiplier = (int) $vipTiers[$vip['tier']]['daily_bonus_multiplier'];
    }

    $baseBonus = (int) Config::get('finance.daily_bonus_base', 1000);
    $streak    = (int) $user['daily_bonus_streak'];
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Streak
    $newStreak = ($user['last_bonus_date'] === $yesterday) ? $streak + 1 : 1;

    // Streak multiplier: +10% per day, lekin settings dan max olish (default 3x)
    $maxStreakRaw  = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='max_daily_bonus_streak_multiplier'");
    $maxStreakMult = $maxStreakRaw !== false ? (float)$maxStreakRaw : 3.0;
    $streakBonus   = min($newStreak * 0.1, $maxStreakMult - 1.0);
    $totalBonus    = (int) round($baseBonus * (1 + $streakBonus) * $multiplier);

    $db->beginTransaction();
    try {
        User::addEarnings($userId, $totalBonus);
        $db->update('users', [
            'last_bonus_date'    => $today,
            'daily_bonus_streak' => $newStreak,
        ], 'id = ?', [$userId]);
        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        Response::error('Xatolik yuz berdi');
    }

    // Telegram xabari — network timeout/xatosi response ni bloklamamasligi uchun try/catch
    try {
        $uRow = $db->fetch('SELECT telegram_id FROM users WHERE id=?', [$userId]);
        if ($uRow) {
            (new TelegramBot())->notifyBonus((int)$uRow['telegram_id'], $totalBonus, $newStreak);
        }
    } catch (\Throwable $tgErr) {
        \Tortinmang\Core\Logger::warning('Bonus notify failed', ['err' => $tgErr->getMessage(), 'user_id' => $userId]);
    }

    Response::success([
        'bonus'      => $totalBonus,
        'streak'     => $newStreak,
        'multiplier' => $multiplier,
    ], "🎁 Kunlik bonus: +" . Helpers::money($totalBonus) . " | Streak: {$newStreak} kun 🔥");
}
