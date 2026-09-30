<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, Helpers, DB};
use Tortinmang\Models\{User, Investment};

$req    = new Request();
$ip     = $req->ip();
$action = $req->action('packages');

if (!RateLimiter::throttle($ip, 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

match ($action) {
    'packages'       => handlePackages(),
    'my_investments' => handleMyInvestments($req),
    'create'         => handleCreate($req, $ip),
    'stats'          => handleStats($req),
    default          => Response::error("Noma'lum amal"),
};

// ─── HANDLERS ────────────────────────────────────────────────

function handlePackages(): never
{
    $packages = DB::get()->fetchAll(
        'SELECT id, name, description, min_amount, max_amount, daily_percent, duration_days, icon, color
         FROM packages WHERE is_active = 1 ORDER BY sort_order ASC, min_amount ASC'
    );

    // Add calculated fields
    $packages = array_map(function (array $p): array {
        $p['example_daily']   = Helpers::dailyProfit(1_000_000, (float) $p['daily_percent']);
        $p['example_total']   = $p['example_daily'] * (int) $p['duration_days'];
        $p['total_percent']   = round((float) $p['daily_percent'] * (int) $p['duration_days'], 1);
        return $p;
    }, $packages);

    Response::success(['packages' => $packages]);
}

function handleMyInvestments(Request $req): never
{
    $user = Auth::getUser($req->initData());
    if (!$user) Response::unauthorized();

    $active   = Investment::findActive((int) $user['id']);
    $all      = Investment::findAll((int) $user['id']);
    $stats    = Investment::getStats((int) $user['id']);

    Response::success([
        'active'     => $active,
        'history'    => $all,
        'stats'      => $stats,
    ]);
}

function handleCreate(Request $req, string $ip): never
{
    $user = Auth::getUser($req->initData());
    if (!$user) Response::unauthorized();

    if (!RateLimiter::throttle($ip, 'invest', 10, 3600)) {
        Response::tooManyRequests();
    }

    $packageId = $req->getInt('package_id');
    $amount    = $req->getFloat('amount');

    if (!$packageId || $amount <= 0) {
        Response::error('Paket va summa kiriting');
    }

    $package = DB::get()->fetch(
        'SELECT * FROM packages WHERE id = ? AND is_active = 1',
        [$packageId]
    );
    if (!$package) Response::error('Paket topilmadi');

    $validator = \Tortinmang\Core\Validator::make($req->all())
        ->min('amount', (float) $package['min_amount'], 'Summa')
        ->max('amount', (float) $package['max_amount'], 'Summa');

    if ($validator->fails()) Response::error($validator->firstError());

    if ((float) $user['balance'] < $amount) {
        Response::error('Balans yetarli emas: ' . Helpers::money((float) $user['balance']));
    }

    $investment = Investment::create((int) $user['id'], $packageId, $amount);
    if (!$investment) Response::error('Investitsiya amalga oshirilmadi');

    $daily = Helpers::dailyProfit($amount, (float) $package['daily_percent']);

    Response::success(
        ['investment' => $investment],
        "🚀 Investitsiya muvaffaqiyatli! Kunlik daromad: +" . Helpers::money($daily)
    );
}

function handleStats(Request $req): never
{
    $user = Auth::getUser($req->initData());
    if (!$user) Response::unauthorized();

    Response::success([
        'stats' => Investment::getStats((int) $user['id'])
    ]);
}
