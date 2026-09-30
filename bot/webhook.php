<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tortinmang\Core\{Config, DB, TelegramBot, Logger, Helpers};
use Tortinmang\Models\{User, Referral};

// ── Verify webhook secret ──────────────────────────────────────
$secret = Config::get('telegram.webhook_secret', '');
if ($secret) {
    $incoming = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($secret, $incoming)) {
        http_response_code(403);
        exit;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) exit;

$bot = new TelegramBot();

$message       = $input['message']       ?? null;
$callbackQuery = $input['callback_query'] ?? null;

if ($callbackQuery) {
    handleCallbackQuery($callbackQuery, $bot);
    exit;
}

if (!$message) exit;

$chatId   = $message['chat']['id']    ?? 0;
$chatType = $message['chat']['type']  ?? 'private';
$text     = $message['text']          ?? '';
$userId   = $message['from']['id']    ?? 0;
$firstName= $message['from']['first_name'] ?? '';

// Contact (telefon raqami) yuborilgan
if (isset($message['contact'])) {
    handleContact($message['contact'], $userId, $chatId, $bot);
    exit;
}

if ($chatType !== 'private') {
    handleGroupMessage($chatId, $text, $userId, $firstName, $bot);
    exit;
}

// Private chat commands
if (str_starts_with($text, '/start')) {
    handleStart($chatId, $userId, $message, $text, $bot);
} elseif ($text === '/balance') {
    handleBalance($chatId, $userId, $bot);
} elseif ($text === '/referral') {
    handleReferral($chatId, $userId, $bot);
} elseif ($text === '/help') {
    handleHelp($chatId, $bot);
} elseif ($text === '/profile') {
    handleProfile($chatId, $userId, $bot);
} elseif ($text === '/invest') {
    handleInvest($chatId, $bot);
} elseif ($text === '/lottery') {
    handleLottery($chatId, $userId, $bot);
} elseif ($text === '/top') {
    handleTop($chatId, $bot);
} else {
    $bot->sendMessage($chatId,
        "Ilovani ochish uchun quyidagi tugmani bosing 👇",
        ['reply_markup' => mainKeyboard()]
    );
}

// ─── HANDLERS ────────────────────────────────────────────────

function handleStart(int $chatId, int $tgUserId, array $message, string $text, TelegramBot $bot): void
{
    $parts     = explode(' ', $text, 2);
    $startParam = trim($parts[1] ?? '');

    $userData = [
        'id'         => $tgUserId,
        'first_name' => $message['from']['first_name'] ?? '',
        'last_name'  => $message['from']['last_name']  ?? '',
        'username'   => $message['from']['username']   ?? '',
    ];

    $user = User::findByTelegramId($tgUserId);
    $isNew = !$user;

    if (!$user) {
        $referralCode = '';
        if ($startParam && strlen($startParam) === 8) {
            $referralCode = strtoupper($startParam);
        }
        $user = User::create([
            'telegram_id'   => $tgUserId,
            'first_name'    => mb_substr($userData['first_name'], 0, 100),
            'last_name'     => mb_substr($userData['last_name'],  0, 100),
            'username'      => mb_substr($userData['username'],   0, 100),
            'referral_code' => \Tortinmang\Core\Auth::generateReferralCode(),
            'referrer_id'   => $referralCode ? (function () use ($referralCode) {
                $ref = User::findByReferralCode($referralCode);
                return $ref ? (int) $ref['id'] : null;
            })() : null,
        ]);
    }

    // Telefon raqami tasdiqlanmagan bo'lsa — so'rash
    if ($user && empty($user['phone_verified'])) {
        $bot->sendMessage($chatId,
            "📱 *Tortinmang — Telefon tasdiqlash*\n\n" .
            "Xavfsizlik uchun telefon raqamingizni tasdiqlang.\n" .
            "Bu *bir marta* amalga oshiriladi va hisobingizni himoya qiladi.\n\n" .
            "⚠️ Diqqat: Har bir telefon raqami faqat *1 ta hisob*ga bog'lanadi.",
            [
                'reply_markup' => [
                    'keyboard' => [[
                        ['text' => '📱 Telefon raqamimni tasdiqlash', 'request_contact' => true]
                    ]],
                    'resize_keyboard'   => true,
                    'one_time_keyboard' => true,
                ],
            ]
        );
        return;
    }

    $miniappUrl = Config::get('app.miniapp_url');
    $channelUrl = Config::get('telegram.channel_url');

    $bot->sendMessage($chatId,
        "💎 *Tortinmang*ga xush kelibsiz, {$userData['first_name']}!\n\n" .
        "🚀 O'zbekistoning eng ishonchli investitsiya platformasi\n\n" .
        "✅ Kunlik daromad — har kuni balansingiz o'sadi\n" .
        "✅ 3 bosqichli referal — do'stlardan daromad\n" .
        "✅ VIP imtiyozlar — ko'proq foyda\n" .
        "✅ Lotereya & yutuqlar — har kun sovrin\n\n" .
        "👇 Ilovani oching va boshlang:",
        [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '📱 Ilovani ochish', 'web_app' => ['url' => $miniappUrl]]],
                    [['text' => '📢 Rasmiy kanal', 'url' => $channelUrl]],
                    [
                        ['text' => '👥 Referallar', 'callback_data' => 'ref'],
                        ['text' => '💰 Balans',     'callback_data' => 'bal'],
                    ],
                ],
            ],
        ]
    );
}

function handleBalance(int $chatId, int $tgUserId, TelegramBot $bot): void
{
    $user = User::findByTelegramId($tgUserId);
    if (!$user) {
        $bot->sendMessage($chatId, "Avval /start buyrug'ini yuboring.");
        return;
    }

    $available = max(0.0, (float) $user['total_earned'] - (float) $user['total_withdraw']);
    $levelInfo = User::getLevelInfo((int) $user['level']);
    $badgeInfo = User::getBadgeInfo($user['badge'] ?? 'yangi');

    $bot->sendMessage($chatId,
        "💰 *Tortinmang — Balansingiz*\n\n" .
        "👤 " . $user['first_name'] . "\n" .
        $badgeInfo['icon'] . " " . $badgeInfo['label'] . " | ⭐ " . $levelInfo['name'] . "\n\n" .
        "💵 Balans: *" . Helpers::money((float) $user['balance']) . "*\n" .
        "📈 Jami daromad: *" . Helpers::money((float) $user['total_earned']) . "*\n" .
        "📥 Jami depozit: *" . Helpers::money((float) $user['total_deposit']) . "*\n" .
        "📤 Jami yechilgan: *" . Helpers::money((float) $user['total_withdraw']) . "*\n" .
        "✅ Yechish mumkin: *" . Helpers::money($available) . "*",
        ['reply_markup' => ['inline_keyboard' => [[['text' => '📱 Ilovani ochish', 'web_app' => ['url' => Config::get('app.miniapp_url')]]]]]]
    );
}

function handleReferral(int $chatId, int $tgUserId, TelegramBot $bot): void
{
    $user = User::findByTelegramId($tgUserId);
    if (!$user) {
        $bot->sendMessage($chatId, "Avval /start buyrug'ini yuboring.");
        return;
    }

    $stats = Referral::getStats((int) $user['id']);
    $link  = $stats['referral_link'];

    $bot->sendMessage($chatId,
        "👥 *Tortinmang — Referal dasturi*\n\n" .
        "🔗 Sizning havolangiz:\n`{$link}`\n\n" .
        "📊 *Statistika:*\n" .
        "├ 1-daraja (" . Config::get('finance.ref_level_1') . "%): " . $stats['level_1'] . " kishi\n" .
        "├ 2-daraja (" . Config::get('finance.ref_level_2') . "%): " . $stats['level_2'] . " kishi\n" .
        "└ 3-daraja (" . Config::get('finance.ref_level_3') . "%): " . $stats['level_3'] . " kishi\n\n" .
        "💰 Jami referal daromad: *" . Helpers::money($stats['total_earnings']) . "*\n\n" .
        "Do'stingiz investitsiya qilganida siz avtomatik daromad olasiz! 🚀"
    );
}

function handleHelp(int $chatId, TelegramBot $bot): void
{
    $bot->sendMessage($chatId,
        "📖 *Tortinmang — Yordam*\n\n" .
        "🔹 /start — Botni ishga tushirish\n" .
        "🔹 /balance — Balansingizni ko'rish\n" .
        "🔹 /referral — Referal ma'lumotlari\n" .
        "🔹 /profile — Profilingiz\n" .
        "🔹 /invest — Paketlar ro'yxati\n" .
        "🔹 /lottery — Kunlik spin holati\n" .
        "🔹 /top — TOP 10 reyting\n" .
        "🔹 /help — Yordam\n\n" .
        "❓ Savol va muammolar uchun: @tortinmang_support\n" .
        "📢 Yangiliklar: @tortinmang_app"
    );
}

function handleInvest(int $chatId, TelegramBot $bot): void
{
    $db       = DB::get();
    $packages = $db->fetchAll("SELECT name, min_amount, max_amount, daily_percent, duration_days FROM packages WHERE is_active=1 ORDER BY sort_order ASC LIMIT 6");
    $miniappUrl = Config::get('app.miniapp_url');

    $msg = "📦 *Tortinmang — Investitsiya paketlari*\n\n";
    foreach ($packages as $p) {
        $msg .= "▸ *{$p['name']}* — {$p['daily_percent']}%/kun · {$p['duration_days']} kun\n";
        $msg .= "  " . Helpers::money((float)$p['min_amount']) . " – " . Helpers::money((float)$p['max_amount']) . "\n\n";
    }
    $msg .= "Investitsiya qilish uchun ilovani oching 👇";

    $bot->sendMessage($chatId, $msg, [
        'reply_markup' => ['inline_keyboard' => [
            [['text' => '📱 Ilovani ochish', 'web_app' => ['url' => $miniappUrl]]],
        ]],
    ]);
}

function handleLottery(int $chatId, int $tgUserId, TelegramBot $bot): void
{
    $user = User::findByTelegramId($tgUserId);
    if (!$user) {
        $bot->sendMessage($chatId, "Avval /start buyrug'ini yuboring.");
        return;
    }
    $db     = DB::get();
    $userId = (int) $user['id'];
    $used   = (int) $db->fetchColumn("SELECT COUNT(*) FROM lottery_spins WHERE user_id=? AND DATE(spun_at)=CURDATE()", [$userId]);
    $vip    = $db->fetch("SELECT tier FROM vip_memberships WHERE user_id=? AND is_active=1 AND expires_at>NOW() LIMIT 1", [$userId]);
    $cfg    = Config::get('vip_tiers', []);
    $max    = ($vip && isset($cfg[$vip['tier']])) ? (int)$cfg[$vip['tier']]['lottery_spins'] : 1;
    $left   = max(0, $max - $used);

    $msg = "🎰 *Tortinmang — Lotereya*\n\n";
    $msg .= "Bugungi spinlar: *{$left}/{$max}* qoldi\n\n";
    if ($left > 0) {
        $msg .= "Aylantirish uchun ilovani oching 👇";
    } else {
        $msg .= "Spinlar tugadi. Ertaga qaytib keling! ⏰";
    }

    $bot->sendMessage($chatId, $msg, [
        'reply_markup' => ['inline_keyboard' => [
            [['text' => '🎰 Ilovani ochish', 'web_app' => ['url' => Config::get('app.miniapp_url')]]],
        ]],
    ]);
}

function handleTop(int $chatId, TelegramBot $bot): void
{
    $db  = DB::get();
    $top = $db->fetchAll("SELECT first_name, total_earned FROM users WHERE is_blocked=0 ORDER BY total_earned DESC LIMIT 10");
    $medals = ['🥇','🥈','🥉'];
    $msg = "🏆 *Tortinmang — TOP 10 daromadchilar*\n\n";
    foreach ($top as $i => $u) {
        $m    = $medals[$i] ?? ($i + 1) . '.';
        $msg .= "{$m} {$u['first_name']} — " . Helpers::money((float)$u['total_earned']) . "\n";
    }
    $bot->sendMessage($chatId, $msg, [
        'reply_markup' => ['inline_keyboard' => [
            [['text' => '📱 Ilovani ochish', 'web_app' => ['url' => Config::get('app.miniapp_url')]]],
        ]],
    ]);
}

function handleProfile(int $chatId, int $tgUserId, TelegramBot $bot): void
{
    $user = User::findByTelegramId($tgUserId);
    if (!$user) {
        $bot->sendMessage($chatId, "Avval /start buyrug'ini yuboring.");
        return;
    }

    $levelInfo = User::getLevelInfo((int) $user['level']);
    $badgeInfo = User::getBadgeInfo($user['badge'] ?? 'yangi');
    $streak    = (int) $user['daily_bonus_streak'];

    $db     = DB::get();
    $refs   = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE referrer_id = ?", [$user['id']]);
    $actInv = (int) $db->fetchColumn("SELECT COUNT(*) FROM investments WHERE user_id = ? AND status='active'", [$user['id']]);

    $bot->sendMessage($chatId,
        "👤 *Tortinmang — Profil*\n\n" .
        "Ism: *{$user['first_name']} {$user['last_name']}*\n" .
        "Badge: {$badgeInfo['icon']} *{$badgeInfo['label']}*\n" .
        "Daraja: ⭐ *{$levelInfo['name']}*\n\n" .
        "💰 Balans: *" . Helpers::money((float) $user['balance']) . "*\n" .
        "📈 Jami daromad: *" . Helpers::money((float) $user['total_earned']) . "*\n" .
        "📦 Faol investitsiyalar: *{$actInv}*\n" .
        "👥 Referallar: *{$refs}*\n" .
        "🔥 Streak: *{$streak} kun*",
        ['reply_markup' => ['inline_keyboard' => [[['text' => '📱 Ilovani ochish', 'web_app' => ['url' => Config::get('app.miniapp_url')]]]]]]
    );
}

function handleCallbackQuery(array $cq, TelegramBot $bot): void
{
    $chatId = $cq['message']['chat']['id'] ?? 0;
    $userId = $cq['from']['id']            ?? 0;
    $data   = $cq['data']                  ?? '';

    $bot->answerCallbackQuery($cq['id']);

    match ($data) {
        'bal' => handleBalance($chatId, $userId, $bot),
        'ref' => handleReferral($chatId, $userId, $bot),
        default => null,
    };
}

function handleGroupMessage(int $chatId, string $text, int $userId, string $firstName, TelegramBot $bot): void
{
    $db = DB::get();

    if ($text === '/stats@tortinmang_bot' || $text === '/stats') {
        $users  = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_blocked = 0");
        $actInv = (int) $db->fetchColumn("SELECT COUNT(*) FROM investments WHERE status = 'active'");
        $paid   = (float) $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='approved'");

        $bot->sendMessage($chatId,
            "📊 *Tortinmang — Statistika*\n\n" .
            "👥 Foydalanuvchilar: *{$users}*\n" .
            "📈 Faol investitsiyalar: *{$actInv}*\n" .
            "💸 Jami to'langan: *" . Helpers::money($paid) . "*"
        );
    } elseif ($text === '/top@tortinmang_bot' || $text === '/top') {
        $top = $db->fetchAll(
            "SELECT first_name, total_earned FROM users WHERE is_blocked = 0 ORDER BY total_earned DESC LIMIT 10"
        );
        $msg    = "🏆 *Tortinmang TOP 10*\n\n";
        $medals = ['🥇', '🥈', '🥉'];
        foreach ($top as $i => $t) {
            $m    = $medals[$i] ?? ($i + 1) . '.';
            $msg .= "{$m} {$t['first_name']} — " . Helpers::money((float) $t['total_earned']) . "\n";
        }
        $bot->sendMessage($chatId, $msg);
    }
}

function handleContact(array $contact, int $tgUserId, int $chatId, TelegramBot $bot): void
{
    // Faqat o'z kontakti bo'lsa qabul qilinadi
    $contactUserId = $contact['user_id'] ?? 0;
    if ($contactUserId !== $tgUserId) {
        $bot->sendMessage($chatId, "❌ Faqat *o'z* telefon raqamingizni yuboring.");
        return;
    }

    $phone = preg_replace('/[^0-9+]/', '', $contact['phone_number'] ?? '');
    if (empty($phone)) {
        $bot->sendMessage($chatId, "❌ Telefon raqami noto'g'ri.");
        return;
    }

    $db   = DB::get();
    $user = User::findByTelegramId($tgUserId);
    if (!$user) {
        $bot->sendMessage($chatId, "❌ Hisob topilmadi. /start ni qayta bosing.");
        return;
    }

    // Bu telefon boshqa aktiv hisobda bormi?
    $existing = $db->fetch(
        "SELECT id, telegram_id FROM users WHERE phone = ? AND id != ? AND is_blocked = 0 LIMIT 1",
        [$phone, $user['id']]
    );

    if ($existing) {
        // Bloklash va xabar
        $db->update('users', ['is_blocked' => 1], 'id = ?', [$user['id']]);
        $bot->sendMessage($chatId,
            "🚫 *Hisob bloklandi*\n\n" .
            "Bu telefon raqami allaqachon boshqa hisobda ro'yxatdan o'tgan.\n\n" .
            "Yordam uchun: @tortinmang_support",
            ['reply_markup' => ['remove_keyboard' => true]]
        );
        Logger::warning('Phone duplicate blocked', [
            'new_user_id'      => $user['id'],
            'existing_user_id' => $existing['id'],
            'phone'            => substr($phone, 0, 4) . '****',
        ]);
        return;
    }

    // Telefon raqamini saqlash va tasdiqlash
    $db->update('users', [
        'phone'          => $phone,
        'phone_verified' => 1,
        'device_registered_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$user['id']]);

    $miniappUrl = Config::get('app.miniapp_url');

    $bot->sendMessage($chatId,
        "✅ *Telefon tasdiqlandi!*\n\n" .
        "Hisob muvaffaqiyatli himoya qilindi.\n" .
        "Endi ilovadan to'liq foydalanishingiz mumkin! 🎉",
        [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '📱 Ilovani ochish', 'web_app' => ['url' => $miniappUrl]]],
                ],
                'remove_keyboard' => true,
            ],
        ]
    );
}

function mainKeyboard(): array
{
    return [
        'inline_keyboard' => [
            [['text' => '📱 Ilovani ochish', 'web_app' => ['url' => Config::get('app.miniapp_url')]]],
        ],
    ];
}
