<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../bootstrap.php';

use Tortinmang\Core\{DB, Config, Helpers, Logger};

// ── Auth ──────────────────────────────────────────────────────
function adminAuth(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ?p=login'); exit;
    }
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verifyCsrf(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}
function money(float $n): string { return number_format($n, 0, '', ' '); }

$db   = DB::get();
$page = $_GET['p'] ?? 'login';
$msg  = ''; $msgType = 'ok';

// ── LOGIN ─────────────────────────────────────────────────────
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $admin = $db->fetch('SELECT * FROM admin_users WHERE username = ?', [$uname]);
    if ($admin && $admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
        $msg = 'Hisob bloklangan. 30 daqiqa kuting.'; $msgType = 'err';
    } elseif ($admin && password_verify($pass, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        $db->update('admin_users', ['login_attempts' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
        header('Location: ?p=dashboard'); exit;
    } else {
        if ($admin) {
            $att = $admin['login_attempts'] + 1;
            $lock = $att >= 5 ? date('Y-m-d H:i:s', strtotime('+30 min')) : null;
            $db->update('admin_users', ['login_attempts' => $att, 'locked_until' => $lock], 'id = ?', [$admin['id']]);
        }
        $msg = "Noto'g'ri login yoki parol"; $msgType = 'err';
    }
}
if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ?p=login'); exit;
}
if ($page !== 'login') adminAuth();
$csrf = csrf();

// ── CSV EXPORT ────────────────────────────────────────────────
if ($page === 'export' && isset($_GET['type'])) {
    adminAuth();
    // GET CSRF tekshiruvi
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'])) {
        http_response_code(403); echo 'Forbidden'; exit;
    }
    adminAuth();
    $type = $_GET['type'];
    $allowed = ['users','deposits','withdrawals'];
    if (!in_array($type, $allowed)) { http_response_code(400); exit; }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tortinmang_'.$type.'_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

    if ($type === 'users') {
        fputcsv($out, ['ID','Telegram ID','Ism','Familiya','Username','Balans','Jami daromad','Jami depozit','Jami yechilgan','Daraja','Badge','Holat','Sana']);
        foreach ($db->fetchAll("SELECT * FROM users ORDER BY id DESC") as $r) {
            fputcsv($out, [$r['id'],$r['telegram_id'],$r['first_name'],$r['last_name'],$r['username'],
                $r['balance'],$r['total_earned'],$r['total_deposit'],$r['total_withdraw'],
                $r['level'],$r['badge'],$r['is_blocked']?'Bloklangan':'Faol',$r['created_at']]);
        }
    } elseif ($type === 'deposits') {
        fputcsv($out, ['ID','User ID','Ism','Username','Summa','Karta turi','Holat','Admin izohi','Sana']);
        foreach ($db->fetchAll("SELECT d.*,u.first_name,u.username FROM deposits d JOIN users u ON d.user_id=u.id ORDER BY d.id DESC") as $r) {
            fputcsv($out, [$r['id'],$r['user_id'],$r['first_name'],$r['username'],
                $r['amount'],$r['card_type'],$r['status'],$r['admin_note'],$r['created_at']]);
        }
    } elseif ($type === 'withdrawals') {
        fputcsv($out, ['ID','User ID','Ism','Username','Summa','Karta turi','Holat','Admin izohi','Sana']);
        foreach ($db->fetchAll("SELECT w.*,u.first_name,u.username FROM withdrawals w JOIN users u ON w.user_id=u.id ORDER BY w.id DESC") as $r) {
            fputcsv($out, [$r['id'],$r['user_id'],$r['first_name'],$r['username'],
                $r['amount'],$r['card_type'],$r['status'],$r['admin_note'],$r['created_at']]);
        }
    }
    fclose($out);
    exit;
}

// ── DEPOSITS actions ──────────────────────────────────────────
if ($page === 'deposits' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $id = (int)($_POST['id'] ?? 0); $note = $_POST['note'] ?? '';
    if ($_POST['action'] === 'approve') {
        if (\Tortinmang\Models\Transaction::approveDeposit($id, $note)) $msg = 'Depozit tasdiqlandi ✅';
        else { $msg = 'Xatolik'; $msgType = 'err'; }
    } elseif ($_POST['action'] === 'reject') {
        if (\Tortinmang\Models\Transaction::rejectDeposit($id, $note)) $msg = 'Depozit rad etildi';
        else { $msg = 'Xatolik'; $msgType = 'err'; }
    }
}

// ── WITHDRAWALS actions ───────────────────────────────────────
if ($page === 'withdrawals' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $id = (int)($_POST['id'] ?? 0); $note = $_POST['note'] ?? '';
    if ($_POST['action'] === 'approve') {
        if (\Tortinmang\Models\Transaction::approveWithdrawal($id, $note)) $msg = 'Yechish tasdiqlandi ✅';
        else { $msg = 'Xatolik'; $msgType = 'err'; }
    } elseif ($_POST['action'] === 'reject') {
        if (\Tortinmang\Models\Transaction::rejectWithdrawal($id, $note)) $msg = "Yechish rad etildi, pul qaytarildi";
        else { $msg = 'Xatolik'; $msgType = 'err'; }
    }
}

// ── USERS actions ─────────────────────────────────────────────
if ($page === 'users' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $uid = (int)($_POST['uid'] ?? 0);
    match ($_POST['action'] ?? '') {
        'block'     => ($db->update('users', ['is_blocked' => 1], 'id=?', [$uid]) && ($msg = 'Bloklandi')),
        'unblock'   => ($db->update('users', ['is_blocked' => 0], 'id=?', [$uid]) && ($msg = 'Blokdan chiqdi')),
        'add_bal'   => (($a=(float)($_POST['amt']??0))>0 && \Tortinmang\Models\User::addEarnings($uid,$a) && ($msg=money($a)." qo'shildi")),
        'sub_bal'   => (($a=(float)($_POST['amt']??0))>0 && $db->query('UPDATE users SET balance=GREATEST(0,balance-?) WHERE id=?',[$a,$uid]) && ($msg=money($a)." ayirildi")),
        'set_badge' => (in_array($_POST['badge']??'',['yangi','investor','lider','kurator']) && $db->update('users',['badge'=>$_POST['badge']],'id=?',[$uid]) && ($msg='Badge yangilandi')),
        default     => null,
    };
}

// ── PACKAGES actions ──────────────────────────────────────────
if ($page === 'packages' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $db->insert('packages', ['name'=>$_POST['name'],'description'=>$_POST['desc'],'min_amount'=>$_POST['min'],'max_amount'=>$_POST['max'],'daily_percent'=>$_POST['pct'],'duration_days'=>$_POST['days'],'icon'=>$_POST['icon']??'📦','color'=>$_POST['color']??'#7c3aed','is_active'=>1,'sort_order'=>0]);
        $msg = "Paket qo'shildi";
    } elseif ($act === 'edit') {
        $db->update('packages',['name'=>$_POST['name'],'description'=>$_POST['desc'],'min_amount'=>$_POST['min'],'max_amount'=>$_POST['max'],'daily_percent'=>$_POST['pct'],'duration_days'=>$_POST['days'],'is_active'=>(int)($_POST['active']??1)],'id=?',[(int)$_POST['pid']]);
        $msg = 'Yangilandi';
    } elseif ($act === 'delete') {
        $db->delete('packages','id=?',[(int)$_POST['pid']]); $msg = "O'chirildi";
    }
}

// ── SETTINGS actions ──────────────────────────────────────────
if ($page === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    foreach ($_POST['s'] ?? [] as $k => $v) {
        $exists = $db->fetchColumn('SELECT id FROM settings WHERE setting_key=?', [$k]);
        if ($exists) $db->update('settings', ['setting_value'=>$v], 'setting_key=?', [$k]);
        else $db->insert('settings', ['setting_key'=>$k,'setting_value'=>$v]);
    }
    // Update config values too
    foreach (['bot_token','bot_username'] as $key) {
        if (isset($_POST['s'][$key])) {
            // Will be read from settings table at runtime
        }
    }
    $msg = 'Saqlandi ✅';
}

// ── PROMO actions ─────────────────────────────────────────────
if ($page === 'promo' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $db->insert('promo_codes', ['code'=>strtoupper($_POST['code']),'reward_amount'=>(float)$_POST['amount'],'max_uses'=>(int)$_POST['max_uses'],'used_count'=>0,'expires_at'=>($_POST['expires']??'')?:null,'is_active'=>1]);
        $msg = 'Promo kod yaratildi';
    } elseif ($act === 'edit') {
        $db->update('promo_codes', [
            'reward_amount' => (float)$_POST['amount'],
            'max_uses'      => (int)$_POST['max_uses'],
            'expires_at'    => ($_POST['expires']??'')?:null,
        ], 'id=?', [(int)$_POST['pid']]);
        $msg = 'Yangilandi';
    } elseif ($act === 'delete') {
        $db->delete('promo_codes','id=?',[(int)$_POST['pid']]); $msg = "O'chirildi";
    } elseif ($act === 'toggle') {
        $p = $db->fetch('SELECT is_active FROM promo_codes WHERE id=?',[(int)$_POST['pid']]);
        if ($p) { $db->update('promo_codes',['is_active'=>$p['is_active']?0:1],'id=?',[(int)$_POST['pid']]); $msg='Yangilandi'; }
    }
}

// ── NEWS actions ──────────────────────────────────────────────
if ($page === 'news' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $db->insert('news',['title'=>$_POST['title'],'content'=>$_POST['content'],'emoji'=>$_POST['emoji']??'📰','is_active'=>1]);
        $msg = "Qo'shildi";
    } elseif ($act === 'edit') {
        $db->update('news',['title'=>$_POST['title'],'content'=>$_POST['content'],'emoji'=>$_POST['emoji']??'📰'],'id=?',[(int)$_POST['nid']]);
        $msg = "Yangilandi";
    } elseif ($act === 'toggle') {
        $n = $db->fetch('SELECT is_active FROM news WHERE id=?',[(int)$_POST['nid']]);
        if ($n) { $db->update('news',['is_active'=>$n['is_active']?0:1],'id=?',[(int)$_POST['nid']]); $msg='Yangilandi'; }
    } elseif ($act === 'delete') {
        $db->delete('news','id=?',[(int)$_POST['nid']]); $msg = "O'chirildi";
    }
}

// ── LOTTERY actions ───────────────────────────────────────────
if ($page === 'lottery' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'add_prize') {
        $db->insert('lottery_prizes', [
            'name'       => $_POST['name'],
            'prize_type' => $_POST['prize_type'],
            'amount'     => (float)$_POST['amount'],
            'weight'     => (int)$_POST['weight'],
            'is_active'  => 1,
        ]);
        $msg = "Sovrin qo'shildi";
    } elseif ($act === 'edit_prize') {
        $db->update('lottery_prizes', [
            'name'       => $_POST['name'],
            'prize_type' => $_POST['prize_type'],
            'amount'     => (float)$_POST['amount'],
            'weight'     => (int)$_POST['weight'],
        ], 'id=?', [(int)$_POST['pid']]);
        $msg = 'Yangilandi';
    } elseif ($act === 'toggle_prize') {
        $p = $db->fetch('SELECT is_active FROM lottery_prizes WHERE id=?', [(int)$_POST['pid']]);
        if ($p) { $db->update('lottery_prizes', ['is_active' => $p['is_active'] ? 0 : 1], 'id=?', [(int)$_POST['pid']]); $msg = 'Yangilandi'; }
    } elseif ($act === 'delete_prize') {
        $db->delete('lottery_prizes', 'id=?', [(int)$_POST['pid']]); $msg = "O'chirildi";
    }
}

// ── VIP actions ────────────────────────────────────────────────
if ($page === 'vip' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    $uid = (int)($_POST['uid'] ?? 0);
    if ($act === 'add_vip' && $uid) {
        $tier = $_POST['tier'] ?? 'silver';
        $days = (int)($_POST['days'] ?? 30);
        if (in_array($tier, ['silver','gold','diamond'])) {
            $db->query("UPDATE vip_memberships SET is_active=0 WHERE user_id=? AND is_active=1", [$uid]);
            $db->insert('vip_memberships', [
                'user_id'    => $uid,
                'tier'       => $tier,
                'started_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime("+{$days} days")),
                'is_active'  => 1,
            ]);
            $msg = "VIP berildi: {$tier} ({$days} kun)";
        }
    } elseif ($act === 'revoke_vip' && $uid) {
        $db->query("UPDATE vip_memberships SET is_active=0 WHERE user_id=?", [$uid]);
        $msg = "VIP bekor qilindi";
    }
}

// ── ACHIEVEMENTS actions ───────────────────────────────────────
if ($page === 'achievements' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $db->insert('achievements', [
            'achievement_key'  => strtolower(trim($_POST['ach_key'])),
            'name'             => $_POST['name'],
            'description'      => $_POST['description'],
            'icon'             => $_POST['icon'] ?? '🏆',
            'requirement_type' => $_POST['req_type'],
            'requirement_value'=> (float)$_POST['req_value'],
            'reward'           => (float)$_POST['reward'],
            'is_active'        => 1,
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
        ]);
        $msg = "Yutuq qo'shildi";
    } elseif ($act === 'edit') {
        $db->update('achievements', [
            'name'             => $_POST['name'],
            'description'      => $_POST['description'],
            'icon'             => $_POST['icon'] ?? '🏆',
            'requirement_type' => $_POST['req_type'],
            'requirement_value'=> (float)$_POST['req_value'],
            'reward'           => (float)$_POST['reward'],
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
        ], 'id=?', [(int)$_POST['aid']]);
        $msg = 'Yangilandi';
    } elseif ($act === 'toggle') {
        $a = $db->fetch('SELECT is_active FROM achievements WHERE id=?', [(int)$_POST['aid']]);
        if ($a) { $db->update('achievements', ['is_active' => $a['is_active'] ? 0 : 1], 'id=?', [(int)$_POST['aid']]); $msg = 'Yangilandi'; }
    } elseif ($act === 'delete') {
        $db->delete('achievements', 'id=?', [(int)$_POST['aid']]); $msg = "O'chirildi";
    }
}

// ── BROADCAST ─────────────────────────────────────────────────
if ($page === 'broadcast' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $text      = trim($_POST['message'] ?? '');
    $target    = $_POST['target'] ?? 'all';
    $parseMode = $_POST['parse_mode'] ?? 'none'; // 'markdown' yoki 'none'
    if ($text) {
        // Vaqt cheklovini olib tashlaymiz
        set_time_limit(0);
        ignore_user_abort(true);

        $where = match($target) {
            'vip'              => "u.id IN (SELECT user_id FROM vip_memberships WHERE is_active=1 AND expires_at>NOW())",
            'active_investors' => "u.id IN (SELECT DISTINCT user_id FROM investments WHERE status='active')",
            default            => '1=1',
        };
        $users = $db->fetchAll("SELECT telegram_id FROM users u WHERE is_blocked=0 AND {$where}");
        $tg    = new \Tortinmang\Core\TelegramBot();
        $sent  = 0; $failed = 0;
        $options = $parseMode === 'markdown' ? [] : ['parse_mode' => ''];
        foreach ($users as $u) {
            $result = $tg->sendMessage((int)$u['telegram_id'], $text, $options);
            if ($result['ok'] ?? false) $sent++; else $failed++;
            // Har 30 ta xabardan keyin 1s kutish (Telegram rate limit: 30/s)
            if (($sent + $failed) % 30 === 0) sleep(1);
        }
        $msg = "✅ Yuborildi: {$sent} | ❌ Xato: {$failed}";
    }
}

// ── PASSWORD ──────────────────────────────────────────────────
if ($page === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $admin = $db->fetch('SELECT password_hash FROM admin_users WHERE id=?',[$_SESSION['admin_id']]);
    if (!password_verify($_POST['cur']??'', $admin['password_hash'])) { $msg="Joriy parol noto'g'ri"; $msgType='err'; }
    elseif (($_POST['new']??'') !== ($_POST['con']??'')) { $msg='Parollar mos emas'; $msgType='err'; }
    elseif (strlen($_POST['new']??'') < 8) { $msg='Kamida 8 belgi'; $msgType='err'; }
    else { $db->update('admin_users',['password_hash'=>password_hash($_POST['new'],PASSWORD_BCRYPT,['cost'=>12])],'id=?',[$_SESSION['admin_id']]); $msg='Parol yangilandi ✅'; }
}

?><!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#020d07">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Tortinmang Admin">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="icons/admin-180.png">
<title>Tortinmang Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
<style>
body{background:#020d07}
.glass{background:rgba(0,255,136,0.03);backdrop-filter:blur(20px);border:1px solid rgba(0,255,136,0.1)}
.glass-dark{background:rgba(0,15,7,0.6);backdrop-filter:blur(16px);border:1px solid rgba(0,255,136,0.08)}
.btn-p{background:linear-gradient(135deg,#00ff88,#00d4a0);transition:all .2s}
.btn-p:hover{opacity:.9;transform:translateY(-1px);color:#021a0c}
.btn-g{background:linear-gradient(135deg,#006633,#00cc66)}
.btn-r{background:linear-gradient(135deg,#dc2626,#ef4444)}
.nav-a{transition:all .2s;border-left:3px solid transparent}
.nav-a.act,.nav-a:hover{background:rgba(0,255,136,.1);border-left-color:#00ff88}
input,select,textarea{background:rgba(0,0,0,.4)!important;border:1px solid rgba(255,255,255,.1)!important;color:#e2e8f0!important;transition:border-color .2s}
input:focus,select:focus,textarea:focus{border-color:#00ff88!important;outline:none!important}

/* Mobile admin navigation and install affordance */
.admin-mobile-bar{position:sticky;top:0;z-index:40;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:calc(.75rem + env(safe-area-inset-top)) 1rem .75rem;background:rgba(2,13,7,.94);backdrop-filter:blur(18px);border-bottom:1px solid rgba(0,255,136,.12)}
.admin-mobile-menu{position:fixed;z-index:70;top:0;left:0;bottom:0;width:min(86vw,340px);padding:calc(1rem + env(safe-area-inset-top)) 1rem calc(1rem + env(safe-area-inset-bottom));overflow-y:auto;background:#031208;border-right:1px solid rgba(0,255,136,.18);box-shadow:20px 0 50px rgba(0,0,0,.45);transform:translateX(-105%);transition:transform .22s ease}
.admin-mobile-menu.is-open{transform:translateX(0)}
.admin-menu-backdrop{position:fixed;z-index:60;inset:0;background:rgba(0,0,0,.64);opacity:0;pointer-events:none;transition:opacity .22s ease}
.admin-menu-backdrop.is-open{opacity:1;pointer-events:auto}
.admin-menu-button{min-width:44px;min-height:44px;border:1px solid rgba(0,255,136,.22);border-radius:12px;background:rgba(0,255,136,.08);color:#a7f3d0;font-size:20px;line-height:1;cursor:pointer}
.pwa-install{display:none;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:0 12px;border:1px solid rgba(0,255,136,.28);border-radius:12px;background:rgba(0,255,136,.1);color:#a7f3d0;font-size:12px;font-weight:700;cursor:pointer}
.pwa-install.is-visible{display:inline-flex}
.pwa-install:focus-visible,.admin-menu-button:focus-visible{outline:2px solid #00ff88;outline-offset:2px}
@media (max-width:1023px){
  main{padding:1rem 1rem calc(2rem + env(safe-area-inset-bottom))!important}
  table{min-width:640px}
  .glass{border-radius:16px}
}
</style>
</head>
<body class="font-sans text-slate-200 antialiased">

<?php if ($page === 'login'): ?>
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="glass rounded-3xl p-10 w-full max-w-md">
    <div class="text-center mb-8">
      <div class="text-5xl mb-3">💎</div>
      <h1 class="text-3xl font-bold bg-gradient-to-r from-green-400 to-teal-400 bg-clip-text text-transparent">Tortinmang</h1>
      <p class="text-slate-500 text-sm mt-1">Admin boshqaruv paneli</p>
    </div>
    <?php if($msg):?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-3 mb-4 text-sm text-center"><?=htmlspecialchars($msg)?></div><?php endif;?>
    <form method="POST" class="space-y-4">
      <input type="text" name="username" placeholder="Login" required class="w-full rounded-xl px-4 py-3 text-sm">
      <input type="password" name="password" placeholder="Parol" required class="w-full rounded-xl px-4 py-3 text-sm">
      <button class="btn-p w-full text-white font-semibold py-3 rounded-xl text-sm">Kirish →</button>
    </form>
    <button type="button" id="pwa-install-login" class="pwa-install w-full mt-4">📲 Ilova sifatida o'rnatish</button>
    <p id="pwa-ios-hint-login" class="hidden text-center text-xs text-slate-500 mt-3">iPhone/iPad: Safari menyusidan <b>Share → Add to Home Screen</b> ni tanlang.</p>
  </div>
</div>
<?php else:?>

<?php
$menu = [
  ['dashboard','📊','Dashboard'],['deposits','💰','Depozitlar'],['withdrawals','💸','Yechishlar'],
  ['users','👥','Foydalanuvchilar'],['investments','📈','Investitsiyalar'],['packages','📦','Paketlar'],
  ['lottery','🎰','Lotereya'],['vip','👑','VIP'],['achievements','🏆','Yutuqlar'],
  ['promo','🎁','Promo kodlar'],['news','📰','Yangiliklar'],['broadcast','📢','Broadcast'],
  ['statistics','📉','Statistika'],['security','🔒','Xavfsizlik'],['settings','⚙️','Sozlamalar'],['password','🔑','Parol'],['logout','🚪','Chiqish'],
];
$currentPageLabel = 'Boshqaruv';
foreach ($menu as [$menuPage, , $menuLabel]) {
    if ($menuPage === $page) {
        $currentPageLabel = $menuLabel;
        break;
    }
}
?>
<div class="min-h-screen">
  <!-- Mobile app bar -->
  <header class="admin-mobile-bar lg:hidden">
    <button type="button" id="admin-menu-open" class="admin-menu-button" aria-label="Menyuni ochish" aria-controls="admin-mobile-menu" aria-expanded="false">☰</button>
    <div class="min-w-0 flex-1">
      <div class="text-sm font-bold text-green-300 truncate">💎 Tortinmang Admin</div>
      <div class="text-xs text-slate-500 truncate"><?=$currentPageLabel?></div>
    </div>
    <button type="button" id="pwa-install" class="pwa-install" aria-label="Admin ilovasini o'rnatish">📲 O'rnatish</button>
  </header>

  <!-- Mobile drawer -->
  <div id="admin-menu-backdrop" class="admin-menu-backdrop lg:hidden" aria-hidden="true"></div>
  <aside id="admin-mobile-menu" class="admin-mobile-menu lg:hidden" aria-label="Admin menyusi" aria-hidden="true">
    <div class="flex items-start justify-between gap-3 mb-5 px-2">
      <div>
        <div class="text-lg font-bold text-green-300">💎 Tortinmang Admin</div>
        <div class="text-xs text-slate-500 mt-1"><?=htmlspecialchars($_SESSION['admin_name'] ?? 'admin')?></div>
      </div>
      <button type="button" id="admin-menu-close" class="admin-menu-button" aria-label="Menyuni yopish">×</button>
    </div>
    <nav class="flex flex-col gap-1">
      <?php foreach ($menu as [$p,$i,$l]): ?>
        <a href="?p=<?=$p?>" class="nav-a <?=$page===$p?'act':''?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm <?=$page===$p?'text-green-400 font-semibold':'text-slate-300'?>">
          <span class="w-6 text-center"><?=$i?></span><?=$l?>
        </a>
      <?php endforeach; ?>
    </nav>
    <button type="button" id="pwa-install-menu" class="pwa-install w-full mt-5">📲 Ilova sifatida o'rnatish</button>
    <p id="pwa-ios-hint-menu" class="hidden text-xs text-slate-500 leading-5 mt-3 px-2">iPhone/iPad: Safari menyusidan <b>Share → Add to Home Screen</b> ni tanlang.</p>
  </aside>

  <!-- Desktop sidebar -->
  <aside class="w-64 glass-dark min-h-screen p-4 flex flex-col gap-1 fixed top-0 left-0 bottom-0 overflow-y-auto z-50 hidden lg:flex">
    <div class="p-4 mb-4 text-center">
      <div class="text-3xl mb-1">💎</div>
      <div class="text-lg font-bold bg-gradient-to-r from-green-400 to-teal-400 bg-clip-text text-transparent">Tortinmang Admin</div>
      <div class="text-xs text-slate-500 mt-1"><?=htmlspecialchars($_SESSION['admin_name'] ?? 'admin')?></div>
    </div>
    <?php foreach ($menu as [$p,$i,$l]): ?>
      <a href="?p=<?=$p?>" class="nav-a <?=$page===$p?'act':''?> flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm <?=$page===$p?'text-green-400 font-semibold':'text-slate-400 hover:text-slate-200'?>">
        <span><?=$i?></span><?=$l?>
      </a>
    <?php endforeach; ?>
  </aside>

  <!-- MAIN -->
  <main class="lg:ml-64 p-6">
<?php if ($msg):?>
<div class="mb-6 rounded-xl px-5 py-3 text-sm flex items-center gap-3 <?=$msgType==='err'?'bg-red-500/10 border border-red-500/30 text-red-400':'bg-green-500/10 border border-green-500/30 text-green-400'?>">
  <?=$msgType==='err'?'❌':'✅'?> <?=htmlspecialchars($msg)?>
</div>
<?php endif;?>

<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest && e.target.closest('.show-card-btn');
  if(!btn) return;
  const wid = btn.getAttribute('data-wid');
  const container = btn.closest('tr') || btn.closest('div');
  const span = container ? container.querySelector('.card-mask[data-wid="' + wid + '"]') : null;
  if(!span || !wid) return;
  // If already shown, hide
  if(btn.dataset.shown === '1'){
    span.textContent = maskCard(span.dataset.full || '');
    btn.textContent = "Ko'rsat";
    btn.dataset.shown = '0';
    return;
  }
  // Fetch full card from server
  const data = new URLSearchParams();
  data.append('id', wid);
  data.append('csrf', '<?=$csrf?>');
  fetch('reveal_card.php', { method: 'POST', body: data, credentials: 'same-origin' })
    .then(r => r.json())
    .then(j => {
     if(!j.success){ alert(j.error || 'Xato'); return; }
     span.dataset.full = j.card;
     span.textContent = j.card;
     btn.textContent = 'Yashir';
     btn.dataset.shown = '1';
     // auto-hide after 15s
     setTimeout(()=>{
       if(btn.dataset.shown==='1'){
       span.textContent = maskCard(span.dataset.full || '');
       btn.textContent = "Ko'rsat";
       btn.dataset.shown = '0';
       }
     },15000);
    })
    .catch(()=>alert('Sorov amalga oshmadi'));
});

function maskCard(full){
  if(!full) return '';
  const clean = (full + '').replace(/\s+/g,'');
  if(clean.length < 8) return full;
  return clean.slice(0,4) + ' **** **** ' + clean.slice(-4);
}
</script>

<?php
// ── DASHBOARD ────────────────────────────────────────────────
if ($page === 'dashboard'):
$s = [
  'users'       => (int)$db->fetchColumn('SELECT COUNT(*) FROM users'),
  'today_users' => (int)$db->fetchColumn('SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()'),
  'deposits'    => (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status='approved'"),
  'withdrawals' => (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='approved'"),
  'balance'     => (float)$db->fetchColumn("SELECT COALESCE(SUM(balance),0) FROM users"),
  'pend_dep'    => (int)$db->fetchColumn("SELECT COUNT(*) FROM deposits WHERE status='pending'"),
  'pend_wd'     => (int)$db->fetchColumn("SELECT COUNT(*) FROM withdrawals WHERE status='pending'"),
  'active_inv'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM investments WHERE status='active'"),
  'active_inv_sum'=> (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status='active'"),
  'vip'         => (int)$db->fetchColumn("SELECT COUNT(*) FROM vip_memberships WHERE is_active=1 AND expires_at>NOW()"),
];
$cards = [
  ['👥',$s['users'],'Jami users','text-green-400'],
  ['🆕',$s['today_users'],'Bugun qo\'shildi','text-green-400'],
  ['💰',money($s['deposits']).' so\'m','Jami depozit','text-yellow-400'],
  ['💸',money($s['withdrawals']).' so\'m','Jami yechilgan','text-red-400'],
  ['🏦',money($s['balance']).' so\'m','Tizim balansi','text-teal-400'],
  ['📈',$s['active_inv'],'Faol investitsiya','text-teal-400'],
  ['💵',money($s['active_inv_sum']).' so\'m','Invest. summasi','text-emerald-400'],
  ['⏳',$s['pend_dep'],'Kutilayotgan dep.','text-orange-400'],
  ['⏳',$s['pend_wd'],'Kutilayotgan yech.','text-pink-400'],
  ['👑',$s['vip'],'VIP a\'zolar','text-amber-400'],
];
?>
<h2 class="text-2xl font-bold text-white mb-6">📊 Dashboard</h2>
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
<?php foreach($cards as [$ic,$val,$lbl,$col]):?>
  <div class="glass rounded-2xl p-4">
    <div class="text-2xl mb-2"><?=$ic?></div>
    <div class="text-lg font-bold <?=$col?>"><?=$val?></div>
    <div class="text-xs text-slate-500 mt-1"><?=$lbl?></div>
  </div>
<?php endforeach;?>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="glass rounded-2xl p-5">
    <h3 class="text-sm font-semibold text-slate-300 mb-3">💰 So'nggi depozitlar</h3>
    <?php foreach($db->fetchAll("SELECT d.*,u.first_name,u.username FROM deposits d JOIN users u ON d.user_id=u.id ORDER BY d.created_at DESC LIMIT 8") as $d):?>
    <div class="flex justify-between items-center py-2 border-b border-white/5 text-sm">
      <span class="text-slate-300"><?=htmlspecialchars($d['first_name'])?> <span class="text-slate-500 text-xs">@<?=htmlspecialchars($d['username'])?></span></span>
      <span class="<?=$d['status']==='approved'?'text-green-400':($d['status']==='pending'?'text-yellow-400':'text-red-400')?> font-semibold"><?=money((float)$d['amount'])?> so'm</span>
    </div>
    <?php endforeach;?>
  </div>
  <div class="glass rounded-2xl p-5">
    <h3 class="text-sm font-semibold text-slate-300 mb-3">💸 So'nggi yechishlar</h3>
    <?php foreach($db->fetchAll("SELECT w.*,u.first_name,u.username FROM withdrawals w JOIN users u ON w.user_id=u.id ORDER BY w.created_at DESC LIMIT 8") as $w):?>
    <div class="flex justify-between items-center py-2 border-b border-white/5 text-sm">
      <span class="text-slate-300"><?=htmlspecialchars($w['first_name'])?> <span class="text-slate-500 text-xs">@<?=htmlspecialchars($w['username'])?></span></span>
      <span class="<?=$w['status']==='approved'?'text-green-400':($w['status']==='pending'?'text-yellow-400':'text-red-400')?> font-semibold"><?=money((float)$w['amount'])?> so'm</span>
    </div>
    <?php endforeach;?>
  </div>
</div>
<?php endif;?>

<?php if ($page === 'deposits'):?>
<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-white">💰 Depozitlar</h2>
  <a href="?p=export&type=deposits&csrf=<?=$csrf?>" class="btn-g text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2">📥 CSV Export</a>
</div>
<?php $pends = $db->fetchAll("SELECT d.*,u.first_name,u.username,u.telegram_id as tgid FROM deposits d JOIN users u ON d.user_id=u.id WHERE d.status='pending' ORDER BY d.created_at ASC");?>
<?php if($pends):?>
<h3 class="text-yellow-400 font-semibold mb-3">⏳ Kutilayotganlar (<?=count($pends)?>)</h3>
<div class="space-y-4 mb-8">
<?php foreach($pends as $d):?>
<div class="glass rounded-2xl p-5">
  <div class="flex flex-col sm:flex-row justify-between gap-4 mb-4">
    <div>
      <div class="font-semibold text-white"><?=htmlspecialchars($d['first_name'])?> <span class="text-slate-400 text-xs">@<?=htmlspecialchars($d['username'])?></span></div>
      <div class="text-xs text-slate-500">TG: <?=$d['tgid']?> | Sana: <?=$d['created_at']?></div>
    </div>
    <div class="text-right">
      <div class="text-2xl font-bold text-green-400"><?=money((float)$d['amount'])?> so'm</div>
      <div class="text-xs text-slate-400">💳 <?=$d['card_type']?></div>
    </div>
  </div>
  <div class="glass-dark rounded-xl px-4 py-3 text-sm text-slate-300 mb-4">
    <span class="text-slate-500 text-xs">📋 Chek:</span> <?=htmlspecialchars($d['receipt_info']?:'—')?>
  </div>
  <form method="POST" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="id" value="<?=$d['id']?>">
    <input type="text" name="note" placeholder="Admin izohi..." class="flex-1 rounded-xl px-4 py-2 text-sm">
    <button name="action" value="approve" class="btn-g text-white px-6 py-2 rounded-xl text-sm font-semibold">✓ Tasdiqlash</button>
    <button name="action" value="reject"  class="btn-r text-white px-6 py-2 rounded-xl text-sm font-semibold">✗ Rad etish</button>
  </form>
</div>
<?php endforeach;?>
</div>
<?php else:?>
<div class="glass rounded-2xl p-10 text-center mb-6"><div class="text-4xl mb-2">🎉</div><p class="text-slate-400">Kutilayotgan depozitlar yo'q</p></div>
<?php endif;?>
<h3 class="text-slate-300 font-semibold mb-3">📋 Tarix (so'nggi 50)</h3>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">ID</th><th class="px-4 py-3 text-left">Foydalanuvchi</th>
    <th class="px-4 py-3">Summa</th><th class="px-4 py-3">Karta</th>
    <th class="px-4 py-3">Holat</th><th class="px-4 py-3">Sana</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT d.*,u.first_name,u.username FROM deposits d JOIN users u ON d.user_id=u.id ORDER BY d.created_at DESC LIMIT 50") as $d):?>
  <tr class="border-b border-white/5 hover:bg-white/[0.02]">
    <td class="px-4 py-2.5 text-slate-500">#<?=$d['id']?></td>
    <td class="px-4 py-2.5"><span class="text-white"><?=htmlspecialchars($d['first_name'])?></span><br><span class="text-xs text-slate-500">@<?=$d['username']?></span></td>
    <td class="px-4 py-2.5 text-center font-semibold text-white"><?=money((float)$d['amount'])?></td>
    <td class="px-4 py-2.5 text-center text-slate-400"><?=$d['card_type']?></td>
    <td class="px-4 py-2.5 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?=$d['status']==='approved'?'bg-green-500/20 text-green-400':($d['status']==='pending'?'bg-yellow-500/20 text-yellow-400':'bg-red-500/20 text-red-400')?>"><?=$d['status']?></span></td>
    <td class="px-4 py-2.5 text-center text-xs text-slate-500"><?=$d['created_at']?></td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'withdrawals'):?>
<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-white">💸 Yechishlar</h2>
  <a href="?p=export&type=withdrawals&csrf=<?=$csrf?>" class="btn-g text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2">📥 CSV Export</a>
</div>
<?php $pends = $db->fetchAll("SELECT w.*,u.first_name,u.username,u.telegram_id as tgid FROM withdrawals w JOIN users u ON w.user_id=u.id WHERE w.status='pending' ORDER BY w.created_at ASC");?>
<?php if($pends):?>
<h3 class="text-yellow-400 font-semibold mb-3">⏳ Kutilayotganlar (<?=count($pends)?>)</h3>
<div class="space-y-4 mb-8">
<?php foreach($pends as $w):?>
<div class="glass rounded-2xl p-5">
  <div class="flex flex-col sm:flex-row justify-between gap-4 mb-4">
    <div>
      <div class="font-semibold text-white"><?=htmlspecialchars($w['first_name'])?> <span class="text-slate-400 text-xs">@<?=htmlspecialchars($w['username'])?></span></div>
      <div class="text-xs text-slate-500">TG: <?=$w['tgid']?> | <?=$w['created_at']?></div>
      <div class="text-xs text-slate-400 mt-1 font-mono">💳 <?=$w['card_type']?>:
        <span class="card-mask" data-wid="<?=$w['id']?>"><?=Helpers::maskCard($w['card_number'])?></span>
        <button type="button" class="show-card-btn ml-2 text-xs px-2 py-1 rounded bg-white/5" data-wid="<?=$w['id']?>">Ko'rsat</button>
      </div>
    </div>
    <div class="text-right">
      <div class="text-2xl font-bold text-red-400"><?=money((float)$w['amount'])?> so'm</div>
    </div>
  </div>
  <form method="POST" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="id" value="<?=$w['id']?>">
    <input type="text" name="note" placeholder="Admin izohi..." class="flex-1 rounded-xl px-4 py-2 text-sm">
    <button name="action" value="approve" class="btn-g text-white px-6 py-2 rounded-xl text-sm font-semibold">✓ To'lov qilindi</button>
    <button name="action" value="reject"  class="btn-r text-white px-6 py-2 rounded-xl text-sm font-semibold">✗ Rad etish</button>
  </form>
</div>
<?php endforeach;?>
</div>
<?php else:?>
<div class="glass rounded-2xl p-10 text-center mb-6"><div class="text-4xl mb-2">✅</div><p class="text-slate-400">Kutilayotgan yechishlar yo'q</p></div>
<?php endif;?>
<h3 class="text-slate-300 font-semibold mb-3">📋 Tarix</h3>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">ID</th><th class="px-4 py-3 text-left">Foydalanuvchi</th>
    <th class="px-4 py-3">Summa</th><th class="px-4 py-3">Karta</th>
    <th class="px-4 py-3">Holat</th><th class="px-4 py-3">Sana</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT w.*,u.first_name,u.username FROM withdrawals w JOIN users u ON w.user_id=u.id ORDER BY w.created_at DESC LIMIT 50") as $w):?>
  <tr class="border-b border-white/5 hover:bg-white/[0.02]">
    <td class="px-4 py-2.5 text-slate-500">#<?=$w['id']?></td>
    <td class="px-4 py-2.5"><span class="text-white"><?=htmlspecialchars($w['first_name'])?></span><br><span class="text-xs text-slate-500">@<?=$w['username']?></span></td>
    <td class="px-4 py-2.5 text-center font-semibold text-white"><?=money((float)$w['amount'])?></td>
    <td class="px-4 py-2.5 text-center font-mono text-xs text-slate-400">
      <span class="card-mask" data-wid="<?=$w['id']?>"><?=Helpers::maskCard($w['card_number'])?></span>
      <button type="button" class="show-card-btn ml-2 text-xs px-2 py-1 rounded bg-white/5" data-wid="<?=$w['id']?>">Ko'rsat</button>
    </td>
    <td class="px-4 py-2.5 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?=$w['status']==='approved'?'bg-green-500/20 text-green-400':($w['status']==='pending'?'bg-yellow-500/20 text-yellow-400':'bg-red-500/20 text-red-400')?>"><?=$w['status']?></span></td>
    <td class="px-4 py-2.5 text-center text-xs text-slate-500"><?=$w['created_at']?></td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'users'):?>
<h2 class="text-2xl font-bold text-white mb-6">👥 Foydalanuvchilar</h2>
<?php
$search = trim($_GET['q'] ?? '');
// telegram_id exact match, boshqasi LIKE
if ($search && ctype_digit($search)) {
    $users = $db->fetchAll("SELECT * FROM users WHERE telegram_id=? OR id=? ORDER BY created_at DESC LIMIT 50",
        [(int)$search, (int)$search]);
} elseif ($search) {
    $users = $db->fetchAll("SELECT * FROM users WHERE first_name LIKE ? OR username LIKE ? ORDER BY created_at DESC LIMIT 50",
        ["%$search%", "%$search%"]);
} else {
    $users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 100");
}
?>
<div class="flex gap-3 mb-4 flex-wrap">
  <form method="GET" class="flex gap-2 flex-1 min-w-64">
    <input type="hidden" name="p" value="users">
    <input type="text" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Ism, username yoki Telegram ID..." class="flex-1 rounded-xl px-4 py-2 text-sm">
    <button class="btn-p text-white px-6 py-2 rounded-xl text-sm font-semibold">🔍 Qidirish</button>
  </form>
  <a href="?p=export&type=users&csrf=<?=$csrf?>" class="btn-g text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2">📥 CSV Export</a>
</div>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">ID</th><th class="px-4 py-3 text-left">Foydalanuvchi</th>
    <th class="px-4 py-3">Balans</th><th class="px-4 py-3">Jami daromad</th>
    <th class="px-4 py-3">Daraja</th><th class="px-4 py-3">Badge</th>
    <th class="px-4 py-3">Holat</th><th class="px-4 py-3">Amallar</th>
  </tr></thead>
  <tbody>
  <?php foreach($users as $u):?>
  <tr class="border-b border-white/5 hover:bg-white/[0.02]">
    <td class="px-4 py-2.5 text-slate-500"><?=$u['id']?></td>
    <td class="px-4 py-2.5">
      <div class="text-white font-medium"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></div>
      <div class="text-xs text-slate-500">@<?=htmlspecialchars($u['username'])?> | TG:<?=$u['telegram_id']?></div>
    </td>
    <td class="px-4 py-2.5 text-center text-green-400 font-semibold"><?=money((float)$u['balance'])?></td>
    <td class="px-4 py-2.5 text-center text-white"><?=money((float)$u['total_earned'])?></td>
    <td class="px-4 py-2.5 text-center text-green-400"><?=$u['level']?> LVL</td>
    <td class="px-4 py-2.5 text-center">
      <?php $bi=\Tortinmang\Models\User::getBadgeInfo($u['badge']??'yangi');?>
      <span class="text-xs px-2 py-0.5 rounded-full" style="background:<?=$bi['color']?>22;color:<?=$bi['color']?>"><?=$bi['icon']?> <?=$bi['label']?></span>
    </td>
    <td class="px-4 py-2.5 text-center">
      <span class="text-xs px-2 py-0.5 rounded-full <?=$u['is_blocked']?'bg-red-500/20 text-red-400':'bg-green-500/20 text-green-400'?>"><?=$u['is_blocked']?'Bloklangan':'Faol'?></span>
    </td>
    <td class="px-4 py-2.5">
      <button onclick="toggleUser(<?=$u['id']?>)" class="text-xs text-green-400 hover:text-green-300 mr-2">⚙️ Amallar</button>
    </td>
  </tr>
  <!-- Action Row -->
  <tr id="ua-<?=$u['id']?>" class="hidden bg-green-500/5">
    <td colspan="8" class="px-4 py-3">
      <form method="POST" class="flex flex-wrap gap-2 items-center">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="uid" value="<?=$u['id']?>">
        <?php if($u['is_blocked']):?>
        <button name="action" value="unblock" class="text-xs bg-green-500/20 text-green-400 px-3 py-1.5 rounded-lg">✅ Blokdan chiqar</button>
        <?php else:?>
        <button name="action" value="block" class="text-xs bg-red-500/20 text-red-400 px-3 py-1.5 rounded-lg">🚫 Bloklash</button>
        <?php endif;?>
        <input type="number" name="amt" placeholder="Summa" class="w-28 rounded-lg px-2 py-1.5 text-xs">
        <button name="action" value="add_bal" class="text-xs bg-green-500/20 text-green-400 px-3 py-1.5 rounded-lg">+ Qo'shish</button>
        <button name="action" value="sub_bal" class="text-xs bg-red-500/20 text-red-400 px-3 py-1.5 rounded-lg">- Ayirish</button>
        <select name="badge" class="rounded-lg px-2 py-1.5 text-xs">
          <?php foreach(['yangi','investor','lider','kurator'] as $b):?>
          <option value="<?=$b?>" <?=$u['badge']===$b?'selected':''?>><?=$b?></option>
          <?php endforeach;?>
        </select>
        <button name="action" value="set_badge" class="text-xs bg-teal-500/20 text-teal-400 px-3 py-1.5 rounded-lg">Badge o'zgartir</button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'packages'):?>
<h2 class="text-2xl font-bold text-white mb-6">📦 Paketlar</h2>
<div class="glass rounded-2xl p-6 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">+ Yangi paket</h3>
  <form method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="add">
    <input type="text" name="name" placeholder="Nomi" required class="rounded-xl px-3 py-2 text-sm">
    <input type="text" name="desc" placeholder="Tavsif" required class="rounded-xl px-3 py-2 text-sm col-span-2">
    <input type="number" name="min" placeholder="Min summa" required class="rounded-xl px-3 py-2 text-sm">
    <input type="number" name="max" placeholder="Max summa" required class="rounded-xl px-3 py-2 text-sm">
    <input type="number" step="0.01" name="pct" placeholder="Kunlik %" required class="rounded-xl px-3 py-2 text-sm">
    <input type="number" name="days" placeholder="Kun soni" required class="rounded-xl px-3 py-2 text-sm">
    <input type="text" name="icon" placeholder="Icon (emoji)" value="📦" class="rounded-xl px-3 py-2 text-sm">
    <input type="text" name="color" placeholder="Rang (#hex)" value="#7c3aed" class="rounded-xl px-3 py-2 text-sm">
    <button class="btn-p text-white px-6 py-2 rounded-xl text-sm font-semibold col-span-2 md:col-span-1">+ Qo'shish</button>
  </form>
</div>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">Nom</th><th class="px-4 py-3">Min</th><th class="px-4 py-3">Max</th>
    <th class="px-4 py-3">%/kun</th><th class="px-4 py-3">Kun</th>
    <th class="px-4 py-3">Holat</th><th class="px-4 py-3">Amallar</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT * FROM packages ORDER BY sort_order ASC") as $p):?>
  <tr class="border-b border-white/5">
    <td class="px-4 py-2.5"><span class="text-lg mr-2"><?=$p['icon']?></span><span class="text-white font-medium"><?=htmlspecialchars($p['name'])?></span></td>
    <td class="px-4 py-2.5 text-center text-slate-300"><?=money((float)$p['min_amount'])?></td>
    <td class="px-4 py-2.5 text-center text-slate-300"><?=money((float)$p['max_amount'])?></td>
    <td class="px-4 py-2.5 text-center text-green-400 font-semibold"><?=$p['daily_percent']?>%</td>
    <td class="px-4 py-2.5 text-center text-slate-400"><?=$p['duration_days']?> kun</td>
    <td class="px-4 py-2.5 text-center"><span class="text-xs px-2 py-0.5 rounded-full <?=$p['is_active']?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400'?>"><?=$p['is_active']?'Faol':'Nofaol'?></span></td>
    <td class="px-4 py-2.5 text-center flex gap-2 justify-center">
      <button onclick="toggleEdit('pkg-<?=$p['id']?>')" class="text-xs text-green-400 hover:text-green-300">✏️ Edit</button>
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="pid" value="<?=$p['id']?>"><button class="text-xs text-red-400 hover:text-red-300" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')">🗑</button></form>
    </td>
  </tr>
  <tr id="pkg-<?=$p['id']?>" class="hidden bg-green-500/5">
    <td colspan="7" class="px-4 py-3">
      <form method="POST" class="grid grid-cols-2 md:grid-cols-5 gap-2">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="pid" value="<?=$p['id']?>">
        <input type="text" name="name" value="<?=htmlspecialchars($p['name'])?>" placeholder="Nomi" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="text" name="desc" value="<?=htmlspecialchars($p['description'])?>" placeholder="Tavsif" class="rounded-lg px-3 py-1.5 text-sm col-span-2">
        <input type="number" name="min" value="<?=(int)$p['min_amount']?>" placeholder="Min" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="number" name="max" value="<?=(int)$p['max_amount']?>" placeholder="Max" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="number" step="0.01" name="pct" value="<?=$p['daily_percent']?>" placeholder="%" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="number" name="days" value="<?=$p['duration_days']?>" placeholder="Kunlar" class="rounded-lg px-3 py-1.5 text-sm">
        <select name="active" class="rounded-lg px-3 py-1.5 text-sm">
          <option value="1" <?=$p['is_active']?'selected':''?>>✅ Faol</option>
          <option value="0" <?=!$p['is_active']?'selected':''?>>❌ Nofaol</option>
        </select>
        <button class="btn-p text-white px-4 py-1.5 rounded-lg text-sm font-semibold">💾 Saqlash</button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'promo'):?>
<h2 class="text-2xl font-bold text-white mb-6">🎁 Promo kodlar</h2>
<div class="glass rounded-2xl p-6 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">+ Yangi promo kod</h3>
  <form method="POST" class="flex flex-wrap gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="create">
    <input type="text" name="code" placeholder="KOD (TORTINMANG2025)" required class="rounded-xl px-3 py-2 text-sm w-40 uppercase">
    <input type="number" name="amount" placeholder="Mukofot (so'm)" required class="rounded-xl px-3 py-2 text-sm w-36">
    <input type="number" name="max_uses" placeholder="Max foydalanish" value="100" class="rounded-xl px-3 py-2 text-sm w-36">
    <input type="datetime-local" name="expires" class="rounded-xl px-3 py-2 text-sm">
    <button class="btn-p text-white px-5 py-2 rounded-xl text-sm font-semibold">+ Yaratish</button>
  </form>
</div>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">Kod</th><th class="px-4 py-3">Mukofot</th>
    <th class="px-4 py-3">Ishlatildi</th><th class="px-4 py-3">Max</th>
    <th class="px-4 py-3">Tugash</th><th class="px-4 py-3">Holat</th><th class="px-4 py-3">Amal</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT * FROM promo_codes ORDER BY created_at DESC") as $p):?>
  <tr class="border-b border-white/5">
    <td class="px-4 py-2.5 font-mono font-bold text-green-400"><?=htmlspecialchars($p['code'])?></td>
    <td class="px-4 py-2.5 text-center text-green-400"><?=money((float)$p['reward_amount'])?> so'm</td>
    <td class="px-4 py-2.5 text-center text-white"><?=$p['used_count']?></td>
    <td class="px-4 py-2.5 text-center text-slate-400"><?=$p['max_uses']?></td>
    <td class="px-4 py-2.5 text-center text-xs text-slate-500"><?=$p['expires_at']??'—'?></td>
    <td class="px-4 py-2.5 text-center"><span class="text-xs px-2 py-0.5 rounded-full <?=$p['is_active']?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400'?>"><?=$p['is_active']?'Faol':'Nofaol'?></span></td>
    <td class="px-4 py-2.5 flex gap-2">
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="pid" value="<?=$p['id']?>"><button class="text-xs text-yellow-400">⏸</button></form>
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="pid" value="<?=$p['id']?>"><button class="text-xs text-red-400" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')">🗑</button></form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'news'):?>
<h2 class="text-2xl font-bold text-white mb-6">📰 Yangiliklar</h2>
<div class="glass rounded-2xl p-6 mb-6">
  <form method="POST" class="space-y-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="add">
    <div class="flex gap-3">
      <input type="text" name="emoji" value="📰" class="rounded-xl px-3 py-2 text-sm w-20">
      <input type="text" name="title" placeholder="Sarlavha" required class="flex-1 rounded-xl px-3 py-2 text-sm">
    </div>
    <textarea name="content" placeholder="Matn..." rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"></textarea>
    <button class="btn-p text-white px-6 py-2 rounded-xl text-sm font-semibold">+ Qo'shish</button>
  </form>
</div>
<div class="space-y-3">
<?php foreach($db->fetchAll("SELECT * FROM news ORDER BY created_at DESC") as $n):?>
<div class="glass rounded-xl p-4 flex justify-between items-start gap-4">
  <div>
    <div class="font-semibold text-white"><?=$n['emoji']?> <?=htmlspecialchars($n['title'])?></div>
    <div class="text-sm text-slate-400 mt-1"><?=htmlspecialchars(mb_substr($n['content'],0,120))?>...</div>
    <div class="text-xs text-slate-500 mt-1"><?=$n['created_at']?></div>
  </div>
  <form method="POST" class="flex-shrink-0">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="nid" value="<?=$n['id']?>">
    <button class="text-red-400 text-sm" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')">🗑</button>
  </form>
</div>
<?php endforeach;?>
</div>
<?php endif;?>

<?php if ($page === 'broadcast'):?>
<h2 class="text-2xl font-bold text-white mb-6">📢 Broadcast</h2>
<div class="glass rounded-2xl p-6 max-w-2xl">
  <p class="text-slate-400 text-sm mb-4">⚠️ Bu xabar barcha (yoki tanlangan) foydalanuvchilarga yuboriladi.</p>
  <form method="POST" class="space-y-4">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <div>
      <label class="text-xs text-slate-500 mb-2 block">Kimga yuborish</label>
      <select name="target" class="w-full rounded-xl px-3 py-2 text-sm">
        <option value="all">👥 Barcha foydalanuvchilar (<?=(int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_blocked=0")?> kishi)</option>
        <option value="vip">👑 Faqat VIP a'zolar (<?=(int)$db->fetchColumn("SELECT COUNT(DISTINCT user_id) FROM vip_memberships WHERE is_active=1 AND expires_at>NOW()")?> kishi)</option>
        <option value="active_investors">📈 Faol investorlar (<?=(int)$db->fetchColumn("SELECT COUNT(DISTINCT user_id) FROM investments WHERE status='active'")?> kishi)</option>
      </select>
    </div>
    <div>
      <label class="text-xs text-slate-500 mb-2 block">Matn formati</label>
      <select name="parse_mode" class="w-full rounded-xl px-3 py-2 text-sm">
        <option value="none">📄 Oddiy matn (xavfsiz)</option>
        <option value="markdown">✨ Markdown (*qalin*, _kursiv_, `kod`)</option>
      </select>
    </div>
    <textarea name="message" placeholder="Xabar matni..." rows="6" required class="w-full rounded-xl px-4 py-3 text-sm"></textarea>
    <div class="flex items-center gap-3">
      <button class="btn-p text-white px-6 py-2.5 rounded-xl text-sm font-semibold" onclick="return confirm('Haqiqatan ham yubormoqchimisiz?')">📤 Yuborish</button>
    </div>
  </form>
</div>
<?php endif;?>

<?php if ($page === 'lottery'):?>
<h2 class="text-2xl font-bold text-white mb-6">🎰 Lotereya sovrinlari</h2>
<div class="glass rounded-2xl p-6 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">+ Yangi sovrin qo'shish</h3>
  <form method="POST" class="flex flex-wrap gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="add_prize">
    <input type="text" name="name" placeholder="Nomi (10 000 so'm)" required class="rounded-xl px-3 py-2 text-sm w-40">
    <select name="prize_type" class="rounded-xl px-3 py-2 text-sm">
      <option value="balance">💰 Balans</option>
      <option value="spin">🔄 +1 Spin</option>
      <option value="nothing">😅 Omadsiz</option>
    </select>
    <input type="number" name="amount" placeholder="Summa (so'm)" value="0" class="rounded-xl px-3 py-2 text-sm w-32">
    <input type="number" name="weight" placeholder="Og'irlik (1-100)" value="10" class="rounded-xl px-3 py-2 text-sm w-32">
    <button class="btn-p text-white px-5 py-2 rounded-xl text-sm font-semibold">+ Qo'shish</button>
  </form>
</div>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">Nomi</th><th class="px-4 py-3">Turi</th>
    <th class="px-4 py-3">Summa</th><th class="px-4 py-3">Og'irlik</th>
    <th class="px-4 py-3">Ehtimol</th><th class="px-4 py-3">Holat</th><th class="px-4 py-3">Amal</th>
  </tr></thead>
  <tbody>
  <?php
    $prizes = $db->fetchAll("SELECT * FROM lottery_prizes ORDER BY weight DESC");
    $totalW = array_sum(array_column($prizes, 'weight'));
    foreach($prizes as $p):
    $chance = $totalW > 0 ? round($p['weight']/$totalW*100,1) : 0;
  ?>
  <tr class="border-b border-white/5">
    <td class="px-4 py-2.5 text-white font-medium"><?=htmlspecialchars($p['name'])?></td>
    <td class="px-4 py-2.5 text-center text-slate-400"><?=$p['prize_type']?></td>
    <td class="px-4 py-2.5 text-center text-green-400"><?=money((float)$p['amount'])?></td>
    <td class="px-4 py-2.5 text-center text-green-400"><?=$p['weight']?></td>
    <td class="px-4 py-2.5 text-center text-yellow-400"><?=$chance?>%</td>
    <td class="px-4 py-2.5 text-center"><span class="text-xs px-2 py-0.5 rounded-full <?=$p['is_active']?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400'?>"><?=$p['is_active']?'Faol':'Nofaol'?></span></td>
    <td class="px-4 py-2.5 flex gap-2">
      <button onclick="toggleEdit('lp-<?=$p['id']?>')" class="text-xs text-green-400">✏️</button>
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="toggle_prize"><input type="hidden" name="pid" value="<?=$p['id']?>"><button class="text-xs text-yellow-400">⏸</button></form>
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="delete_prize"><input type="hidden" name="pid" value="<?=$p['id']?>"><button class="text-xs text-red-400" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')">🗑</button></form>
    </td>
  </tr>
  <tr id="lp-<?=$p['id']?>" class="hidden bg-green-500/5">
    <td colspan="7" class="px-4 py-3">
      <form method="POST" class="flex flex-wrap gap-2">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="edit_prize">
        <input type="hidden" name="pid" value="<?=$p['id']?>">
        <input type="text" name="name" value="<?=htmlspecialchars($p['name'])?>" placeholder="Nomi" class="rounded-lg px-3 py-1.5 text-sm w-36">
        <select name="prize_type" class="rounded-lg px-3 py-1.5 text-sm">
          <option value="balance" <?=$p['prize_type']==='balance'?'selected':''?>>💰 Balans</option>
          <option value="spin" <?=$p['prize_type']==='spin'?'selected':''?>>🔄 +1 Spin</option>
          <option value="nothing" <?=$p['prize_type']==='nothing'?'selected':''?>>😅 Omadsiz</option>
        </select>
        <input type="number" name="amount" value="<?=(int)$p['amount']?>" placeholder="Summa" class="rounded-lg px-3 py-1.5 text-sm w-28">
        <input type="number" name="weight" value="<?=$p['weight']?>" placeholder="Og'irlik" class="rounded-lg px-3 py-1.5 text-sm w-24">
        <button class="btn-p text-white px-4 py-1.5 rounded-lg text-sm font-semibold">💾 Saqlash</button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'vip'):?>
<h2 class="text-2xl font-bold text-white mb-6">👑 VIP boshqaruv</h2>
<div class="glass rounded-2xl p-6 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">🎁 Foydalanuvchiga VIP berish</h3>
  <form method="POST" class="flex flex-wrap gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="add_vip">
    <input type="number" name="uid" placeholder="User ID" required class="rounded-xl px-3 py-2 text-sm w-28">
    <select name="tier" class="rounded-xl px-3 py-2 text-sm">
      <option value="silver">🥈 Silver</option>
      <option value="gold">🥇 Gold</option>
      <option value="diamond">💎 Diamond</option>
    </select>
    <input type="number" name="days" value="30" placeholder="Kun" class="rounded-xl px-3 py-2 text-sm w-24">
    <button class="btn-g text-white px-5 py-2 rounded-xl text-sm font-semibold">✅ Berish</button>
  </form>
</div>
<h3 class="text-slate-300 font-semibold mb-3">Faol VIP a'zolar</h3>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">Foydalanuvchi</th><th class="px-4 py-3">Daraja</th>
    <th class="px-4 py-3">Boshlanish</th><th class="px-4 py-3">Tugash</th><th class="px-4 py-3">Amal</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT v.*,u.first_name,u.username FROM vip_memberships v JOIN users u ON v.user_id=u.id WHERE v.is_active=1 AND v.expires_at>NOW() ORDER BY v.expires_at ASC") as $v):?>
  <tr class="border-b border-white/5">
    <td class="px-4 py-2.5"><div class="text-white"><?=htmlspecialchars($v['first_name'])?></div><div class="text-xs text-slate-500">@<?=$v['username']?> | ID:<?=$v['user_id']?></div></td>
    <td class="px-4 py-2.5 text-center">
      <span class="px-2 py-0.5 rounded-full text-xs <?=match($v['tier']){'silver'=>'bg-slate-500/30 text-slate-300','gold'=>'bg-yellow-500/20 text-yellow-400',default=>'bg-cyan-500/20 text-cyan-400'}?>"><?=ucfirst($v['tier'])?></span>
    </td>
    <td class="px-4 py-2.5 text-center text-xs text-slate-500"><?=substr($v['started_at'],0,10)?></td>
    <td class="px-4 py-2.5 text-center text-xs <?=strtotime($v['expires_at'])<strtotime('+3 days')?'text-red-400':'text-slate-400'?>"><?=substr($v['expires_at'],0,10)?></td>
    <td class="px-4 py-2.5 text-center">
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="revoke_vip"><input type="hidden" name="uid" value="<?=$v['user_id']?>"><button class="text-xs text-red-400" onclick="return confirm('VIP ni bekor qilasizmi?')">✗ Bekor</button></form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'achievements'):?>
<h2 class="text-2xl font-bold text-white mb-6">🏆 Yutuqlar</h2>
<div class="glass rounded-2xl p-6 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">+ Yangi yutuq</h3>
  <form method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="add">
    <input type="text" name="ach_key" placeholder="kalit (first_deposit)" required class="rounded-xl px-3 py-2 text-sm">
    <input type="text" name="name" placeholder="Nomi" required class="rounded-xl px-3 py-2 text-sm">
    <input type="text" name="description" placeholder="Tavsif" required class="rounded-xl px-3 py-2 text-sm col-span-2">
    <input type="text" name="icon" placeholder="Emoji" value="🏆" class="rounded-xl px-3 py-2 text-sm w-20">
    <select name="req_type" class="rounded-xl px-3 py-2 text-sm">
      <option value="deposits_count">Depozitlar soni</option>
      <option value="referrals_count">Referallar soni</option>
      <option value="streak_days">Streak kunlar</option>
      <option value="total_earned">Jami daromad</option>
      <option value="investments_count">Investitsiyalar</option>
    </select>
    <input type="number" name="req_value" placeholder="Qiymat" required class="rounded-xl px-3 py-2 text-sm">
    <input type="number" name="reward" placeholder="Mukofot (so'm)" required class="rounded-xl px-3 py-2 text-sm">
    <input type="number" name="sort_order" placeholder="Tartib" value="0" class="rounded-xl px-3 py-2 text-sm">
    <button class="btn-p text-white px-5 py-2 rounded-xl text-sm font-semibold col-span-2 md:col-span-1">+ Qo'shish</button>
  </form>
</div>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">Yutuq</th><th class="px-4 py-3">Shart</th>
    <th class="px-4 py-3">Qiymat</th><th class="px-4 py-3">Mukofot</th>
    <th class="px-4 py-3">Olinganlar</th><th class="px-4 py-3">Holat</th><th class="px-4 py-3">Amal</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT a.*, (SELECT COUNT(*) FROM user_achievements ua WHERE ua.achievement_id=a.id AND ua.claimed=1) as claimed_count FROM achievements a ORDER BY a.sort_order ASC") as $a):?>
  <tr class="border-b border-white/5">
    <td class="px-4 py-2.5"><span class="text-lg mr-2"><?=$a['icon']?></span><span class="text-white"><?=htmlspecialchars($a['name'])?></span><div class="text-xs text-slate-500"><?=$a['achievement_key']?></div></td>
    <td class="px-4 py-2.5 text-center text-slate-400 text-xs"><?=$a['requirement_type']?></td>
    <td class="px-4 py-2.5 text-center text-white"><?=number_format((float)$a['requirement_value'],0,'','.')?></td>
    <td class="px-4 py-2.5 text-center text-green-400"><?=money((float)$a['reward'])?></td>
    <td class="px-4 py-2.5 text-center text-green-400"><?=$a['claimed_count']?> kishi</td>
    <td class="px-4 py-2.5 text-center"><span class="text-xs px-2 py-0.5 rounded-full <?=$a['is_active']?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400'?>"><?=$a['is_active']?'Faol':'Nofaol'?></span></td>
    <td class="px-4 py-2.5 flex gap-2">
      <button onclick="toggleEdit('ach-<?=$a['id']?>')" class="text-xs text-green-400">✏️</button>
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="aid" value="<?=$a['id']?>"><button class="text-xs text-yellow-400">⏸</button></form>
      <form method="POST" class="inline"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="aid" value="<?=$a['id']?>"><button class="text-xs text-red-400" onclick="return confirm('O\'chirishni tasdiqlaysizmi?')">🗑</button></form>
    </td>
  </tr>
  <tr id="ach-<?=$a['id']?>" class="hidden bg-green-500/5">
    <td colspan="7" class="px-4 py-3">
      <form method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="aid" value="<?=$a['id']?>">
        <input type="text" name="name" value="<?=htmlspecialchars($a['name'])?>" placeholder="Nomi" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="text" name="description" value="<?=htmlspecialchars($a['description'])?>" placeholder="Tavsif" class="rounded-lg px-3 py-1.5 text-sm col-span-2">
        <input type="text" name="icon" value="<?=$a['icon']?>" placeholder="Emoji" class="rounded-lg px-3 py-1.5 text-sm w-16">
        <select name="req_type" class="rounded-lg px-3 py-1.5 text-sm">
          <?php foreach(['deposits_count'=>'Depozitlar','referrals_count'=>'Referallar','streak_days'=>'Streak','total_earned'=>'Daromad','investments_count'=>'Investitsiyalar'] as $k=>$v):?>
          <option value="<?=$k?>" <?=$a['requirement_type']===$k?'selected':''?>><?=$v?></option>
          <?php endforeach;?>
        </select>
        <input type="number" name="req_value" value="<?=(float)$a['requirement_value']?>" placeholder="Qiymat" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="number" name="reward" value="<?=(float)$a['reward']?>" placeholder="Mukofot" class="rounded-lg px-3 py-1.5 text-sm">
        <input type="number" name="sort_order" value="<?=$a['sort_order']?>" placeholder="Tartib" class="rounded-lg px-3 py-1.5 text-sm">
        <button class="btn-p text-white px-4 py-1.5 rounded-lg text-sm font-semibold">💾 Saqlash</button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'settings'):?>
<h2 class="text-2xl font-bold text-white mb-6">⚙️ Sozlamalar</h2>
<?php
$settings = [];
foreach ($db->fetchAll("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$cfg = Config::get('finance');
function sv(array $s, string $k, mixed $d=''): string { return htmlspecialchars((string)($s[$k]??$d)); }
?>
<form method="POST" class="space-y-6 max-w-3xl">
  <input type="hidden" name="csrf" value="<?=$csrf?>">
  <div class="glass rounded-2xl p-6">
    <h3 class="font-semibold text-slate-300 mb-4">💳 Karta ma'lumotlari</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div><label class="text-xs text-slate-500">Uzcard raqami</label><input type="text" name="s[deposit_card_uzcard]" value="<?=sv($settings,'deposit_card_uzcard',$cfg['deposit_card_uzcard'])?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-xs text-slate-500">Humo raqami</label><input type="text" name="s[deposit_card_humo]" value="<?=sv($settings,'deposit_card_humo',$cfg['deposit_card_humo'])?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-xs text-slate-500">Karta egasi</label><input type="text" name="s[deposit_card_holder]" value="<?=sv($settings,'deposit_card_holder',$cfg['deposit_card_holder'])?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
    </div>
  </div>
  <div class="glass rounded-2xl p-6">
    <h3 class="font-semibold text-slate-300 mb-4">🔧 Tizim holati</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php foreach(['deposit_enabled'=>'Depozit','withdrawal_enabled'=>'Yechish','maintenance_mode'=>'Texnik ishlar'] as $k=>$l):?>
      <div><label class="text-xs text-slate-500"><?=$l?></label>
        <select name="s[<?=$k?>]" class="w-full rounded-xl px-3 py-2 text-sm mt-1">
          <option value="1" <?=sv($settings,$k,'1')==='1'?'selected':''?>>✅ Yoqilgan</option>
          <option value="0" <?=sv($settings,$k,'1')==='0'?'selected':''?>>❌ O'chirilgan</option>
        </select>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <div class="glass rounded-2xl p-6">
    <h3 class="font-semibold text-slate-300 mb-4">📊 Fake statistika</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div><label class="text-xs text-slate-500">Online min</label><input type="number" name="s[fake_online_min]" value="<?=sv($settings,'fake_online_min','847')?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-xs text-slate-500">Online max</label><input type="number" name="s[fake_online_max]" value="<?=sv($settings,'fake_online_max','2341')?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-xs text-slate-500">Jami users</label><input type="number" name="s[fake_total_users]" value="<?=sv($settings,'fake_total_users','15847')?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-xs text-slate-500">Jami to'langan (sum)</label><input type="number" name="s[fake_total_paid]" value="<?=sv($settings,'fake_total_paid','2450000000')?>" class="w-full rounded-xl px-3 py-2 text-sm mt-1"></div>
    </div>
  </div>
  <button class="btn-p text-white px-8 py-3 rounded-xl font-semibold">💾 Saqlash</button>
</form>
<?php endif;?>

<?php if ($page === 'investments'):?>
<h2 class="text-2xl font-bold text-white mb-6">📈 Investitsiyalar</h2>
<?php
$filter  = $_GET['f'] ?? 'active';
$where   = match($filter) { 'completed'=>"status='completed'", 'all'=>'1=1', default=>"status='active'" };
$invests = $db->fetchAll("SELECT i.*,u.first_name,u.username,p.name as pkg FROM investments i JOIN users u ON i.user_id=u.id JOIN packages p ON i.package_id=p.id WHERE {$where} ORDER BY i.started_at DESC LIMIT 100");
$totals  = $db->fetch("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as sum, COALESCE(SUM(total_earned),0) as earned FROM investments WHERE status='active'");
?>
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-green-400"><?=$totals['cnt']?></div><div class="text-xs text-slate-500 mt-1">Faol investitsiyalar</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-green-400"><?=money((float)$totals['sum'])?> so'm</div><div class="text-xs text-slate-500 mt-1">Jami kapital</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-yellow-400"><?=money((float)$totals['earned'])?> so'm</div><div class="text-xs text-slate-500 mt-1">Jami to'langan daromad</div></div>
</div>
<div class="flex gap-2 mb-4">
  <a href="?p=investments&f=active"    class="px-4 py-2 rounded-xl text-sm <?=$filter==='active'   ?'btn-p text-white':'glass text-slate-400'?>">Faol</a>
  <a href="?p=investments&f=completed" class="px-4 py-2 rounded-xl text-sm <?=$filter==='completed'?'btn-p text-white':'glass text-slate-400'?>">Yakunlangan</a>
  <a href="?p=investments&f=all"       class="px-4 py-2 rounded-xl text-sm <?=$filter==='all'      ?'btn-p text-white':'glass text-slate-400'?>">Barchasi</a>
</div>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">ID</th>
    <th class="px-4 py-3 text-left">Foydalanuvchi</th>
    <th class="px-4 py-3 text-left">Paket</th>
    <th class="px-4 py-3">Summa</th>
    <th class="px-4 py-3">Kunlik</th>
    <th class="px-4 py-3">Daromad</th>
    <th class="px-4 py-3">Progress</th>
    <th class="px-4 py-3">Holat</th>
    <th class="px-4 py-3">Sana</th>
  </tr></thead>
  <tbody>
  <?php foreach($invests as $i):
    $pct = $i['duration_days'] > 0 ? min(100, round(($i['days_passed']/$i['duration_days'])*100)) : 0;
  ?>
  <tr class="border-b border-white/5 hover:bg-white/[0.02]">
    <td class="px-4 py-2.5 text-slate-500">#<?=$i['id']?></td>
    <td class="px-4 py-2.5">
      <div class="text-white"><?=htmlspecialchars($i['first_name'])?></div>
      <div class="text-xs text-slate-500">@<?=htmlspecialchars($i['username'])?></div>
    </td>
    <td class="px-4 py-2.5 text-green-300"><?=htmlspecialchars($i['pkg'])?></td>
    <td class="px-4 py-2.5 text-center font-semibold text-white"><?=money((float)$i['amount'])?></td>
    <td class="px-4 py-2.5 text-center text-green-400">+<?=money((float)$i['daily_profit'])?></td>
    <td class="px-4 py-2.5 text-center text-yellow-400"><?=money((float)$i['total_earned'])?></td>
    <td class="px-4 py-2.5" style="min-width:100px">
      <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
        <div class="h-full bg-green-500 rounded-full" style="width:<?=$pct?>%"></div>
      </div>
      <div class="text-xs text-slate-500 mt-1"><?=$i['days_passed']?>/<?=$i['duration_days']?> kun</div>
    </td>
    <td class="px-4 py-2.5 text-center">
      <span class="px-2 py-0.5 rounded-full text-xs <?=$i['status']==='active'?'bg-green-500/20 text-green-400':($i['status']==='completed'?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400')?>"><?=$i['status']?></span>
    </td>
    <td class="px-4 py-2.5 text-center text-xs text-slate-500"><?=substr($i['started_at'],0,10)?></td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'security'):?>
<h2 class="text-2xl font-bold text-white mb-6">🔒 Xavfsizlik</h2>

<?php
// Security actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'verify_all_deposited') {
        // Depozit qilgan hamma foydalanuvchini verified qilish
        $rows = $db->query("UPDATE users SET phone_verified=1 WHERE phone_verified=0 AND total_deposit > 0")->rowCount();
        $msg = "✅ {$rows} ta foydalanuvchi tasdiqlandi";
    } elseif ($act === 'block_unverified') {
        // Eski tasdiqlanmaganlarni bloklash (7+ kun ro'yxatdan o'tgan)
        $rows = $db->query("UPDATE users SET is_blocked=1 WHERE phone_verified=0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND total_deposit=0")->rowCount();
        $msg = "🚫 {$rows} ta foydalanuvchi bloklandi";
    } elseif ($act === 'unblock_all_unverified') {
        $rows = $db->query("UPDATE users SET is_blocked=0 WHERE phone_verified=0 AND total_deposit=0")->rowCount();
        $msg = "✅ {$rows} ta foydalanuvchi blokdan chiqarildi";
    } elseif ($act === 'manual_verify' && (int)($_POST['uid']??0)) {
        $uid = (int)$_POST['uid'];
        $db->update('users', ['phone_verified' => 1], 'id=?', [$uid]);
        $msg = "✅ User #{$uid} tasdiqlandi";
    }
}

// Statistika
$secStats = [
    'total'         => (int)$db->fetchColumn("SELECT COUNT(*) FROM users"),
    'verified'      => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE phone_verified=1"),
    'unverified'    => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE phone_verified=0"),
    'unver_dep'     => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE phone_verified=0 AND total_deposit>0"),
    'unver_no_dep'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE phone_verified=0 AND total_deposit=0"),
    'duplicates'    => (int)$db->fetchColumn("SELECT COUNT(*) FROM (SELECT phone, COUNT(*) c FROM users WHERE phone IS NOT NULL GROUP BY phone HAVING c>1) t"),
    'blocked'       => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_blocked=1"),
];
$verPct = $secStats['total'] > 0 ? round($secStats['verified'] / $secStats['total'] * 100) : 0;
?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-green-400"><?=$secStats['verified']?></div><div class="text-xs text-slate-500 mt-1">✅ Tasdiqlangan</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-yellow-400"><?=$secStats['unverified']?></div><div class="text-xs text-slate-500 mt-1">⚠️ Tasdiqlanmagan</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-red-400"><?=$secStats['duplicates']?></div><div class="text-xs text-slate-500 mt-1">🚨 Dublikat telefon</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-2xl font-bold text-teal-400"><?=$verPct?>%</div><div class="text-xs text-slate-500 mt-1">📊 Tasdiqlash %</div></div>
</div>

<!-- Progress bar -->
<div class="glass rounded-2xl p-5 mb-6">
  <div class="flex justify-between text-sm mb-2">
    <span class="text-slate-300 font-semibold">Telefon tasdiqlash holati</span>
    <span class="text-green-400"><?=$secStats['verified']?> / <?=$secStats['total']?></span>
  </div>
  <div class="h-3 bg-white/10 rounded-full overflow-hidden">
    <div class="h-full bg-gradient-to-r from-green-500 to-emerald-400 rounded-full" style="width:<?=$verPct?>%"></div>
  </div>
</div>

<!-- Actions -->
<div class="glass rounded-2xl p-5 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">⚡ Ommaviy amallar</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <form method="POST">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="verify_all_deposited">
      <button class="btn-g text-white w-full py-2 rounded-xl text-sm font-semibold" onclick="return confirm('Depozit qilgan barcha userlarni tasdiqlash?')">
        ✅ Depozitlilarni tasdiqlash (<?=$secStats['unver_dep']?> kishi)
      </button>
    </form>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="block_unverified">
      <button class="btn-r text-white w-full py-2 rounded-xl text-sm font-semibold" onclick="return confirm('7+ kun old tasdiqlanmagan depozitsiz userlarni bloklash?')">
        🚫 Eskilarni bloklash (<?=$secStats['unver_no_dep']?> kishi)
      </button>
    </form>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="unblock_all_unverified">
      <button class="glass text-slate-300 w-full py-2 rounded-xl text-sm font-semibold border border-white/10">
        🔓 Blokdan chiqarish
      </button>
    </form>
  </div>
</div>

<!-- Manual verify -->
<div class="glass rounded-2xl p-5 mb-6">
  <h3 class="text-sm font-semibold text-slate-300 mb-4">🔑 Qo'lda tasdiqlash</h3>
  <form method="POST" class="flex gap-3">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="action" value="manual_verify">
    <input type="number" name="uid" placeholder="User ID" class="rounded-xl px-4 py-2 text-sm w-36">
    <button class="btn-p text-white px-5 py-2 rounded-xl text-sm font-semibold">✅ Tasdiqlash</button>
  </form>
</div>

<!-- Tasdiqlanmagan foydalanuvchilar ro'yxati -->
<h3 class="text-slate-300 font-semibold mb-3">⚠️ Tasdiqlanmagan foydalanuvchilar (so'nggi 50)</h3>
<div class="glass rounded-2xl overflow-x-auto">
<table class="w-full text-sm">
  <thead><tr class="text-slate-500 text-xs border-b border-white/5">
    <th class="px-4 py-3 text-left">ID</th>
    <th class="px-4 py-3 text-left">Foydalanuvchi</th>
    <th class="px-4 py-3">Depozit</th>
    <th class="px-4 py-3">Balans</th>
    <th class="px-4 py-3">Holat</th>
    <th class="px-4 py-3">Ro'yxat sanasi</th>
    <th class="px-4 py-3">Amal</th>
  </tr></thead>
  <tbody>
  <?php foreach($db->fetchAll("SELECT * FROM users WHERE phone_verified=0 ORDER BY created_at DESC LIMIT 50") as $u):?>
  <tr class="border-b border-white/5 hover:bg-white/[0.02]">
    <td class="px-4 py-2.5 text-slate-500"><?=$u['id']?></td>
    <td class="px-4 py-2.5">
      <div class="text-white font-medium"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></div>
      <div class="text-xs text-slate-500">TG: <?=$u['telegram_id']?></div>
    </td>
    <td class="px-4 py-2.5 text-center <?=$u['total_deposit']>0?'text-green-400':'text-slate-500'?>"><?=money((float)$u['total_deposit'])?></td>
    <td class="px-4 py-2.5 text-center text-white"><?=money((float)$u['balance'])?></td>
    <td class="px-4 py-2.5 text-center">
      <span class="px-2 py-0.5 rounded-full text-xs <?=$u['is_blocked']?'bg-red-500/20 text-red-400':'bg-yellow-500/20 text-yellow-400'?>">
        <?=$u['is_blocked']?'Bloklangan':'Tasdiqlanmagan'?>
      </span>
    </td>
    <td class="px-4 py-2.5 text-center text-xs text-slate-500"><?=substr($u['created_at'],0,10)?></td>
    <td class="px-4 py-2.5">
      <form method="POST" class="inline">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="manual_verify">
        <input type="hidden" name="uid" value="<?=$u['id']?>">
        <button class="text-xs text-green-400 hover:text-green-300">✅ Tasdiqlash</button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>
<?php endif;?>

<?php if ($page === 'statistics'):?>
<h2 class="text-2xl font-bold text-white mb-6">📉 Statistika</h2>
<?php
// So'nggi 14 kun uchun ma'lumotlar
$days14 = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $days14[] = $date;
}
$labels    = array_map(fn($d) => date('d.m', strtotime($d)), $days14);
$regData   = [];
$depData   = [];
$wdData    = [];
foreach ($days14 as $date) {
    $regData[] = (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?", [$date]);
    $depData[] = (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE DATE(created_at)=? AND status='approved'", [$date]);
    $wdData[]  = (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE DATE(created_at)=? AND status='approved'", [$date]);
}
$labelsJson = json_encode($labels);
$regJson    = json_encode($regData);
$depJson    = json_encode($depData);
$wdJson     = json_encode($wdData);
// Umumiy raqamlar
$totals = [
    'users'       => (int)$db->fetchColumn('SELECT COUNT(*) FROM users'),
    'deposits'    => (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status='approved'"),
    'withdrawals' => (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='approved'"),
    'investments' => (float)$db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status='active'"),
    'profit'      => (float)$db->fetchColumn("SELECT COALESCE(SUM(total_earned),0) FROM investments"),
    'lottery_paid'=> (float)$db->fetchColumn("SELECT COALESCE(SUM(result_amount),0) FROM lottery_spins"),
];
?>
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
  <div class="glass rounded-2xl p-4"><div class="text-green-400 font-bold text-xl"><?=money($totals['users'])?></div><div class="text-xs text-slate-500 mt-1">👥 Jami users</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-green-400 font-bold text-xl"><?=money($totals['deposits'])?> so'm</div><div class="text-xs text-slate-500 mt-1">💰 Jami depozit</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-red-400 font-bold text-xl"><?=money($totals['withdrawals'])?> so'm</div><div class="text-xs text-slate-500 mt-1">💸 Jami yechilgan</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-teal-400 font-bold text-xl"><?=money($totals['investments'])?> so'm</div><div class="text-xs text-slate-500 mt-1">📈 Faol investitsiya</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-yellow-400 font-bold text-xl"><?=money($totals['profit'])?> so'm</div><div class="text-xs text-slate-500 mt-1">🏆 Jami invest daromad</div></div>
  <div class="glass rounded-2xl p-4"><div class="text-teal-400 font-bold text-xl"><?=money($totals['lottery_paid'])?> so'm</div><div class="text-xs text-slate-500 mt-1">🎰 Lotereya to'langan</div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="glass rounded-2xl p-5">
    <h3 class="text-sm font-semibold text-slate-300 mb-4">👥 Kunlik registratsiyalar (14 kun)</h3>
    <canvas id="chartReg" height="160"></canvas>
  </div>
  <div class="glass rounded-2xl p-5">
    <h3 class="text-sm font-semibold text-slate-300 mb-4">💰 Kunlik depozit vs yechish (so'm)</h3>
    <canvas id="chartFin" height="160"></canvas>
  </div>
</div>
<script>
const lbl = <?=$labelsJson?>;
const cfg = {responsive:true,plugins:{legend:{labels:{color:'#94a3b8',font:{size:11}}}},
  scales:{x:{ticks:{color:'#475569'},grid:{color:'rgba(255,255,255,.05)'}},
          y:{ticks:{color:'#475569'},grid:{color:'rgba(255,255,255,.05)'}}}};

new Chart(document.getElementById('chartReg'),{type:'bar',data:{labels:lbl,datasets:[{
  label:'Yangi users',data:<?=$regJson?>,
  backgroundColor:'rgba(99,102,241,.5)',borderColor:'#6366f1',borderWidth:1.5,borderRadius:5
}]},options:cfg});

new Chart(document.getElementById('chartFin'),{type:'line',data:{labels:lbl,datasets:[
  {label:'Depozit',data:<?=$depJson?>,borderColor:'#22c55e',backgroundColor:'rgba(34,197,94,.1)',fill:true,tension:.4,pointRadius:3},
  {label:'Yechish',data:<?=$wdJson?>,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.1)',fill:true,tension:.4,pointRadius:3}
]},options:cfg});
</script>
<?php endif;?>

<?php if ($page === 'password'):?>
<h2 class="text-2xl font-bold text-white mb-6">🔑 Parolni o'zgartirish</h2>
<div class="glass rounded-2xl p-6 max-w-md">
  <form method="POST" class="space-y-4">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <div><label class="text-xs text-slate-500">Joriy parol</label><input type="password" name="cur" required class="w-full rounded-xl px-4 py-2.5 text-sm mt-1"></div>
    <div><label class="text-xs text-slate-500">Yangi parol</label><input type="password" name="new" required minlength="8" class="w-full rounded-xl px-4 py-2.5 text-sm mt-1"></div>
    <div><label class="text-xs text-slate-500">Yangi parolni tasdiqlang</label><input type="password" name="con" required class="w-full rounded-xl px-4 py-2.5 text-sm mt-1"></div>
    <button class="btn-p w-full text-white py-3 rounded-xl font-semibold">🔒 O'zgartirish</button>
  </form>
</div>
<?php endif;?>

</main>
</div>
<?php endif;?>

<script>
function toggleEdit(id) {
  const row = document.getElementById(id);
  if (row) row.classList.toggle('hidden');
}

function toggleUser(id) {
  const row = document.getElementById('ua-' + id);
  if (row) row.classList.toggle('hidden');
}

(() => {
  const drawer = document.getElementById('admin-mobile-menu');
  const backdrop = document.getElementById('admin-menu-backdrop');
  const openButton = document.getElementById('admin-menu-open');
  const closeButton = document.getElementById('admin-menu-close');

  function setDrawer(open) {
    if (!drawer || !backdrop) return;
    drawer.classList.toggle('is-open', open);
    backdrop.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', String(!open));
    backdrop.setAttribute('aria-hidden', String(!open));
    if (openButton) openButton.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
  }

  openButton?.addEventListener('click', () => setDrawer(true));
  closeButton?.addEventListener('click', () => setDrawer(false));
  backdrop?.addEventListener('click', () => setDrawer(false));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') setDrawer(false);
  });

  // PWA: never cache authenticated HTML. The service worker only keeps install assets.
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js', {scope: './'}).catch(() => {
        // The panel remains fully usable when service workers are unavailable.
      });
    });
  }

  const installButtons = Array.from(document.querySelectorAll('.pwa-install'));
  const iosHints = Array.from(document.querySelectorAll('[id^="pwa-ios-hint"]'));
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  let deferredInstallPrompt = null;

  function setInstallVisible(visible) {
    installButtons.forEach(button => button.classList.toggle('is-visible', visible));
  }

  if (isIos && !isStandalone) {
    iosHints.forEach(hint => hint.classList.remove('hidden'));
  }

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
    setInstallVisible(true);
  });

  installButtons.forEach(button => button.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    setInstallVisible(false);
  }));

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    setInstallVisible(false);
    iosHints.forEach(hint => hint.classList.add('hidden'));
  });
})();
</script>
</body>
</html>
