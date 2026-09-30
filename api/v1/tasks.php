<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Auth, Request, Response, RateLimiter, DB, Helpers, TelegramBot};
use Tortinmang\Models\User;

$req    = new Request();
$action = $req->action('list');

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$user = Auth::getUser($req->initData());
if (!$user) Response::unauthorized();

match ($action) {
    'list'     => handleList($user),
    'complete' => handleComplete($req, $user),
    default    => Response::error("Noma'lum amal"),
};

function handleList(array $user): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];
    $today  = date('Y-m-d');

    $tasks = $db->fetchAll(
        "SELECT t.*,
            CASE
                WHEN t.task_type = 'daily' THEN
                    (SELECT COUNT(*) FROM task_completions tc WHERE tc.task_id = t.id AND tc.user_id = ? AND DATE(tc.completed_at) = ?)
                ELSE
                    (SELECT COUNT(*) FROM task_completions tc WHERE tc.task_id = t.id AND tc.user_id = ?)
            END AS is_completed
         FROM tasks t
         WHERE t.is_active = 1
         ORDER BY t.sort_order ASC, t.id ASC",
        [$userId, $today, $userId]
    );

    Response::success(['tasks' => $tasks]);
}

function handleComplete(Request $req, array $user): never
{
    $db     = DB::get();
    $userId = (int) $user['id'];
    $taskId = $req->getInt('task_id');

    if (!RateLimiter::throttle($req->ip(), 'task', 20, 3600)) {
        Response::tooManyRequests();
    }

    if (!$taskId) Response::error('Task ID kiriting');

    $task = $db->fetch('SELECT * FROM tasks WHERE id = ? AND is_active = 1', [$taskId]);
    if (!$task) Response::error('Vazifa topilmadi');

    $today = date('Y-m-d');

    // Check already completed
    if ($task['task_type'] === 'daily') {
        $done = $db->fetchColumn(
            'SELECT COUNT(*) FROM task_completions WHERE task_id = ? AND user_id = ? AND DATE(completed_at) = ?',
            [$taskId, $userId, $today]
        );
        if ((int) $done > 0) Response::error('Bugungi vazifa allaqachon bajarildi. Ertaga qaytib keling!');
    } else {
        $done = $db->fetchColumn(
            'SELECT COUNT(*) FROM task_completions WHERE task_id = ? AND user_id = ?',
            [$taskId, $userId]
        );
        if ((int) $done > 0) Response::error('Bu vazifa allaqachon bajarilgan');
    }

    // Special checks
    $actionType = $task['action_type'] ?? '';

    if ($actionType === 'subscribe') {
        $tg = new TelegramBot();
        if (!$tg->isChannelMember((int) $user['telegram_id'])) {
            Response::error("Avval kanalga obuna bo'ling, so'ng tekshiring!");
        }
    }

    if ($actionType === 'deposit') {
        $depCount = $db->fetchColumn(
            "SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'approved'",
            [$userId]
        );
        if ((int) $depCount < 1) Response::error("Avval depozit kiriting!");
    }

    if ($actionType === 'referral') {
        $refCount = $db->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE referrer_id = ?',
            [$userId]
        );
        if ((int) $refCount < 1) Response::error("Avval kamida 1 ta dost taklif qiling!");
    }

    // URL / visit / share / channel / group — foydalanuvchi havolani ochgan bo'lishi kerak
    // Frontend click_time (Unix timestamp) yuboradi, server kamida 3 soniya o'tganini tekshiradi
    if (in_array($actionType, ['url', 'visit', 'share', 'channel', 'group'], true)) {
        $clickTime = $req->getInt('click_time', 0);
        if ($clickTime <= 0) {
            // click_time umuman yuborilmagan — tekshiruvni o'tkazmagan
            Response::error("Avval havolani oching, so'ng vazifani tasdiqlang!");
        }
        $elapsed = time() - $clickTime;
        if ($elapsed < 3) {
            Response::error("Sahifada kamida 3 soniya bo'ling!");
        }
        if ($elapsed > 3600) {
            // 1 soatdan eski click — eskirgan, qayta bossin
            Response::error("Muddati o'tgan. Havolani qayta oching!");
        }
    }

    $reward = (float) $task['reward'];

    $db->beginTransaction();
    try {
        $db->insert('task_completions', [
            'user_id'      => $userId,
            'task_id'      => $taskId,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($reward > 0) {
            User::addEarnings($userId, $reward);
        }

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        Response::error('Xatolik yuz berdi');
    }

    $msg = $reward > 0
        ? "✅ Vazifa bajarildi! +" . Helpers::money($reward) . " 🎉"
        : "✅ Vazifa bajarildi!";

    Response::success(['reward' => $reward], $msg);
}
