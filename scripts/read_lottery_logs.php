<?php
// Read latest lottery-related entries from today's log
$log = __DIR__ . '/../storage/logs/' . date('Y-m-d') . '.log';
if (!file_exists($log)) { echo "No log file for today\n"; exit(0); }
$lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$found = 0;
// Show up to last 200 lines and filter lottery-related entries
foreach (array_slice($lines, -200) as $l) {
    if (stripos($l, 'lottery') !== false || stripos($l, 'lottery.') !== false) { echo $l . "\n"; $found++; }
}
if ($found === 0) echo "No lottery entries in recent logs\n";
