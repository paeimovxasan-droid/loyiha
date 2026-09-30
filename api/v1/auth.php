<?php
declare(strict_types=1);

// JSON header — eng avval set qilamiz, hech narsa chiqmasin
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    require_once __DIR__ . '/../../bootstrap.php';
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Bootstrap xatosi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Config, Logger, DB};
use Tortinmang\Models\User;

try {
    $req = new Request();
    $ip  = $req->ip();

    if (!RateLimiter::throttle($ip, 'auth', 30, 60)) {
        echo json_encode(['success' => false, 'message' => 'Juda ko\'p so\'rov'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $initData     = $req->initData();
    $startParam   = $req->getString('start_param');
    $referralCode = $req->getString('referral_code', $startParam);

    if (empty($initData)) {
        echo json_encode(['success' => false, 'message' => 'initData talab qilinadi'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = Auth::getUser($initData, $referralCode);

    if (!$user) {
        // Log detailed debug server-side, but return a generic message to client
        Logger::error('Auth failed', ['ip' => $ip, 'len' => strlen($initData), 'preview' => substr($initData, 0, 150)]);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Init data validation failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $vip = DB::get()->fetch(
        "SELECT tier, expires_at FROM vip_memberships WHERE user_id=? AND is_active=1 AND expires_at>NOW() ORDER BY expires_at DESC LIMIT 1",
        [$user['id']]
    );

    $userData        = User::publicData($user);
    $userData['vip'] = $vip ? ['tier' => $vip['tier'], 'expires_at' => $vip['expires_at']] : null;

    $channelId  = Config::get('telegram.channel_id', '');
    $isMember   = true;
    if ($channelId) {
        $isMember = (new \Tortinmang\Core\TelegramBot())->isChannelMember((int) $user['telegram_id']);
    }
    $userData['channel_member']    = $isMember;
    $userData['channel_url']       = Config::get('telegram.channel_url', '');
    $userData['channel_id']        = $channelId;
    // Telefon tasdiq holati — miniapp da ogohlantirish uchun
    $userData['phone_verified']    = (bool) ($user['phone_verified'] ?? false);
    $userData['phone_required']    = !(bool) ($user['phone_verified'] ?? false);

    echo json_encode(['success' => true, 'user' => $userData], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    Logger::error('auth.php exception: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    echo json_encode(['success' => false, 'message' => 'Server xatosi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
