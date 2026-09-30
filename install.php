<?php
declare(strict_types=1);
/**
 * TORTINMANG - O'rnatish Wizardi
 * Faqat bir marta ishlatiladi. Tugagach DARHOL o'chiring!
 */

// Session avval boshlanadi — ini_set dan oldin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
define('INSTALL_VERSION', '1.0.0');
define('BASE', __DIR__);

if (!isset($_SESSION['install_key'])) {
    $_SESSION['install_key'] = bin2hex(random_bytes(16));
}
$installKey = $_SESSION['install_key'];

if (($_GET['key'] ?? '') !== $installKey && ($_SESSION['install_auth'] ?? false) !== true) {
    if (($_POST['install_key'] ?? '') === $installKey) {
        $_SESSION['install_auth'] = true;
    } else {
        showKeyForm($installKey);
        exit;
    }
}
$_SESSION['install_auth'] = true;

$step = (int)($_GET['step'] ?? 1);

// ── Yordamchi funksiyalar ────────────────────────────────────

function isValidDbId(string $v): bool {
    return (bool)preg_match('/^[A-Za-z0-9_]+$/', $v);
}

function isValidDbHost(string $v): bool {
    return (bool)preg_match('/^[A-Za-z0-9._:-]+$/', $v);
}

function checkRequirements(): array {
    $r = [];
    $r[] = ['PHP 8.0+',     version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION];
    $r[] = ['PDO MySQL',    extension_loaded('pdo_mysql'),  extension_loaded('pdo_mysql')  ? 'OK' : 'Yoq'];
    $r[] = ['cURL',         extension_loaded('curl'),       extension_loaded('curl')       ? 'OK' : 'Yoq'];
    $r[] = ['JSON',         extension_loaded('json'),       extension_loaded('json')       ? 'OK' : 'Yoq'];
    $r[] = ['OpenSSL',      extension_loaded('openssl'),    extension_loaded('openssl')    ? 'OK' : 'Yoq'];
    $r[] = ['mbstring',     extension_loaded('mbstring'),   extension_loaded('mbstring')   ? 'OK' : 'Yoq'];
    $r[] = ['storage/cache/', is_writable(BASE.'/storage/cache/') || @mkdir(BASE.'/storage/cache/', 0755, true), 'Papka'];
    $r[] = ['storage/logs/',  is_writable(BASE.'/storage/logs/')  || @mkdir(BASE.'/storage/logs/',  0755, true), 'Papka'];
    // config.php mavjud YOKI install jarayonida yaratiladi — install uchun shart emas
    $configOk = file_exists(BASE.'/config.php') || is_writable(BASE.'/') || is_writable(dirname(BASE.'/config.php'));
    $r[] = ['config.php (yozish)',  $configOk, $configOk ? 'Yozish mumkin' : 'Yozib bo\'lmaydi'];
    return $r;
}

function installDB(array $cfg): array {
    $res = ['success' => false, 'message' => '', 'count' => 0];
    if (!isValidDbHost($cfg['host']) || !isValidDbId($cfg['name']) || !isValidDbId($cfg['user'])) {
        $res['message'] = "DB konfiguratsiyasi notogri";
        return $res;
    }
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};charset=utf8mb4",
            $cfg['user'], $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // Bazani yaratishga urinib ko'ramiz, bo'lmasa mavjud bazaga ulanamiz
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $createEx) {
            // CREATE DATABASE huquqi yo'q bo'lishi mumkin (shared hosting) — mavjud bazadan foydalanib ko'ramiz
        }
        $pdo->exec("USE `{$cfg['name']}`");
        $sql   = file_get_contents(BASE.'/database/schema.sql');
        // Faqat qator boshidagi kommentlarni olib tashlash (string ichidagi -- ga tegmaymiz)
        $sql   = preg_replace('/^--[^\n]*$/m', '', $sql);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        $n = 0;
        foreach ($stmts as $stmt) {
            $clean = trim($stmt);
            if (!empty($clean)) {
                try { $pdo->exec($stmt); $n++; } catch(\Exception $e) { /* skip duplicate */ }
            }
        }
        $res['success'] = true;
        $res['message'] = "Baza muvaffaqiyatli yaratildi";
        $res['count']   = $n;
    } catch (\Exception $e) {
        $res['message'] = $e->getMessage();
    }
    return $res;
}

function writeConfig(array $d): bool {
    // webhook_secret bo'sh bo'lsa bo'sh qoldiramiz — webhook secretsiz ishlaydi
    $webhookSecret = $d['webhook_secret'] ?? '';

    $c  = '<?php' . "\ndeclare(strict_types=1);\nreturn [\n";
    $c .= "    'app' => [\n";
    $c .= "        'name'            => 'Tortinmang',\n";
    $c .= "        'url'             => '" . addslashes($d['app_url']) . "',\n";
    $c .= "        'miniapp_url'     => '" . addslashes($d['app_url']) . "/miniapp/',\n";
    $c .= "        'env'             => 'production',\n";
    $c .= "        'debug'           => false,\n";
    $c .= "        'trusted_proxies' => [],\n";
    $c .= "        'install_secret'  => '" . bin2hex(random_bytes(16)) . "',\n";
    $c .= "        'debug_secret'    => '" . bin2hex(random_bytes(16)) . "',\n";
    $c .= "    ],\n";
    $c .= "    'db' => [\n";
    $c .= "        'host'    => '" . addslashes($d['db_host']) . "',\n";
    $c .= "        'name'    => '" . addslashes($d['db_name']) . "',\n";
    $c .= "        'user'    => '" . addslashes($d['db_user']) . "',\n";
    $c .= "        'pass'    => '" . addslashes($d['db_pass']) . "',\n";
    $c .= "        'charset' => 'utf8mb4',\n";
    $c .= "    ],\n";
    $c .= "    'telegram' => [\n";
    $c .= "        'bot_token'      => '" . addslashes($d['bot_token']) . "',\n";
    $c .= "        'bot_username'   => '" . addslashes($d['bot_username']) . "',\n";
    $c .= "        'webhook_secret' => '" . addslashes($webhookSecret) . "',\n";
    $c .= "        'channel_id'     => '" . addslashes($d['channel_id']) . "',\n";
    $c .= "        'channel_url'    => '" . addslashes($d['channel_url']) . "',\n";
    $c .= "    ],\n";
    $c .= "    'finance' => [\n";
    $c .= "        'min_deposit'          => " . (int)$d['min_deposit'] . ",\n";
    $c .= "        'max_deposit'          => 50000000,\n";
    $c .= "        'min_withdraw'         => " . (int)$d['min_withdraw'] . ",\n";
    $c .= "        'max_withdraw'         => 5000000,\n";
    $c .= "        'daily_withdraw_limit' => 10000000,\n";
    $c .= "        'ref_level_1'          => 10,\n";
    $c .= "        'ref_level_2'          => 5,\n";
    $c .= "        'ref_level_3'          => 2,\n";
    $c .= "        'daily_bonus_base'     => " . (int)$d['daily_bonus'] . ",\n";
    $c .= "        'platform_commission'  => 5,\n";
    $c .= "        'deposit_card_uzcard'  => '" . addslashes($d['card_uzcard']) . "',\n";
    $c .= "        'deposit_card_humo'    => '" . addslashes($d['card_humo']) . "',\n";
    $c .= "        'deposit_card_holder'  => '" . addslashes($d['card_holder']) . "',\n";
    $c .= "    ],\n";
    $c .= "    'levels' => [1=>0,2=>100000,3=>500000,4=>1000000,5=>5000000,6=>10000000,7=>50000000,8=>100000000,9=>500000000,10=>1000000000],\n";
    $c .= "    'level_names' => [1=>'Yangi boshlovchi',2=>'Kichik investor',3=>\"O'rta investor\",4=>'Tajribali investor',5=>'Senior investor',6=>'Pro investor',7=>'Master investor',8=>'Grand Master',9=>'Legend',10=>'Tortinmang Elite'],\n";
    $c .= "    'vip_tiers' => [\n";
    $c .= "        'silver'  => ['id'=>'silver','name'=>'Silver VIP','price'=>100000,'duration_days'=>30,'daily_bonus_multiplier'=>2,'lottery_spins'=>2,'ref_bonus_extra'=>2,'benefits'=>['Kunlik bonus x2','Lotereya 2 marta/kun','Referal bonus +2%','Ustuvor qollab-quvvatlash']],\n";
    $c .= "        'gold'    => ['id'=>'gold','name'=>'Gold VIP','price'=>500000,'duration_days'=>30,'daily_bonus_multiplier'=>3,'lottery_spins'=>3,'ref_bonus_extra'=>5,'benefits'=>['Kunlik bonus x3','Lotereya 3 marta/kun','Referal bonus +5%','Maxsus paketlarga kirish','Tezkor pul yechish']],\n";
    $c .= "        'diamond' => ['id'=>'diamond','name'=>'Diamond VIP','price'=>2000000,'duration_days'=>30,'daily_bonus_multiplier'=>5,'lottery_spins'=>5,'ref_bonus_extra'=>10,'benefits'=>['Kunlik bonus x5','Lotereya 5 marta/kun','Referal bonus +10%','Barcha paketlarga kirish','Tezkor pul yechish','Shaxsiy menejer','Maxsus badge']],\n";
    $c .= "    ],\n";
    $c .= "    'rate_limits' => ['auth'=>['attempts'=>30,'window'=>60],'deposit'=>['attempts'=>5,'window'=>3600],'withdraw'=>['attempts'=>5,'window'=>3600],'invest'=>['attempts'=>10,'window'=>3600],'task'=>['attempts'=>20,'window'=>3600],'lottery'=>['attempts'=>10,'window'=>86400],'promo'=>['attempts'=>5,'window'=>3600],'api_global'=>['attempts'=>120,'window'=>60]],\n";
    $c .= "    'storage' => ['cache_dir'=>__DIR__.'/storage/cache/','log_dir'=>__DIR__.'/storage/logs/'],\n";
    $c .= "];\n";

    // BOM siz UTF-8 bilan yozish — file_put_contents ishlatiladi (SplFileObject ba'zi hosting da to'liq yozmaydi)
    $result = file_put_contents(BASE.'/config.php', $c, LOCK_EX);
    if ($result === false) {
        return false;
    }
    return file_exists(BASE.'/config.php') && filesize(BASE.'/config.php') > 100;
}

// webhook_secret bo'sh bo'lsa secretsiz, bo'lmasa secret bilan o'rnatadi
function setWebhook(string $token, string $url, string $secret): array {
    $apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";
    $params = ['url' => $url.'/bot/webhook.php'];
    if (!empty($secret)) {
        $params['secret_token'] = $secret;
    }
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode((string)$res, true) ?: ['ok' => false];
}

// Settings jadvalini install paytida to'ldirish
function updateSettings(array $d): void {
    $dbCfg = [
        'host' => $d['db_host'],
        'name' => $d['db_name'],
        'user' => $d['db_user'],
        'pass' => $d['db_pass'],
    ];
    if (!isValidDbHost($dbCfg['host']) || !isValidDbId($dbCfg['name']) || !isValidDbId($dbCfg['user'])) {
        return;
    }
    try {
        $pdo = new PDO(
            "mysql:host={$dbCfg['host']};dbname={$dbCfg['name']};charset=utf8mb4",
            $dbCfg['user'], $dbCfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $updates = [
            'deposit_card_uzcard'  => $d['card_uzcard'] ?? '',
            'deposit_card_humo'    => $d['card_humo']   ?? '',
            'deposit_card_holder'  => $d['card_holder'] ?? '',
        ];
        foreach ($updates as $key => $val) {
            if (empty($val)) continue;
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $stmt->execute([$key, $val]);
        }
    } catch (\Exception $e) {
        // Settings table bo'lmasa yoki ulanish yo'q — o'tkazib yuboramiz
    }
}

// Bot tokenini tekshirib, haqiqiy bot username ni oladi
function getBotUsername(string $token): string {
    $ch = curl_init("https://api.telegram.org/bot{$token}/getMe");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string)$res, true);
    return $data['result']['username'] ?? '';
}

function setAdminPassword(array $dbCfg, string $username, string $password): bool {
    if (!isValidDbHost($dbCfg['host']) || !isValidDbId($dbCfg['name']) || !isValidDbId($dbCfg['user'])) {
        return false;
    }
    try {
        $pdo  = new PDO(
            "mysql:host={$dbCfg['host']};dbname={$dbCfg['name']};charset=utf8mb4",
            $dbCfg['user'], $dbCfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $exists = $pdo->prepare("SELECT id FROM admin_users WHERE username=?");
        $exists->execute([$username]);
        if ($exists->fetch()) {
            $s = $pdo->prepare("UPDATE admin_users SET password_hash=?,login_attempts=0,locked_until=NULL WHERE username=?");
            $s->execute([$hash, $username]);
        } else {
            $s = $pdo->prepare("INSERT INTO admin_users (username,password_hash) VALUES (?,?)");
            $s->execute([$username, $hash]);
        }
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

// ── POST handlers ────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step2') {
        $cfg = [
            'host' => trim($_POST['db_host'] ?? 'localhost'),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => $_POST['db_pass'] ?? '',
        ];
        $r = installDB($cfg);
        $_SESSION['db_cfg']       = $cfg;
        $_SESSION['step2_result'] = $r;
        if ($r['success']) {
            header('Location: install.php?step=3'); exit;
        }
        header('Location: install.php?step=2'); exit;
    }

    if ($action === 'step3') {
        // db_cfg session bo'sh bo'lsa — step2 ga qaytarish
        if (empty($_SESSION['db_cfg']['name'])) {
            header('Location: install.php?step=2'); exit;
        }
        $botToken    = trim($_POST['bot_token'] ?? '');
        $botUsername = ltrim(trim($_POST['bot_username'] ?? ''), '@');

        // Agar bot_username bo'sh bo'lsa, tokendan avtomatik olamiz
        if (empty($botUsername) && !empty($botToken)) {
            $fetched = getBotUsername($botToken);
            if (!empty($fetched)) {
                $botUsername = $fetched;
            }
        }

        $d = [
            'app_url'        => rtrim(trim($_POST['app_url'] ?? ''), '/'),
            'db_host'        => $_SESSION['db_cfg']['host'] ?? 'localhost',
            'db_name'        => $_SESSION['db_cfg']['name'] ?? '',
            'db_user'        => $_SESSION['db_cfg']['user'] ?? '',
            'db_pass'        => $_SESSION['db_cfg']['pass'] ?? '',
            'bot_token'      => $botToken,
            'bot_username'   => $botUsername ?: 'tortinmang_bot',
            'webhook_secret' => '', // bo'sh — secretsiz webhook
            'channel_id'     => trim($_POST['channel_id'] ?? ''),
            'channel_url'    => trim($_POST['channel_url'] ?? ''),
            'card_uzcard'    => trim($_POST['card_uzcard'] ?? ''),
            'card_humo'      => trim($_POST['card_humo'] ?? ''),
            'card_holder'    => trim($_POST['card_holder'] ?? ''),
            'min_deposit'    => (int)($_POST['min_deposit'] ?? 10000),
            'min_withdraw'   => (int)($_POST['min_withdraw'] ?? 10000),
            'daily_bonus'    => (int)($_POST['daily_bonus'] ?? 1000),
        ];
        $_SESSION['cfg_data'] = $d;
        if (!writeConfig($d)) {
            $_SESSION['cfg_write_err'] = true;
            header('Location: install.php?step=3'); exit;
        }

        // Settings jadvalini ham yangilash (karta ma'lumotlari va boshqalar)
        updateSettings($d);

        header('Location: install.php?step=4'); exit;
    }

    if ($action === 'step4') {
        $d   = $_SESSION['cfg_data'] ?? [];
        // secret bo'sh — webhook secretsiz o'rnatiladi
        $wh  = setWebhook($d['bot_token'] ?? '', $d['app_url'] ?? '', '');
        $_SESSION['webhook_result'] = $wh;
        header('Location: install.php?step=5'); exit;
    }

    if ($action === 'step5') {
        $pass  = $_POST['admin_pass']  ?? '';
        $pass2 = $_POST['admin_pass2'] ?? '';
        $uname = trim($_POST['admin_user'] ?? 'admin');
        if ($pass !== $pass2 || strlen($pass) < 8) {
            $_SESSION['step5_err'] = 'Parollar mos emas yoki 8 belgidan kam';
            header('Location: install.php?step=5'); exit;
        }
        $_SESSION['step5_ok']   = setAdminPassword($_SESSION['db_cfg'] ?? [], $uname, $pass);
        $_SESSION['admin_user'] = $uname;
        header('Location: install.php?step=6'); exit;
    }
}

// ── HTML ─────────────────────────────────────────────────────

function showKeyForm(string $key): void { ?>
<!DOCTYPE html><html lang="uz"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tortinmang Install</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#020d07;font-family:system-ui,sans-serif;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.box{background:rgba(0,255,136,.04);border:1px solid rgba(0,255,136,.15);border-radius:20px;padding:40px;max-width:380px;width:100%}
h2{font-size:22px;font-weight:700;margin-bottom:8px;color:#00ff88}
p{font-size:13px;color:#64748b;margin-bottom:24px}
input{width:100%;padding:12px 14px;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#e2e8f0;font-size:14px;outline:none;margin-bottom:12px}
input:focus{border-color:#00ff88}
button{width:100%;padding:13px;background:linear-gradient(135deg,#00ff88,#00d4a0);color:#021a0c;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer}
.hint{font-size:11px;color:#475569;margin-top:12px;text-align:center;word-break:break-all}
</style></head><body><div class="box">
<h2>Tortinmang Install</h2>
<p>O'rnatishni boshlash uchun kalit kiriting</p>
<form method="POST">
<input type="text" name="install_key" placeholder="Kalitni kiriting" required autocomplete="off">
<button type="submit">Kirish</button>
</form>
<div class="hint">Kalit: <?=htmlspecialchars($key)?></div>
</div></body></html>
<?php }

function layout(string $title, string $body, int $step): void { ?>
<!DOCTYPE html><html lang="uz"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> - Tortinmang Install</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#020d07;font-family:system-ui,sans-serif;color:#e2e8f0;min-height:100vh;padding:20px}
.wrap{max-width:600px;margin:0 auto}
.brand{text-align:center;margin-bottom:28px}
.brand h1{font-size:24px;font-weight:900;color:#00ff88;letter-spacing:1px}
.brand p{font-size:12px;color:#475569;margin-top:4px}
.steps{display:flex;align-items:center;justify-content:center;gap:4px;margin-bottom:28px}
.st{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid rgba(255,255,255,.1);color:#475569;flex-shrink:0}
.st.done{background:#00cc66;border-color:#00cc66;color:#021a0c}
.st.active{background:#00ff88;border-color:#00ff88;color:#021a0c}
.line{flex:1;height:2px;background:rgba(255,255,255,.08)}
.line.done{background:#00cc66}
.card{background:rgba(0,255,136,.03);border:1px solid rgba(0,255,136,.1);border-radius:16px;padding:24px;margin-bottom:14px}
.card h2{font-size:16px;font-weight:700;margin-bottom:16px;color:#e2e8f0}
label{font-size:12px;color:#94a3b8;display:block;margin-bottom:4px;margin-top:14px}
label:first-of-type{margin-top:0}
input,select{width:100%;padding:11px 14px;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#e2e8f0;font-size:13px;outline:none;transition:border-color .2s}
input:focus,select:focus{border-color:#00ff88}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#00ff88,#00d4a0);color:#021a0c;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;margin-top:16px;display:block;text-align:center;text-decoration:none}
.btn:hover{opacity:.9}
.btn-sec{background:rgba(255,255,255,.05);color:#94a3b8;border:1px solid rgba(255,255,255,.1)}
.ok{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);color:#a7f3d0;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:10px}
.err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:10px}
.hint{font-size:11px;color:#475569;margin-top:6px;line-height:1.5}
code{background:rgba(0,0,0,.4);padding:2px 6px;border-radius:4px;font-size:11px;color:#00ff88}
</style></head><body><div class="wrap">
<div class="brand">
  <h1>TORTINMANG</h1>
  <p>O'rnatish Wizardi v<?=INSTALL_VERSION?></p>
</div>
<div class="steps">
<?php for($i=1;$i<=6;$i++): ?>
  <div class="st <?=$i<$step?'done':($i===$step?'active':'')?>">
    <?=$i<$step?'&#10003;':$i?>
  </div>
  <?php if($i<6): ?><div class="line <?=$i<$step?'done':''?>"></div><?php endif; ?>
<?php endfor; ?>
</div>
<?=$body?>
</div></body></html>
<?php }

// ── Step sahifalari ──────────────────────────────────────────

ob_start();

if ($step === 1):
    $checks = checkRequirements();
    $allOk  = !in_array(false, array_column($checks, 1));
?>
<div class="card">
  <h2>1. Tizim talablari</h2>
  <?php foreach ($checks as [$name, $ok, $val]): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px">
    <span><?=htmlspecialchars($name)?></span>
    <span style="color:<?=$ok?'#00ff88':'#ef4444'?>;font-weight:600"><?=$ok?'OK ('.$val.')':'YOQ'?></span>
  </div>
  <?php endforeach; ?>
  <?php if ($allOk): ?>
    <div class="ok" style="margin-top:12px">Barcha talablar bajarilgan!</div>
    <a class="btn" href="install.php?step=2">Keyingisi →</a>
  <?php else: ?>
    <div class="err" style="margin-top:12px">Ba'zi talablar bajarilmagan. Hostingda kerakli kengaytmalarni yoqing.</div>
  <?php endif; ?>
</div>

<?php elseif ($step === 2):
    $prev = $_SESSION['step2_result'] ?? null;
?>
<div class="card">
  <h2>2. Ma'lumotlar bazasi</h2>
  <?php if ($prev): ?>
    <div class="<?=$prev['success']?'ok':'err'?>">
      <?=$prev['success']?'':''?><?=htmlspecialchars($prev['message'])?>
      <?php if($prev['success']): ?> (<?=$prev['count']?> so'rov bajarildi)<?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="step2">
    <label>DB Host</label>
    <input name="db_host" value="localhost" placeholder="localhost">
    <label>Baza nomi</label>
    <input name="db_name" placeholder="masalan: user123_tort" required>
    <label>Foydalanuvchi</label>
    <input name="db_user" placeholder="masalan: user123_tort" required>
    <label>Parol</label>
    <input name="db_pass" type="password" placeholder="DB parol" autocomplete="new-password">
    <div class="hint">ISPManager: MySQL → Bazalar → Yangi. Yaratilgan baza nomi va userini kiriting.</div>
    <button class="btn" type="submit" style="margin-top:16px">Bazani yaratish →</button>
  </form>
</div>
<?php if ($prev && $prev['success']): ?>
  <a class="btn" href="install.php?step=3">Keyingisi (DB tayyor) →</a>
<?php endif; ?>

<?php elseif ($step === 3):
    // DB cfg bo'sh bo'lsa step2 ga yo'naltirish
    if (empty($_SESSION['db_cfg']['name'])) {
        header('Location: install.php?step=2'); exit;
    }
    $appUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'app.tortinmang.uz');
    $appUrl = rtrim(str_replace('/install.php', '', $appUrl), '/');
    $savedData = $_SESSION['cfg_data'] ?? [];
?>
<div class="card">
  <h2>3. Asosiy sozlamalar</h2>
  <?php if (!empty($_SESSION['cfg_write_err'])): unset($_SESSION['cfg_write_err']); ?>
    <div class="err">config.php yozib bo'lmadi. Fayl huquqlarini tekshiring (chmod 644).</div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="step3">
    <label>Sayt URL (https bilan, oxiridagi / siz)</label>
    <input name="app_url" value="<?=htmlspecialchars($savedData['app_url'] ?? $appUrl)?>" placeholder="https://app.tortinmang.uz" required>

    <label>Telegram Bot Token (@BotFather → /newbot)</label>
    <input name="bot_token" value="<?=htmlspecialchars($savedData['bot_token'] ?? '')?>" placeholder="1234567890:ABCdef..." required autocomplete="off">
    <div class="hint">Bot username avtomatik aniqlanadi. Qo'lda kiriting yoki bo'sh qoldiring.</div>

    <label>Bot Username (ixtiyoriy, @ siz — bo'sh = avtomatik)</label>
    <input name="bot_username" value="<?=htmlspecialchars($savedData['bot_username'] ?? '')?>" placeholder="app_tortinmangbot">

    <label>Kanal ID (majburiy a'zolik. Bo'sh = o'chirilgan)</label>
    <input name="channel_id" value="<?=htmlspecialchars($savedData['channel_id'] ?? '')?>" placeholder="@tortinmang_app">

    <label>Kanal URL</label>
    <input name="channel_url" value="<?=htmlspecialchars($savedData['channel_url'] ?? '')?>" placeholder="https://t.me/tortinmang_app">

    <label>UzCard raqami (depozit uchun)</label>
    <input name="card_uzcard" value="<?=htmlspecialchars($savedData['card_uzcard'] ?? '')?>" placeholder="8600 XXXX XXXX XXXX" required>

    <label>Humo raqami (ixtiyoriy)</label>
    <input name="card_humo" value="<?=htmlspecialchars($savedData['card_humo'] ?? '')?>" placeholder="9860 XXXX XXXX XXXX">

    <label>Karta egasi (F.I.O.)</label>
    <input name="card_holder" value="<?=htmlspecialchars($savedData['card_holder'] ?? '')?>" placeholder="Ism Familiya" required>

    <div class="row2">
      <div>
        <label>Min depozit (so'm)</label>
        <input name="min_deposit" type="number" value="<?=(int)($savedData['min_deposit'] ?? 10000)?>">
      </div>
      <div>
        <label>Min yechish (so'm)</label>
        <input name="min_withdraw" type="number" value="<?=(int)($savedData['min_withdraw'] ?? 10000)?>">
      </div>
    </div>

    <label>Kunlik bonus bazasi (so'm)</label>
    <input name="daily_bonus" type="number" value="<?=(int)($savedData['daily_bonus'] ?? 1000)?>">

    <button class="btn" type="submit" style="margin-top:16px">Saqlash va davom etish →</button>
  </form>
</div>

<?php elseif ($step === 4):
    $wh  = $_SESSION['webhook_result'] ?? null;
    $cfg = $_SESSION['cfg_data'] ?? [];
    $whUrl = ($cfg['app_url'] ?? '').'/bot/webhook.php';
?>
<div class="card">
  <h2>4. Telegram Webhook</h2>
  <?php if ($wh): ?>
    <?php if ($wh['ok'] ?? false): ?>
      <div class="ok">Webhook muvaffaqiyatli o'rnatildi!</div>
    <?php else: ?>
      <div class="err">
        Webhook xatosi: <?=htmlspecialchars($wh['description'] ?? json_encode($wh))?>
        <br><small>Token to'g'ri ekanligini va HTTPS ishlashini tekshiring.</small>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <div style="font-size:12px;color:#94a3b8;margin:12px 0;line-height:2">
    <div>Webhook URL: <code><?=htmlspecialchars($whUrl)?></code></div>
    <div>Bot: <code>@<?=htmlspecialchars($cfg['bot_username'] ?? '—')?></code></div>
    <div>Secret: <code>yo'q (secretsiz)</code></div>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step4">
    <button class="btn" type="submit">Webhookni o'rnatish →</button>
  </form>
  <a class="btn btn-sec" href="install.php?step=5" style="margin-top:8px">O'tkazib yuborish</a>
</div>
<?php if ($wh && ($wh['ok'] ?? false)): ?>
  <a class="btn" href="install.php?step=5">Keyingisi →</a>
<?php endif; ?>

<?php elseif ($step === 5):
    $err = $_SESSION['step5_err'] ?? null;
    unset($_SESSION['step5_err']);
?>
<div class="card">
  <h2>5. Admin parol</h2>
  <?php if ($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="step5">
    <label>Admin login</label>
    <input name="admin_user" value="admin" required>
    <label>Parol (kamida 8 ta belgi)</label>
    <input name="admin_pass" type="password" required minlength="8" autocomplete="new-password">
    <label>Parolni tasdiqlang</label>
    <input name="admin_pass2" type="password" required minlength="8" autocomplete="new-password">
    <button class="btn" type="submit" style="margin-top:16px">Parolni saqlash →</button>
  </form>
</div>

<?php elseif ($step === 6):
    $ok5 = $_SESSION['step5_ok'] ?? null;
    $cfg = $_SESSION['cfg_data'] ?? [];
    $url = $cfg['app_url'] ?? '';
    session_destroy();
?>
<div class="card">
  <h2>O'rnatish muvaffaqiyatli tugadi!</h2>
  <?php if ($ok5): ?>
    <div class="ok">Admin parol saqlandi!</div>
  <?php else: ?>
    <div class="err">Admin parol saqlanmadi — admin paneldan qayta o'rnating.</div>
  <?php endif; ?>

  <div style="display:flex;flex-direction:column;gap:8px;margin:16px 0">
    <a href="<?=htmlspecialchars($url)?>/admin/"
       target="_blank"
       style="padding:14px 16px;background:rgba(0,255,136,.1);border:1px solid rgba(0,255,136,.2);border-radius:12px;text-decoration:none;color:#00ff88;display:flex;justify-content:space-between;align-items:center">
      <span>Admin panel</span>
      <span style="opacity:.5;font-size:11px"><?=htmlspecialchars($url)?>/admin/</span>
    </a>
    <a href="<?=htmlspecialchars($url)?>/miniapp/"
       target="_blank"
       style="padding:14px 16px;background:rgba(0,255,136,.06);border:1px solid rgba(0,255,136,.12);border-radius:12px;text-decoration:none;color:#a7f3d0;display:flex;justify-content:space-between;align-items:center">
      <span>Mini App</span>
      <span style="opacity:.5;font-size:11px"><?=htmlspecialchars($url)?>/miniapp/</span>
    </a>
  </div>

  <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:16px;font-size:13px;color:#fca5a5;line-height:1.8">
    <strong>MUHIM — Darhol bajarish kerak:</strong><br>
    1. <code>install.php</code> faylini o'chiring (FTP yoki File Manager)<br>
    2. <code>test.php</code> mavjud bo'lsa uni ham o'chiring<br>
    3. BotFather da <code>/setmenubutton</code> orqali Mini App tugmasini sozlang
  </div>

  <div style="margin-top:14px;font-size:12px;color:#475569;line-height:2">
    <div>Cron (kunlik daromad, har kuni 00:05):</div>
    <code style="display:block;background:rgba(0,0,0,.4);padding:8px 12px;border-radius:8px;font-size:11px;color:#6ee7b7">
      5 0 * * * php <?=BASE?>/jobs/DailyProfit.php
    </code>
    <div style="margin-top:10px">BotFather Mini App tugmasi:</div>
    <code style="display:block;background:rgba(0,0,0,.4);padding:8px 12px;border-radius:8px;font-size:11px;color:#6ee7b7">
      /setmenubutton → Web App → <?=htmlspecialchars($url)?>/miniapp/ → Tortinmang
    </code>
  </div>
</div>
<?php endif;

$body = ob_get_clean();
layout(
    match($step) {
        1 => 'Talablar',
        2 => 'Baza',
        3 => 'Sozlamalar',
        4 => 'Webhook',
        5 => 'Admin',
        6 => 'Tayyor',
        default => 'Install'
    },
    $body,
    $step
);
