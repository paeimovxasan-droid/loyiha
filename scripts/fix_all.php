<?php
/**
 * TORTINMANG — Fayl tuzatish skripti
 *
 * Nima qiladi:
 *  1. BOM (EF BB BF) belgisini olib tashlaydi
 *  2. ?php → <?php (< belgisi yo'q bo'lsa)
 *  3. Har bir faylning birinchi 6 baytini chiqaradi
 *
 * Ishlatish: php scripts/fix_all.php
 */

$base = dirname(__DIR__);

$dirs = [
    $base,
    $base . '/admin',
    $base . '/api/v1',
    $base . '/core',
    $base . '/models',
    $base . '/bot',
    $base . '/jobs',
];

// scripts/ papkasidagi fayllarni o'zi ham tuzatadi (bu fayl bundan mustasno)
$dirs[] = $base . '/scripts';

$skip = ['fix_all.php', 'check_syntax.php', 'fix_wallet.php'];

$bomFixed    = [];
$tagFixed    = [];
$alreadyOk   = [];
$errors      = [];

foreach ($dirs as $dir) {
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        $bn = basename($file);
        if (in_array($bn, $skip, true)) continue;

        $raw     = file_get_contents($file);
        $changed = false;

        // 1. BOM olib tashlash (UTF-8 BOM = EF BB BF)
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw     = substr($raw, 3);
            $changed = true;
            $bomFixed[] = $bn;
        }

        // 2. ?php → <?php  (< belgisi yo'q bo'lsa)
        if (preg_match('/^\s*\?php\b/', $raw)) {
            $raw     = preg_replace('/^\s*\?php\b/', '<?php', $raw, 1);
            $changed = true;
            $tagFixed[] = $bn;
        }

        // 3. Natijani tekshir
        if (!preg_match('/^\s*<\?php\b/', $raw)) {
            $errors[] = $bn . ' [birinchi hex: ' . bin2hex(substr($raw, 0, 6)) . ']';
            continue;
        }

        if ($changed) {
            if (file_put_contents($file, $raw) === false) {
                $errors[] = $bn . ' [yozib bo\'lmadi!]';
            }
        } else {
            $alreadyOk[] = $bn;
        }
    }
}

// ── Hisobot ──────────────────────────────────────────────────
$line = str_repeat('─', 52);
echo "\n$line\n";
echo " TORTINMANG — fix_all.php hisoboti\n";
echo "$line\n\n";

$total = count($bomFixed) + count($tagFixed) + count($alreadyOk) + count($errors);
echo "Tekshirildi : $total fayl\n";
echo "Allaqachon OK: " . count($alreadyOk) . " ta\n\n";

if ($bomFixed) {
    echo "BOM olib tashlandi (" . count($bomFixed) . "):\n";
    foreach ($bomFixed as $f) echo "  ✔ $f\n";
    echo "\n";
}

if ($tagFixed) {
    echo "<?php tegilmasi tuzatildi (" . count($tagFixed) . "):\n";
    foreach ($tagFixed as $f) echo "  ✔ $f\n";
    echo "\n";
}

if (!$bomFixed && !$tagFixed) {
    echo "✅ Barcha fayllar to'g'ri — hech narsa tuzatilmadi.\n\n";
}

if ($errors) {
    echo "❌ XATOLAR (" . count($errors) . "):\n";
    foreach ($errors as $e) echo "  ! $e\n";
    echo "\n";
} else {
    echo "✅ Hech qanday xato yo'q.\n\n";
}

echo "$line\n\n";
