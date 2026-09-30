<?php
require_once __DIR__ . '/../bootstrap.php';

use Tortinmang\Core\DB;
use Tortinmang\Core\Logger;

// Non-invasive diagnostic: read-only checks for prize weights and sample selection
try {
    $db = DB::get();
    $prizes = $db->fetchAll('SELECT id,name,prize_type,amount,weight FROM lottery_prizes WHERE is_active = 1');
    echo "Active prizes: " . count($prizes) . "\n";
    foreach ($prizes as $p) {
        echo "- {$p['id']} {$p['name']} ({$p['prize_type']}) weight={$p['weight']} amount={$p['amount']}\n";
    }

    if (empty($prizes)) {
        echo "No active prizes found.\n";
        exit(0);
    }

    // Run 1000 sample selections to ensure distribution and no exceptions
    $counts = [];
    $totalWeight = array_sum(array_column($prizes,'weight')) ?: 1;
    for ($i=0;$i<1000;$i++){
        $rand = random_int(1, $totalWeight);
        $cum = 0; $sel = null;
        foreach ($prizes as $prize) {
            $cum += (int)$prize['weight'];
            if ($rand <= $cum) { $sel = $prize; break; }
        }
        $id = $sel['id'] ?? 0;
        $counts[$id] = ($counts[$id] ?? 0) + 1;
    }

    echo "Sample distribution (1000 runs):\n";
    foreach ($prizes as $p) {
        $c = $counts[$p['id']] ?? 0;
        $pct = round($c/10,2);
        echo "- {$p['id']} {$p['name']}: {$c} times (~{$pct}%)\n";
    }

    echo "Diagnostic complete. Check storage/logs for detailed lottery logs.\n";
} catch (Exception $e) {
    Logger::error('lottery_diagnostic failed: '.$e->getMessage());
    echo "Diagnostic failed: " . $e->getMessage() . "\n";
    exit(1);
}
