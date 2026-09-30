<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Config, TelegramBot};

$req = new Request();

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

$tg        = new TelegramBot();
$isMember  = $tg->isChannelMember((int) $user['telegram_id']);

Response::success([
    'is_member'   => $isMember,
    'channel_url' => Config::get('telegram.channel_url'),
    'channel_id'  => Config::get('telegram.channel_id'),
]);
