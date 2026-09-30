<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter};
use Tortinmang\Models\Referral;

$req    = new Request();
$action = $req->action('stats');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'stats'   => handleStats($user),
    'history' => handleHistory($user),
    'tree'    => handleTree($req, $user),
    default   => Response::error("Noma'lum amal"),
};

function handleStats(array $user): never
{
    Response::success(Referral::getStats((int) $user['id']));
}

function handleHistory(array $user): never
{
    Response::success([
        'history' => Referral::getEarningsHistory((int) $user['id'])
    ]);
}

function handleTree(Request $req, array $user): never
{
    $level = max(1, min(3, $req->getInt('level', 1)));
    Response::success([
        'level'    => $level,
        'referrals'=> Referral::getReferrals((int) $user['id'], $level)
    ]);
}
