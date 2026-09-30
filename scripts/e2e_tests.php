<?php
echo "E2E runner starting - before bootstrap\n";
require_once __DIR__ . '/../bootstrap.php';
echo "Bootstrap loaded\n";

use Tortinmang\Core\DB;
echo "After use statements\n";
use Tortinmang\Models\User;
use Tortinmang\Models\Transaction;
use Tortinmang\Core\Logger;

echo "Getting DB\n";
$db = DB::get();
echo "Got DB\n";
$db->beginTransaction();
echo "Began transaction\n";
try {
    echo "Starting E2E transaction (will rollback)\n";

    // 1) Create test user
    $username = 'test_user_' . time();
    $user = User::create([
        'telegram_id' => time(),
        'first_name'  => 'E2E',
        'last_name'   => 'Tester',
        'username'    => $username,
        'referral_code'=>null,
    ]);
    if (!$user) throw new Exception('User creation failed');
    $uid = (int)$user['id'];
    echo "Created user id={$uid}\n";

    // 2) Create deposit (pending) and approve it
    $depId = Transaction::createDeposit($uid, 10000.00, 'uzcard', 'e2e_test');
    if (!$depId) throw new Exception('Deposit creation failed');
    echo "Created deposit id={$depId}\n";

    $ok = Transaction::approveDeposit($depId, 'e2e approve');
    if (!$ok) throw new Exception('Approve deposit failed');
    echo "Approved deposit, balance should increase\n";

    $userAfter = User::find($uid);
    echo "Balance after deposit: ".($userAfter['balance'] ?? 'N/A')."\n";

    // 3) Create withdrawal and approve
    $wid = Transaction::createWithdrawal($uid, 2000.00, 'uzcard', '8600 0000 0000 0000');
    if (!$wid) throw new Exception('Withdrawal creation failed');
    echo "Created withdrawal id={$wid}\n";

    $ok = Transaction::approveWithdrawal($wid, 'e2e paid');
    if (!$ok) throw new Exception('Approve withdrawal failed');
    echo "Approved withdrawal\n";

    $userAfter2 = User::find($uid);
    echo "Balance after withdrawal: ".($userAfter2['balance'] ?? 'N/A')."\n";

    // 4) Ensure there is at least one lottery prize, otherwise create a temp one
    $prize = $db->fetch('SELECT * FROM lottery_prizes WHERE is_active = 1 LIMIT 1');
    $createdTempPrize = false;
    if (!$prize) {
        $pid = $db->insert('lottery_prizes', [
            'name' => 'E2E Cash',
            'prize_type' => 'balance',
            'amount' => 500.00,
            'weight' => 10,
            'is_active' => 1,
        ]);
        $prize = $db->fetch('SELECT * FROM lottery_prizes WHERE id = ?', [$pid]);
        $createdTempPrize = true;
        echo "Inserted temp prize id={$pid}\n";
    }

    // 5) Simulate a spin selection (weighted)
    $prizes = $db->fetchAll('SELECT * FROM lottery_prizes WHERE is_active = 1');
    $totalWeight = array_sum(array_column($prizes, 'weight'));
    $rand = random_int(1, max(1,$totalWeight));
    $cum = 0; $selected = null;
    foreach ($prizes as $p) {
        $cum += (int)$p['weight'];
        if ($rand <= $cum) { $selected = $p; break; }
    }
    if (!$selected) $selected = $prizes[0];
    echo "Selected prize: {$selected['name']} ({$selected['prize_type']})\n";

    if ($selected['prize_type'] === 'balance') {
        // apply earnings
        User::addEarnings($uid, (float)$selected['amount']);
        echo "Added earnings: {$selected['amount']}\n";
        $db->insert('lottery_spins', [
            'user_id' => $uid,
            'prize_id' => (int)$selected['id'],
            'result_amount' => (float)$selected['amount'],
            'spun_at' => date('Y-m-d H:i:s'),
        ]);
    } elseif ($selected['prize_type'] === 'spin') {
        $db->insert('lottery_spins', [
            'user_id' => $uid,
            'prize_id' => (int)$selected['id'],
            'result_amount' => 0.00,
            'spun_at' => date('Y-m-d H:i:s'),
        ]);
        echo "Granted bonus spin\n";
    } else {
        $db->insert('lottery_spins', [
            'user_id' => $uid,
            'prize_id' => (int)$selected['id'],
            'result_amount' => 0.00,
            'spun_at' => date('Y-m-d H:i:s'),
        ]);
        echo "No monetary prize\n";
    }

    $userFinal = User::find($uid);
    echo "Final balance: ".($userFinal['balance'] ?? 'N/A')."\n";

    // Done - rollback
    $db->rollBack();
    echo "Rolled back transaction, no test data persisted.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "E2E test failed: " . $e->getMessage() . "\n";
    exit(1);
}
