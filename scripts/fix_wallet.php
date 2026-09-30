<?php
$file = dirname(__DIR__) . '/api/v1/wallet.php';
$raw  = file_get_contents($file);

// BOM olib tashlash
if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
    $raw = substr($raw, 3);
}

// ?php → <?php (birinchi qatorda)
if (str_starts_with(ltrim($raw), '?php')) {
    // ltrim bo'sh joylarni olib tashlaydi, keyin almashtirish
    $raw = preg_replace('/^\s*\?php/', '<?php', $raw, 1);
    echo "Fixed: ?php -> <?php\n";
}

file_put_contents($file, $raw);
echo "First 30 chars: " . substr($raw, 0, 30) . "\n";
echo "Done.\n";
