<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, DB, Helpers};
use Tortinmang\Models\User;

$req    = new Request();
$action = $req->action('redeem');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'redeem' => handleRedeem($req, $user),
    'check'  => handleCheck($req, $user),
    default  => Response::error("Noma'lum amal"),
};

// ─────────────────────────────────────────────────────────────

function handleRedeem(Request $req, array $user): never
{
    if (!RateLimiter::throttle($req->ip(), 'promo', 5, 3600)) {
        Response::tooManyRequests('Juda ko\'p promo so\'rovi. 1 soat kuting.');
    }

    $code   = strtoupper(trim($req->getString('code')));
    $db     = DB::get();
    $userId = (int) $user['id'];

    if (empty($code)) Response::error('Promo kodni kiriting');

    $promo = $db->fetch(
        "SELECT * FROM promo_codes WHERE code = ? AND is_active = 1",
        [$code]
    );

    if (!$promo) Response::error('Promo kod topilmadi yoki muddati tugagan');

    // Expiry check
    if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
        Response::error('Bu promo kod muddati tugagan');
    }

    // Usage limit check
    if ($promo['max_uses'] > 0 && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
        Response::error("Bu promo kod limitiga yetgan");
    }

    // Already used check
    $alreadyUsed = $db->fetchColumn(
        'SELECT COUNT(*) FROM promo_usages WHERE user_id = ? AND promo_id = ?',
        [$userId, $promo['id']]
    );
    if ((int) $alreadyUsed > 0) Response::error('Siz bu promo kodni allaqachon ishlatgansiz');

    $amount = (float) $promo['reward_amount'];

    $db->beginTransaction();
    try {
        User::addEarnings($userId, $amount);
        $db->insert('promo_usages', [
            'user_id'  => $userId,
            'promo_id' => (int) $promo['id'],
            'amount'   => $amount,
            'used_at'  => date('Y-m-d H:i:s'),
        ]);
        $db->query(
            'UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?',
            [$promo['id']]
        );
        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        Response::error('Xatolik yuz berdi');
    }

    Response::success(
        ['reward' => $amount],
        "🎁 Promo kod faollashtirildi! +" . Helpers::money($amount) . " 🎉"
    );
}

function handleCheck(Request $req, array $user): never
{
    $code   = strtoupper(trim($req->getString('code')));
    $db     = DB::get();
    $userId = (int) $user['id'];

    if (empty($code)) Response::error('Promo kodni kiriting');

    $promo = $db->fetch(
        "SELECT id, code, reward_amount, max_uses, used_count, expires_at FROM promo_codes WHERE code = ? AND is_active = 1",
        [$code]
    );

    if (!$promo) Response::error('Promo kod topilmadi');

    $isUsed = (int) $db->fetchColumn(
        'SELECT COUNT(*) FROM promo_usages WHERE user_id = ? AND promo_id = ?',
        [$userId, $promo['id']]
    ) > 0;

    Response::success([
        'code'       => $promo['code'],
        'amount'     => (float) $promo['reward_amount'],
        'is_used'    => $isUsed,
        'uses_left'  => $promo['max_uses'] > 0 ? max(0, $promo['max_uses'] - $promo['used_count']) : null,
        'expires_at' => $promo['expires_at'],
    ]);
}
