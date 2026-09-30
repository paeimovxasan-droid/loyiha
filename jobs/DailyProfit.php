<?php
declare(strict_types=1);

/**
 * TORTINMANG — Daily Profit Cron Job
 *
 * Run via cron every day at 00:05:
 *   5 0 * * * php /path/to/tortinmang/jobs/DailyProfit.php >> /path/to/tortinmang/storage/logs/cron.log 2>&1
 *
 * Or via HTTP (secured by key):
 *   GET/POST https://app.tortinmang.uz/jobs/DailyProfit.php?key=SECRET
 */

require_once __DIR__ . '/../bootstrap.php';

use Tortinmang\Core\{Config, DB, Logger};
use Tortinmang\Models\Investment;

// ── Security ──────────────────────────────────────────────────
$isCli  = PHP_SAPI === 'cli';
$secret = md5(Config::get('telegram.bot_token', '') . date('Y-m-d'));

if (!$isCli) {
    $key = $_GET['key'] ?? $_POST['key'] ?? '';
    if (!hash_equals($secret, $key)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$startTime = microtime(true);
Logger::info('DailyProfit cron started');

// ── Main ──────────────────────────────────────────────────────
$result = Investment::processDaily();

// ── Fake feed generation ──────────────────────────────────────
$fakeCount = generateFakeFeed();

// ── Badge + level update ──────────────────────────────────────
updateAllBadges();

// ── Cleanup ───────────────────────────────────────────────────
\Tortinmang\Core\Cache::purgeExpired();

$elapsed = round(microtime(true) - $startTime, 2);

$output = [
    'success'    => true,
    'date'       => date('Y-m-d H:i:s'),
    'processed'  => $result['processed'],
    'completed'  => $result['completed'],
    'errors'     => $result['errors'],
    'fake_feed'  => $fakeCount,
    'elapsed_s'  => $elapsed,
];

Logger::info('DailyProfit cron finished', $output);

if ($isCli) {
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
}

// ─────────────────────────────────────────────────────────────

function generateFakeFeed(): int
{
    $db    = DB::get();
    $names = [
        'Aziz', 'Sardor', 'Dilshod', 'Bekzod', 'Jasur',
        'Sherzod', 'Nodir', 'Alisher', 'Bobur', 'Temur',
        'Malika', 'Nilufar', 'Gulnora', 'Shahlo', 'Madina',
        'Kamol', 'Zafar', 'Rustam', 'Doniyor', 'Firdavs',
    ];
    $amounts = [50000, 100000, 200000, 300000, 500000, 750000, 1000000, 1500000, 2000000, 3000000];
    $count   = random_int(5, 12);

    for ($i = 0; $i < $count; $i++) {
        $name   = $names[array_rand($names)] . ' ' . chr(random_int(65, 90)) . '.';
        $amount = $amounts[array_rand($amounts)];
        $type   = random_int(0, 2) === 0 ? 'deposit' : 'withdraw';

        $db->insert('payment_feed', [
            'user_display_name' => $name,
            'amount'            => $amount,
            'feed_type'         => $type,
            'is_fake'           => 1,
            'created_at'        => date('Y-m-d H:i:s', time() - random_int(0, 86400)),
        ]);
    }

    // Keep only last 200 entries
    $db->query(
        "DELETE FROM payment_feed WHERE id NOT IN (SELECT id FROM (SELECT id FROM payment_feed ORDER BY created_at DESC LIMIT 200) t)"
    );

    return $count;
}

function updateAllBadges(): void
{
    $db      = DB::get();
    $userIds = $db->fetchAll("SELECT id FROM users WHERE is_blocked = 0");

    foreach ($userIds as $row) {
        \Tortinmang\Models\User::updateBadge((int) $row['id']);
        \Tortinmang\Models\User::updateLevel((int) $row['id']);
    }
}
