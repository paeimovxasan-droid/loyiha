'use strict';
/* ═══════════════════════════════════════════════════
   TORTINMANG Mini App  ·  app.js
   SVG-only, no emoji icons
═══════════════════════════════════════════════════ */

const API = 'https://app.tortinmang.uz/api/v1/';
// tg lazy — init() ichida qayta olinadi
let tg   = null;
let initData = '';
let user     = null;
let taskClicks = {};

/* ── SVG ICON LIBRARY ─────────────────────────────────────── */
const I = {
  diamond:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/><path d="M12 22L6 9l6-6 6 6z"/></svg>`,
  trend:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>`,
  wallet:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="3"/><path d="M16 3H8L2 7h20z"/></svg>`,
  users:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>`,
  user:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
  star:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
  gift:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>`,
  trophy:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 010-5H6"/><path d="M18 9h1.5a2.5 2.5 0 000-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0012 0V2z"/></svg>`,
  medal:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a6 6 0 100-12 6 6 0 000 12z"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/></svg>`,
  spin:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>`,
  news:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22h16a2 2 0 002-2V4a2 2 0 00-2-2H8a2 2 0 00-2 2v16a2 2 0 01-2 2zm0 0a2 2 0 01-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8V6z"/></svg>`,
  top:      `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>`,
  check:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>`,
  bolt:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>`,
  deposit:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>`,
  withdraw: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>`,
  clock:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
  shield:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
  link:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>`,
  copy:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>`,
  ok:       `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`,
  fire:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 11-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z"/></svg>`,
  box:      `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>`,
  coins:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1110.34 18"/><path d="M7 6h1v4"/><path d="M16.71 13.88L17.5 14l-1 2"/></svg>`,
  info:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
};

function ic(name, size = 18, color = '') {
  // Agar size o'rniga color string berilgan bo'lsa
  if (typeof size === 'string') { color = size; size = 18; }
  return I[name]
    ? I[name].replace('<svg ', `<svg width="${size}" height="${size}" style="flex-shrink:0${color?';color:'+color:''}" `)
    : '';
}

/* ── UTILS ────────────────────────────────────────────────── */
const fm  = n => (+n||0).toLocaleString('uz-UZ') + " so'm";
const fp  = n => (+n||0).toFixed(2).replace(/\.?0+$/, '') + '%';

/** Balansni barcha ko'rinadigan joyda yangilash */
function updateBalanceUI(newBalance) {
  user.balance = String(newBalance);
  const els = document.querySelectorAll('#bal-main, #w-bal');
  els.forEach(el => { if (el) el.textContent = fm(newBalance); });
}

function toast(msg, type = 'ok') {
  const t = document.createElement('div');
  t.className = `toast ${type === 'ok' ? 't-ok' : 't-err'}`;
  t.innerHTML = ic(type === 'ok' ? 'ok' : 'info') + `<span>${msg}</span>`;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3200);
}

function confetti() {
  const el = document.createElement('div');
  el.className = 'cf';
  const cols = ['#00ff88','#00d4a0','#00d4ff','#00ffcc','#a7f3d0','#6ee7b7','#34d399'];
  for (let i = 0; i < 55; i++) {
    const p = document.createElement('div');
    p.className = 'cp';
    p.style.cssText = `left:${Math.random()*100}%;background:${cols[~~(Math.random()*cols.length)]};border-radius:${Math.random()>.5?'50%':'3px'};animation-delay:${Math.random()*2}s;animation-duration:${2.2+Math.random()*2}s`;
    el.appendChild(p);
  }
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 5000);
}

/* ── API ──────────────────────────────────────────────────── */
function apiCall(ep, data = {}) {
  data.initData = initData;
  return fetch(API + ep, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).then(r => r.json()).catch(() => ({ success: false, message: 'Tarmoq xatosi' }));
}

/* ── INIT ─────────────────────────────────────────────────── */
(function init() {
  let _retries = 0;

  function startApp() {
    // tg ob'ektini olish
    tg = window.Telegram?.WebApp || null;

    if (tg) {
      tg.ready();
      tg.expand();
      try { tg.setHeaderColor('#080a12'); } catch(e) {}
      try { tg.setBackgroundColor('#080a12'); } catch(e) {}
      initData = tg.initData || '';
    }

    // initData bo'sh — qayta urinib ko'r (max 5 marta, 300ms oraliq)
    if (!initData && _retries < 5) {
      _retries++;
      setTimeout(startApp, 300);
      return;
    }

    // URL hash dan ham urinib ko'r
    if (!initData && window.location.hash && window.location.hash.length > 1) {
      try { initData = decodeURIComponent(window.location.hash.slice(1)); } catch(e) {}
    }

    apiCall('auth.php', {}).then(r => {
      if (r.success && r.user) {
        user = r.user;
        checkChannelThenGo();
      } else {
        const isNoData = !initData;
        // Xabarni aniq ko'rsat — muammoni aniqlash uchun
        const msg = isNoData
          ? 'initData yo\'q — BotFather da Mini App URL ni tekshiring'
          : (r.message || 'Auth xatosi');
        document.getElementById('root').innerHTML =
          `<div style="height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:24px;text-align:center">
            <div style="opacity:.35">${ic('diamond', 60, '#00ff88')}</div>
            <div style="font-size:20px;font-weight:900;color:#e8fff4;letter-spacing:-.5px">TORTINMANG</div>
            <div style="font-size:12px;color:#2d6b50;line-height:1.7;max-width:300px">${msg}</div>
            <button onclick="location.reload()" style="margin-top:8px;padding:11px 28px;border-radius:14px;background:linear-gradient(135deg,#00ff88,#00d4a0);color:#021a0c;border:none;font-size:14px;font-weight:600;cursor:pointer">Qayta urinish</button>
          </div>`;
      }
    }).catch(() => {
      document.getElementById('root').innerHTML =
        `<div style="height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:24px;text-align:center">
          <div style="opacity:.35">${ic('info', 48, '#ef4444')}</div>
          <div style="font-size:15px;font-weight:700;color:#f87171">Server bilan aloqa yo'q</div>
          <div style="font-size:12px;color:#2d6b50">Internet aloqasini tekshiring</div>
          <button onclick="location.reload()" style="margin-top:8px;padding:11px 28px;border-radius:14px;background:linear-gradient(135deg,#00ff88,#00d4a0);color:#021a0c;border:none;font-size:14px;font-weight:600;cursor:pointer">Qayta urinish</button>
        </div>`;
    });
  }

  // DOMContentLoaded dan keyin boshlash
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startApp);
  } else {
    startApp();
  }
})();

/* ── CHANNEL CHECK ────────────────────────────────────────── */
function checkChannelThenGo() {
  // auth.php channel_member ni qaytaradi — alohida so'rov kerak emas
  if (!user.channel_id || user.channel_member) {
    go('home');
    return;
  }
  showChannelGate(user.channel_url, user.channel_id);
}

function showChannelGate(channelUrl, channelId) {
  document.getElementById('root').innerHTML = `
    <div style="height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;padding:32px;text-align:center">
      <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,rgba(0,255,136,.25),rgba(0,212,255,.15));display:flex;align-items:center;justify-content:center;box-shadow:0 0 30px rgba(0,255,136,.3)">
        ${ic('users',38,'#6ee7b7')}
      </div>
      <div>
        <div style="font-size:22px;font-weight:900;color:#e8fff4;letter-spacing:-.5px;margin-bottom:8px">Kanalga qo'shiling</div>
        <div style="font-size:13px;color:#2d6b50;line-height:1.7">
          Tortinmang ilovasidan foydalanish uchun<br>
          rasmiy kanalimizga a'zo bo'ling
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;width:100%;max-width:280px">
        <a href="${channelUrl}" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px 20px;border-radius:14px;background:linear-gradient(135deg,#00ff88,#00d4a0);color:#021a0c;text-decoration:none;font-size:15px;font-weight:700;box-shadow:0 4px 20px rgba(0,255,136,.35)">
          ${ic('link',18,'#021a0c')} Kanalga o'tish
        </a>
        <button onclick="verifyChannel()" style="padding:13px 20px;border-radius:14px;background:rgba(0,255,136,.07);border:1px solid rgba(0,255,136,.18);color:#00ff88;font-size:14px;font-weight:600;cursor:pointer">
          ${ic('ok',16,'#00ff88')} A'zo bo'ldim — Tekshirish
        </button>
      </div>
    </div>`;
}

function verifyChannel() {
  const btn = document.querySelector('[onclick="verifyChannel()"]');
  if (btn) { btn.disabled = true; btn.innerHTML = ic('clock',16) + ' Tekshirilmoqda...'; }
  apiCall('channel.php', {}).then(r => {
    if (r.is_member) {
      go('home');
    } else {
      toast("Hali a'zo emassiz. Kanalga obuna bo'ling!", 'err');
      if (btn) { btn.disabled = false; btn.innerHTML = ic('ok',16,'#00ff88') + " A'zo bo'ldim — Tekshirish"; }
    }
  });
}

/* ── NAV ──────────────────────────────────────────────────── */
let _curPage = 'home';
function go(page) {
  _curPage = page;
  document.querySelectorAll('.page').forEach(p => p.classList.remove('on'));
  document.querySelectorAll('.ni').forEach(n => n.classList.remove('on'));
  document.getElementById('p-' + page)?.classList.add('on');
  document.querySelector(`[data-p="${page}"]`)?.classList.add('on');
  ({ home: renderHome, invest: renderInvest, wallet: renderWallet, ref: renderRef, profile: renderProfile })[page]?.();
}

/* ── PULL-TO-REFRESH ──────────────────────────────────────── */
function initPullToRefresh() {
  const pages = document.getElementById('pages');
  if (!pages) return;
  let startY = 0, pulling = false;
  let indicator = null;

  pages.addEventListener('touchstart', e => {
    if (pages.scrollTop === 0) { startY = e.touches[0].clientY; pulling = true; }
  }, { passive: true });

  pages.addEventListener('touchmove', e => {
    if (!pulling) return;
    const dy = e.touches[0].clientY - startY;
    if (dy > 10 && dy < 80) {
      if (!indicator) {
        indicator = document.createElement('div');
        indicator.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:300;background:rgba(0,255,136,.9);color:#021a0c;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:600;';
        indicator.textContent = '↓ Yangilash uchun torting';
        document.body.appendChild(indicator);
      }
    }
  }, { passive: true });

  pages.addEventListener('touchend', e => {
    if (!pulling) return;
    const dy = e.changedTouches[0].clientY - startY;
    if (indicator) { indicator.remove(); indicator = null; }
    if (dy > 60) {
      // Serverdan balansni qayta yuklash
      apiCall('wallet.php', { action: 'balance' }).then(r => {
        if (r.success && r.user) {
          Object.assign(user, r.user);
          updateBalanceUI(r.user.balance);
          // Hozirgi sahifani qayta render qilish
          ({ home: renderHome, invest: renderInvest, wallet: renderWallet, ref: renderRef, profile: renderProfile })[_curPage]?.();
          toast('Yangilandi', 'ok');
        }
      });
    }
    pulling = false; startY = 0;
  }, { passive: true });
}
// DOM tayyor bo'lgandan keyin ishga tushirish
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPullToRefresh);
} else {
  initPullToRefresh();
}

function openModal(id)  { document.getElementById('m-' + id)?.classList.add('on');    }
function closeModal(id) { document.getElementById('m-' + id)?.classList.remove('on'); }

// Modal foniga bosib yopish
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal')) {
    e.target.classList.remove('on');
  }
});

/* ── HOME ─────────────────────────────────────────────────── */
function renderHome() {
  if (!user) return;
  const vt = user.vip ? { silver:'Silver VIP', gold:'Gold VIP', diamond:'Diamond VIP' }[user.vip.tier] : null;

  // Telefon tasdiqlanmagan banner
  const phoneBanner = user.phone_required ? `
    <div style="margin:0 0 10px;padding:12px 14px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);border-radius:14px;display:flex;align-items:center;gap:10px">
      <div style="flex-shrink:0">${ic('shield',20,'#ef4444')}</div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:700;color:#f87171">Telefon tasdiqlanmagan</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px">Bonus va yechish uchun botda /start bosib telefon tasdiqlang</div>
      </div>
    </div>` : '';

  document.getElementById('p-home').innerHTML = `
  <div class="hdr">
    <div class="hdr-brand">
      <div class="hdr-logo">${ic('diamond', 36, '#00ff88')}</div>
      <div class="hdr-name">TORTINMANG</div>
    </div>
    <div class="hdr-bal" id="bal-main">${fm(user.balance)}</div>
    <div class="hdr-bal-label">Umumiy balans</div>
    <div class="hdr-meta">
      <span class="chip chip-purple">${ic('shield',12)} ${user.badge_label}</span>
      <span class="chip chip-cyan">${ic('star',12)} ${user.level_name}</span>
      ${vt ? `<span class="chip chip-yellow">${ic('star',12)} ${vt}</span>` : ''}
    </div>
  </div>

  <div style="padding:0 16px">
    ${phoneBanner}
    <div class="card card-y" style="cursor:pointer" onclick="claimBonus()">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:40px;height:40px;border-radius:12px;background:rgba(245,158,11,.2);display:flex;align-items:center;justify-content:center">
            ${ic('bolt',20,'#f59e0b')}
          </div>
          <div>
            <div style="font-weight:700;font-size:14px">Kunlik bonus</div>
            <div style="font-size:11px;color:var(--t2);margin-top:2px;display:flex;align-items:center;gap:4px">
              ${ic('fire',12,'#f59e0b')} Streak: ${user.daily_bonus_streak} kun
            </div>
          </div>
        </div>
        <button class="btn btn-p btn-sm" style="width:auto">Olish</button>
      </div>
    </div>

    <div class="svc-grid">
      <div class="svc" onclick="go('invest')">
        <div class="svc-ico ic-p">${ic('trend',22,'#fff')}</div>
        <span class="svc-lbl">Invest</span>
      </div>
      <div class="svc" onclick="openLottery()">
        <div class="svc-ico ic-c">${ic('spin',22,'#fff')}</div>
        <span class="svc-lbl">Lotereya</span>
      </div>
      <div class="svc" onclick="openVip()">
        <div class="svc-ico ic-y">${ic('star',22,'#fff')}</div>
        <span class="svc-lbl">VIP</span>
      </div>
      <div class="svc" onclick="openTasks()">
        <div class="svc-ico ic-g">${ic('check',22,'#fff')}</div>
        <span class="svc-lbl">Vazifalar</span>
      </div>
      <div class="svc" onclick="openPromo()">
        <div class="svc-ico ic-pk">${ic('gift',22,'#fff')}</div>
        <span class="svc-lbl">Promo</span>
      </div>
      <div class="svc" onclick="openAchievements()">
        <div class="svc-ico ic-b">${ic('trophy',22,'#fff')}</div>
        <span class="svc-lbl">Yutuqlar</span>
      </div>
      <div class="svc" onclick="openLeaderboard()">
        <div class="svc-ico ic-r">${ic('top',22,'#fff')}</div>
        <span class="svc-lbl">TOP</span>
      </div>
      <div class="svc" onclick="openNews()">
        <div class="svc-ico ic-t">${ic('news',22,'#fff')}</div>
        <span class="svc-lbl">Yangilik</span>
      </div>
    </div>

    <div class="card" id="home-stats"><div class="spin-w"></div></div>
    <div class="card">
      <div class="st">${ic('coins')} Tranzaksiyalar</div>
      <div id="home-feed"><div class="spin-w"></div></div>
    </div>
  </div>`;

  apiCall('feed.php', { action: 'stats' }).then(r => {
    const el = document.getElementById('home-stats');
    if (!r.success || !el) return;
    el.innerHTML = `
      <div class="st">${ic('info')} Statistika</div>
      <div class="sr"><span class="sl">${ic('users')} Foydalanuvchilar</span><span class="sv">${(+r.total_users||0).toLocaleString()}</span></div>
      <div class="sr"><span class="sl">${ic('bolt',14,'#22c55e')} Online</span><span class="sv" style="color:var(--g)">${(+r.online_users||0).toLocaleString()}</span></div>
      <div class="sr"><span class="sl">${ic('coins')} Jami to'langan</span><span class="sv">${fm(r.total_withdrawals)}</span></div>`;
  });

  apiCall('feed.php', { action: 'list' }).then(r => {
    const el = document.getElementById('home-feed');
    if (!r.success || !el) return;
    el.innerHTML = (r.feeds || []).slice(0, 12).map(f => {
      const dep = f.feed_type === 'deposit';
      return `<div class="fi">
        <div class="fa ${dep ? 'fa-dep' : 'fa-wd'}">${ic(dep ? 'deposit' : 'withdraw', 18)}</div>
        <div style="flex:1">
          <div class="fn">${f.user_display_name}</div>
          <div class="fa-amt" style="color:${dep?'var(--g)':'var(--c)'}">${fm(f.amount)}</div>
        </div>
      </div>`;
    }).join('') || `<div class="empty">${ic('box',36)}<div>Ma'lumot yo'q</div></div>`;
  });
}

/* ── CLAIM BONUS ──────────────────────────────────────────── */
function claimBonus() {
  apiCall('wallet.php', { action: 'daily_bonus' }).then(r => {
    if (r.success) {
      updateBalanceUI(parseFloat(user.balance) + r.bonus);
      user.daily_bonus_streak = r.streak;
      confetti();
      toast(r.message || `+${fm(r.bonus)} bonus olindi!`, 'ok');
    } else {
      toast(r.message || 'Xatolik', 'err');
    }
  });
}

/* ── INVEST PAGE ──────────────────────────────────────────── */
function renderInvest() {
  document.getElementById('p-invest').innerHTML = `
  <div class="pg-hdr">
    <div class="pg-ttl">${ic('trend',22,'#00ff88')} Investitsiya</div>
  </div>
  <div id="invest-stats" class="card card-hi" style="margin-bottom:10px"><div class="spin-w"></div></div>
  <div class="card" style="margin-bottom:10px">
    <div class="st">${ic('box')} Mening investitsiyalarim</div>
    <div id="my-invest-list"><div class="spin-w"></div></div>
  </div>
  <div class="st" style="padding:0 0 8px">${ic('bolt',16,'#f59e0b')} Paketlar</div>
  <div id="pkg-list"><div class="spin-w"></div></div>`;

  apiCall('invest.php', { action: 'packages' }).then(r => {
    const el = document.getElementById('pkg-list');
    if (!r.success || !el) return;
    // Paketlarni global saqlaymiz — onclick da JSON ishlatmaslik uchun
    window._pkgs = {};
    el.innerHTML = (r.packages || []).map(p => {
      window._pkgs[p.id] = p;
      return `
      <div class="pkg anim-in" onclick="openInvestModal(window._pkgs[${p.id}])">
        <div class="pkg-top">
          <div>
            <div class="pkg-name">${p.name}</div>
            <div class="pkg-sub">${fm(p.min_amount)} – ${fm(p.max_amount)}</div>
          </div>
          <div style="text-align:right">
            <div class="pkg-pct">${fp(p.daily_percent)}</div>
            <div class="pkg-sub">kunlik</div>
          </div>
        </div>
        <div class="pkg-row"><span>${ic('clock',12)} Muddat</span><span>${p.duration_days} kun</span></div>
        <div class="pkg-row"><span>${ic('trend',12)} Jami daromad</span><span style="color:var(--g)">${fp(p.total_percent)}</span></div>
        <div class="pkg-row"><span>${ic('coins',12)} Misol (1M)</span><span style="color:var(--c)">${fm(p.example_daily)}/kun</span></div>
        <button class="btn btn-p btn-sm" style="margin-top:10px;width:100%">
          ${ic('bolt',14)} Investitsiya qilish
        </button>
      </div>`;
    }).join('');
  });

  apiCall('invest.php', { action: 'my_investments' }).then(r => {
    const el = document.getElementById('my-invest-list');
    const se = document.getElementById('invest-stats');
    if (!r.success) return;
    if (se && r.stats) {
      const s = r.stats;
      se.innerHTML = `
        <div class="st">${ic('info')} Statistika</div>
        <div class="sr"><span class="sl">${ic('coins')} Aktiv invest</span><span class="sv">${fm(s.active_amount||0)}</span></div>
        <div class="sr"><span class="sl">${ic('trend')} Jami daromad</span><span class="sv" style="color:var(--g)">${fm(s.total_earned||0)}</span></div>
        <div class="sr"><span class="sl">${ic('box')} Aktiv paketlar</span><span class="sv">${s.active_count||0} ta</span></div>`;
    }
    if (!el) return;
    const actives = (r.active || []);
    if (!actives.length) {
      el.innerHTML = `<div class="empty">${ic('box',36)}<div>Hali investitsiya yo'q</div></div>`;
      return;
    }
    el.innerHTML = actives.map(inv => {
      const pct = Math.min(100, Math.round(((+inv.days_passed||0) / (+inv.duration_days||1)) * 100));
      return `<div class="inv anim-in">
        <div style="flex:1">
          <div style="font-weight:700;font-size:13px">${inv.package_name||'Paket'}</div>
          <div style="font-size:12px;color:var(--t2);margin-top:3px">${fm(inv.amount)} → ${fp(inv.daily_percent)}/kun</div>
          <div class="inv-bar"><div class="inv-fill" style="width:${pct}%"></div></div>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">${inv.days_passed||0}/${inv.duration_days} kun</div>
        </div>
        <div style="text-align:right;flex-shrink:0;margin-left:12px">
          <div style="color:var(--g);font-weight:700;font-size:13px">+${fm(inv.daily_profit)}</div>
          <div style="font-size:11px;color:var(--t3)">kunlik</div>
        </div>
      </div>`;
    }).join('');
  });
}

/* ── INVEST MODAL ─────────────────────────────────────────── */
function openInvestModal(pkg) {
  document.getElementById('m-inv-pkg').value = pkg.id;
  document.getElementById('m-inv-title').innerHTML =
    `${ic('trend',20,'#00ff88')} ${pkg.name}`;
  document.getElementById('m-inv-detail').innerHTML = `
    <div class="card card-g" style="margin-bottom:0">
      <div class="sr"><span class="sl">${ic('coins')} Minimal</span><span class="sv">${fm(pkg.min_amount)}</span></div>
      <div class="sr"><span class="sl">${ic('coins')} Maksimal</span><span class="sv">${fm(pkg.max_amount)}</span></div>
      <div class="sr"><span class="sl">${ic('trend')} Kunlik %</span><span class="sv" style="color:var(--g)">${fp(pkg.daily_percent)}</span></div>
      <div class="sr"><span class="sl">${ic('clock')} Muddat</span><span class="sv">${pkg.duration_days} kun</span></div>
    </div>`;
  document.getElementById('m-inv-calc').innerHTML = '';
  const amtEl = document.getElementById('m-inv-amount');
  amtEl.value = '';
  amtEl.placeholder = `${fm(pkg.min_amount)} – ${fm(pkg.max_amount)}`;
  amtEl.oninput = () => {
    const v = +amtEl.value || 0;
    if (v < 1) { document.getElementById('m-inv-calc').innerHTML = ''; return; }
    const daily = v * pkg.daily_percent / 100;
    const total = daily * pkg.duration_days;
    document.getElementById('m-inv-calc').innerHTML = `
      <div class="card card-g" style="margin-bottom:0">
        <div class="sr"><span class="sl">${ic('bolt',14,'#22c55e')} Kunlik daromad</span><span class="sv" style="color:var(--g)">${fm(daily)}</span></div>
        <div class="sr"><span class="sl">${ic('trophy')} Jami daromad</span><span class="sv" style="color:var(--c)">${fm(total)}</span></div>
      </div>`;
  };
  openModal('invest');
}

function submitInvest() {
  const pkgId  = +document.getElementById('m-inv-pkg').value;
  const amount = +document.getElementById('m-inv-amount').value;
  if (!pkgId || amount < 1) { toast('Summa kiriting', 'err'); return; }
  apiCall('invest.php', { action: 'create', package_id: pkgId, amount }).then(r => {
    if (r.success) {
      updateBalanceUI(Math.max(0, parseFloat(user.balance||0) - amount));
      closeModal('invest');
      toast(r.message || 'Investitsiya amalga oshdi!', 'ok');
      confetti();
      renderInvest();
    } else { toast(r.message || 'Xatolik', 'err'); }
  });
}

/* ── WALLET PAGE ──────────────────────────────────────────── */
function renderWallet() {
  document.getElementById('p-wallet').innerHTML = `
  <div class="pg-hdr">
    <div class="pg-ttl">${ic('wallet',22,'#00ff88')} Hamyon</div>
  </div>
  <div class="card card-hi" style="text-align:center;padding:24px 16px">
    <div style="font-size:11px;color:var(--t2);margin-bottom:6px">Mavjud balans</div>
    <div style="font-size:38px;font-weight:900;letter-spacing:-1.5px" id="w-bal">${fm(user?.balance||0)}</div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button class="btn btn-g" onclick="openDeposit()">
        ${ic('deposit',18)} Depozit
      </button>
      <button class="btn btn-o" onclick="openWithdraw()">
        ${ic('withdraw',18)} Yechish
      </button>
    </div>
  </div>
  <div class="card">
    <div class="st">${ic('clock')} Depozit tarixi</div>
    <div id="w-dep-history"><div class="spin-w"></div></div>
  </div>
  <div class="card">
    <div class="st">${ic('clock')} Yechish tarixi</div>
    <div id="w-wd-history"><div class="spin-w"></div></div>
  </div>`;

  apiCall('wallet.php', { action: 'deposit_history' }).then(r => {
    const el = document.getElementById('w-dep-history');
    if (!el) return;
    const list = r.deposits || [];
    if (!list.length) { el.innerHTML = `<div class="empty">${ic('box',32)}<div>Tarix yo'q</div></div>`; return; }
    el.innerHTML = list.slice(0,8).map(d => {
      const st = {pending:'⏳',approved:'✅',rejected:'❌'}[d.status]||'';
      return `<div class="fi">
        <div class="fa fa-dep">${ic('deposit',18)}</div>
        <div style="flex:1"><div class="fn">${fm(d.amount)}</div><div style="font-size:11px;color:var(--t3)">${d.created_at||''}</div></div>
        <span style="font-size:12px">${st} ${d.status}</span>
      </div>`;
    }).join('');
  });

  apiCall('wallet.php', { action: 'withdraw_history' }).then(r => {
    const el = document.getElementById('w-wd-history');
    if (!el) return;
    const list = r.withdrawals || [];
    if (!list.length) { el.innerHTML = `<div class="empty">${ic('box',32)}<div>Tarix yo'q</div></div>`; return; }
    el.innerHTML = list.slice(0,8).map(d => {
      const st = {pending:'⏳',approved:'✅',rejected:'❌'}[d.status]||'';
      return `<div class="fi">
        <div class="fa fa-wd">${ic('withdraw',18)}</div>
        <div style="flex:1"><div class="fn">${fm(d.amount)}</div><div style="font-size:11px;color:var(--t3)">${d.created_at||''}</div></div>
        <span style="font-size:12px">${st} ${d.status}</span>
      </div>`;
    }).join('');
  });
}

/* ── DEPOSIT / WITHDRAW MODALS ────────────────────────────── */
function openDeposit() {
  apiCall('wallet.php', { action: 'deposit_info' }).then(r => {
    const el = document.getElementById('m-dep-info');
    if (el && r.success) {
      el.innerHTML = `
        <div class="card" style="margin-bottom:0">
          <div class="sr"><span class="sl">${ic('shield')} UzCard</span><span class="sv" style="font-family:monospace;font-size:13px">${r.card_uzcard}</span></div>
          <div class="sr"><span class="sl">${ic('shield')} Humo</span><span class="sv" style="font-family:monospace;font-size:13px">${r.card_humo}</span></div>
          <div class="sr"><span class="sl">${ic('user')} Egasi</span><span class="sv">${r.card_holder}</span></div>
          <div class="sr"><span class="sl">${ic('coins')} Min/Max</span><span class="sv">${fm(r.min_amount)} / ${fm(r.max_amount)}</span></div>
        </div>
        <div style="font-size:12px;color:var(--t2);margin:10px 0 2px">Yuqoridagi kartaga pul o'tkazing, so'ng tranzaksiya ID kiriting.</div>`;
    }
  });
  openModal('deposit');
}

function submitDeposit() {
  const amount  = +document.getElementById('d-amount').value;
  const receipt = document.getElementById('d-receipt').value.trim();
  const ctype   = document.getElementById('d-ctype').value;
  if (!amount || !receipt) { toast("Summa va chekni kiriting", 'err'); return; }
  apiCall('wallet.php', { action: 'deposit_create', amount, receipt_info: receipt, card_type: ctype }).then(r => {
    if (r.success) {
      closeModal('deposit');
      toast(r.message || "So'rov yuborildi!", 'ok');
      document.getElementById('d-amount').value = '';
      document.getElementById('d-receipt').value = '';
    } else { toast(r.message || 'Xatolik', 'err'); }
  });
}

function openWithdraw() {
  apiCall('wallet.php', { action: 'balance' }).then(r => {
    const el = document.getElementById('m-wd-info');
    if (el && r.success) {
      const av = Math.max(0, (r.user?.total_earned||0) - (r.user?.total_withdraw||0));
      el.innerHTML = `
        <div class="card card-y" style="margin-bottom:0">
          <div class="sr"><span class="sl">${ic('wallet')} Balans</span><span class="sv">${fm(r.user?.balance||0)}</span></div>
          <div class="sr"><span class="sl">${ic('withdraw')} Yechish mumkin</span><span class="sv" style="color:var(--g)">${fm(av)}</span></div>
        </div>`;
    }
  });
  openModal('withdraw');
}

function submitWithdraw() {
  const amount = +document.getElementById('w-amount').value;
  const card   = document.getElementById('w-card').value.replace(/\s/g,'');
  const ctype  = document.getElementById('w-ctype').value;
  if (!amount || card.length < 16) { toast("Ma'lumotlarni to'ldiring", 'err'); return; }
  apiCall('wallet.php', { action: 'withdraw_create', amount, card_number: card, card_type: ctype }).then(r => {
    if (r.success) {
      // Balans serverda kamaygani uchun UI ni yangilaymiz
      updateBalanceUI(Math.max(0, parseFloat(user.balance||0) - amount));
      closeModal('withdraw');
      toast(r.message || "So'rov yuborildi!", 'ok');
      document.getElementById('w-amount').value = '';
      document.getElementById('w-card').value = '';
      // Wallet sahifasidagi balansni ham yangilaymiz
      const wBal = document.getElementById('w-bal');
      if (wBal) wBal.textContent = fm(Math.max(0, parseFloat(user.balance||0)));
    } else { toast(r.message || 'Xatolik', 'err'); }
  });
}

// Card number formatter
document.addEventListener('input', e => {
  if (e.target.id === 'w-card') {
    let v = e.target.value.replace(/\D/g,'').slice(0,16);
    e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
  }
});

/* ── REFERRAL PAGE ────────────────────────────────────────── */
function renderRef() {
  document.getElementById('p-ref').innerHTML = `
  <div class="pg-hdr">
    <div class="pg-ttl">${ic('users',22,'#00ff88')} Referal</div>
  </div>
  <div id="ref-stats"><div class="spin-w"></div></div>`;

  apiCall('referral.php', { action: 'stats' }).then(r => {
    const el = document.getElementById('ref-stats');
    if (!r.success || !el) return;
    const s = r;  // getStats returns fields directly at root
    const link = r.referral_link || '';
    el.innerHTML = `
      <div class="card card-hi">
        <div class="st">${ic('gift')} Referal dasturi</div>
        <div style="font-size:12px;color:var(--t2);margin-bottom:14px;line-height:1.6">
          Do'stlaringizni taklif qiling va ularning har bir investitsiyasidan daromad oling:<br>
          <strong style="color:var(--g)">1-daraja: 10%</strong> &nbsp;•&nbsp;
          <strong style="color:var(--c)">2-daraja: 5%</strong> &nbsp;•&nbsp;
          <strong style="color:#00d4ff">3-daraja: 2%</strong>
        </div>
        <div class="ref-row">
          <div class="ref-val" id="ref-link-val">${link}</div>
          <button class="btn btn-p btn-sm" onclick="copyRef()">
            ${ic('copy',14)} Nusxa
          </button>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="btn btn-o" style="flex:1" onclick="shareRef('${link}')">
            ${ic('link',16)} Ulashish
          </button>
          <button class="btn btn-p" style="flex:1" onclick="forwardRef('${link}')">
            ${ic('users',16)} Do'stga yuborish
          </button>
        </div>
      </div>
      <div class="card">
        <div class="st">${ic('star')} Statistika</div>
        <div class="sr"><span class="sl">${ic('users')} Jami referallar</span><span class="sv">${s.total_referrals||0}</span></div>
        <div class="sr"><span class="sl">${ic('coins')} Referal daromad</span><span class="sv" style="color:var(--g)">${fm(s.total_earnings||0)}</span></div>
        <div class="sr"><span class="sl">${ic('users')} 1-daraja</span><span class="sv">${s.level_1||0} kishi</span></div>
        <div class="sr"><span class="sl">${ic('users')} 2-daraja</span><span class="sv">${s.level_2||0} kishi</span></div>
        <div class="sr"><span class="sl">${ic('users')} 3-daraja</span><span class="sv">${s.level_3||0} kishi</span></div>
      </div>
      <div class="card">
        <div class="st">${ic('clock')} Oxirgi daromadlar</div>
        <div id="ref-history"><div class="spin-w"></div></div>
      </div>`;

    apiCall('referral.php', { action: 'history' }).then(rh => {
      const hel = document.getElementById('ref-history');
      if (!hel) return;
      const list = rh.history || [];
      if (!list.length) { hel.innerHTML = `<div class="empty">${ic('coins',32)}<div>Hali daromad yo'q</div></div>`; return; }
      hel.innerHTML = list.slice(0,10).map(h => `
        <div class="fi">
          <div class="fa fa-dep">${ic('gift',18)}</div>
          <div style="flex:1">
            <div class="fn">${h.first_name||'Foydalanuvchi'}</div>
            <div style="font-size:11px;color:var(--t3)">${h.created_at||''} • ${h.level||1}-daraja</div>
          </div>
          <span style="color:var(--g);font-weight:700;font-size:13px">+${fm(h.amount)}</span>
        </div>`).join('');
    });
  });
}

function copyRef() {
  const v = document.getElementById('ref-link-val')?.textContent || '';
  navigator.clipboard?.writeText(v).then(() => toast('Havola nusxalandi!', 'ok'));
}

function shareRef(link) {
  const text = `TORTINMANG — Ishonchli investitsiya platformasi!\n${link}`;
  if (tg) tg.openTelegramLink(`https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent('TORTINMANG orqali daromad oling!')}`);
  else navigator.clipboard?.writeText(text).then(() => toast('Nusxalandi!', 'ok'));
}

function forwardRef(link) {
  // Telegram da to'g'ridan do'stga yuborish
  const msg = `💎 TORTINMANG — O'zbekistonning ishonchli investitsiya platformasi!\n\n` +
              `✅ Kunlik daromad\n✅ Referal bonuslari\n✅ VIP imtiyozlar\n\n` +
              `Ro'yxatdan o'tish: ${link}`;
  if (tg) {
    tg.openTelegramLink(`https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(msg)}`);
  } else {
    navigator.clipboard?.writeText(msg).then(() => toast('Xabar nusxalandi!', 'ok'));
  }
}

/* ── PROFILE PAGE ─────────────────────────────────────────── */
function renderProfile() {
  if (!user) return;
  const nextLvl = +user.level + 1;
  const pct     = Math.min(100, Math.round(((+user.total_earned||0) / (+user.next_level_threshold||1)) * 100));
  const vt = user.vip ? { silver:'🥈 Silver VIP', gold:'🥇 Gold VIP', diamond:'💎 Diamond VIP' }[user.vip.tier] : null;
  document.getElementById('p-profile').innerHTML = `
  <div class="pg-hdr">
    <div class="pg-ttl">${ic('user',22,'#00ff88')} Profil</div>
  </div>
  <div class="card card-hi" style="text-align:center;padding:28px 16px">
    <div class="avatar-svg">
      <svg viewBox="0 0 80 80" width="80" height="80">
        <defs>
          <linearGradient id="avGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#00ff88"/>
            <stop offset="100%" stop-color="#00d4ff"/>
          </linearGradient>
        </defs>
        <circle cx="40" cy="40" r="40" fill="url(#avGrad)" opacity=".2"/>
        <circle cx="40" cy="32" r="16" fill="url(#avGrad)"/>
        <ellipse cx="40" cy="68" rx="24" ry="14" fill="url(#avGrad)"/>
      </svg>
    </div>
    <div style="font-size:20px;font-weight:800;margin:10px 0 4px">${user.first_name||''} ${user.last_name||''}</div>
    <div style="font-size:13px;color:var(--t2)">@${user.username||'—'}</div>
    <div style="display:flex;justify-content:center;gap:8px;margin-top:12px;flex-wrap:wrap">
      <span class="chip chip-purple">${ic('shield',12)} ${user.badge_label||user.badge}</span>
      <span class="chip chip-cyan">${ic('star',12)} ${user.level_name||'Level '+user.level}</span>
      ${vt ? `<span class="chip chip-yellow">${vt}</span>` : ''}
    </div>
  </div>
  <div class="card">
    <div class="st">${ic('trend')} Moliyaviy</div>
    <div class="sr"><span class="sl">${ic('wallet')} Balans</span><span class="sv">${fm(user.balance)}</span></div>
    <div class="sr"><span class="sl">${ic('coins')} Jami daromad</span><span class="sv" style="color:var(--g)">${fm(user.total_earned)}</span></div>
    <div class="sr"><span class="sl">${ic('deposit')} Jami depozit</span><span class="sv">${fm(user.total_deposit)}</span></div>
    <div class="sr"><span class="sl">${ic('withdraw')} Jami yechilgan</span><span class="sv">${fm(user.total_withdraw)}</span></div>
    <div class="sr"><span class="sl">${ic('gift')} Referal daromad</span><span class="sv" style="color:var(--c)">${fm(user.referral_earnings)}</span></div>
  </div>
  <div class="card">
    <div class="st">${ic('star')} Daraja</div>
    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
      <span style="font-size:13px;font-weight:600">${user.level_name||'Level '+user.level}</span>
      <span style="font-size:12px;color:var(--t2)">${pct}%</span>
    </div>
    <div class="lvl-bar"><div class="lvl-fill" style="width:${pct}%"></div></div>
    ${nextLvl <= 10 ? `<div style="font-size:11px;color:var(--t3);margin-top:6px">${fm(user.next_level_threshold)} gacha</div>` : ''}
    <div class="sr" style="margin-top:8px"><span class="sl">${ic('fire')} Streak</span><span class="sv" style="color:var(--y)">${user.daily_bonus_streak||0} kun 🔥</span></div>
  </div>`;
}

/* ── TASKS MODAL ──────────────────────────────────────────── */
function openTasks() {
  openModal('tasks');
  apiCall('tasks.php', { action: 'list' }).then(r => {
    const el = document.getElementById('m-tasks-list');
    if (!el || !r.success) return;
    const tasks = r.tasks || [];
    if (!tasks.length) { el.innerHTML = `<div class="empty">${ic('check',36)}<div>Vazifalar yo'q</div></div>`; return; }
    el.innerHTML = tasks.map(t => {
      const done = t.is_completed;
      return `<div class="task-item ${done ? 'task-done' : ''}" style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;margin-bottom:8px;background:${done?'rgba(0,255,136,.07)':'rgba(0,255,136,.02)'};border:1px solid ${done?'rgba(0,255,136,.2)':'rgba(0,255,136,.07)'}">
        <div style="width:42px;height:42px;border-radius:12px;background:${done?'rgba(0,255,136,.2)':'rgba(0,255,136,.12)'};display:flex;align-items:center;justify-content:center;flex-shrink:0">
          ${ic(done?'ok':'bolt',20,done?'#00ff88':'#00d4a0')}
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:13px">${t.title}</div>
          <div style="font-size:11px;color:var(--t2);margin-top:2px">+${fm(t.reward)}</div>
        </div>
        ${!done ? `<button class="btn btn-p btn-sm" onclick="completeTask(${t.id},'${t.action_type}','${t.action_url}')" id="tb-${t.id}">
          ${ic('bolt',14)} Bajar
        </button>` : `<span style="font-size:20px">✅</span>`}
      </div>`;
    }).join('');
  });
}

function completeTask(id, type, url) {
  const btn = document.getElementById('tb-' + id);

  // click_time — foydalanuvchi havolani qachon ochganini saqlash
  if (!taskClicks[id]) taskClicks[id] = { count: 0, clickTime: 0 };

  // Havola ochish kerak bo'lgan tiplar
  const needsLink = url && ['subscribe', 'share', 'visit', 'url', 'channel', 'group'].includes(type);

  // 1-bosish: havolani ochib, "Tasdiqlash" holatiga o'tkazish
  if (needsLink && taskClicks[id].count === 0) {
    taskClicks[id].clickTime = Math.floor(Date.now() / 1000);
    taskClicks[id].count = 1;

    if (tg) tg.openLink(url);
    else window.open(url, '_blank');

    // Tugmani "Tasdiqlash" ga o'zgartir
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = ic('ok', 14) + ' Tasdiqlash';
      btn.style.background = 'linear-gradient(135deg,#00d4a0,#00ff88)';
    }

    if (type === 'subscribe') {
      toast("Kanalga obuna bo'ling va Tasdiqlash tugmasini bosing", 'ok');
    } else {
      toast("Havolani ko'rib, Tasdiqlash tugmasini bosing", 'ok');
    }
    return;
  }

  // 2-bosish: serverga yuborish
  if (btn) { btn.disabled = true; btn.innerHTML = ic('clock', 14) + ' ...'; }

  const clickTime = taskClicks[id]?.clickTime || 0;

  apiCall('tasks.php', { action: 'complete', task_id: id, click_time: clickTime }).then(r => {
    if (r.success) {
      user.balance = (parseFloat(user.balance || 0) + (r.reward || 0)).toString();
      toast(r.message || 'Vazifa bajarildi!', 'ok');
      delete taskClicks[id];
      openTasks();
    } else {
      toast(r.message || 'Xatolik', 'err');
      // Xato bo'lsa qayta urinish imkoniyati — click_time ni reset qilamiz
      if (r.message && (r.message.includes('oching') || r.message.includes('Muddati'))) {
        delete taskClicks[id];
      }
      if (btn) {
        btn.disabled = false;
        // Agar havola ochilgan bo'lsa "Tasdiqlash" ko'rinishida qolsin
        if (needsLink && taskClicks[id]?.count > 0) {
          btn.innerHTML = ic('ok', 14) + ' Tasdiqlash';
        } else {
          btn.innerHTML = ic('bolt', 14) + ' Bajar';
          btn.style.background = '';
        }
      }
    }
  });
}

/* ── LOTTERY MODAL ────────────────────────────────────────── */
function openLottery() {
  openModal('lottery');
  apiCall('lottery.php', { action: 'status' }).then(r => {
    const el = document.getElementById('m-lottery-cnt');
    if (!el || !r.success) return;
    const prizes = r.prizes || [];
    const spinsLeft = r.spins_left ?? 1;
    const lastWin = r.recent_wins?.[0];
    el.innerHTML = `
      <div style="text-align:center;padding:10px 0 20px">
        <div class="wheel-wrap">
          <div class="wheel" id="lottery-wheel">
            <svg viewBox="0 0 200 200" width="220" height="220">
              <defs>
                ${['#00ff88','#00d4ff','#00cc66','#f0c040','#00ffcc','#34d399','#6ee7b7','#00aacc'].map((c,i)=>`<radialGradient id="wg${i}" cx="50%" cy="50%" r="50%"><stop offset="60%" stop-color="${c}" stop-opacity=".7"/><stop offset="100%" stop-color="${c}" stop-opacity=".3"/></radialGradient>`).join('')}
              </defs>
              ${prizes.slice(0,8).map((p,i,arr)=>{
                const seg = (2 * Math.PI) / arr.length;
                const a1 = seg * i - Math.PI / 2;
                const a2 = a1 + seg;
                const r = 90, cx = 100, cy = 100;
                const x1 = cx + r * Math.cos(a1), y1 = cy + r * Math.sin(a1);
                const x2 = cx + r * Math.cos(a2), y2 = cy + r * Math.sin(a2);
                const mx = cx + (r*.65) * Math.cos(a1 + seg/2), my = cy + (r*.65) * Math.sin(a1 + seg/2);
                const colors = ['#00ff88','#00d4ff','#00cc66','#f0c040','#00ffcc','#34d399','#6ee7b7','#00aacc'];
                return `<path d="M${cx},${cy} L${x1},${y1} A${r},${r} 0 0,1 ${x2},${y2} Z" fill="${colors[i%colors.length]}" opacity=".85"/>
                  <text x="${mx}" y="${my}" text-anchor="middle" dominant-baseline="central" fill="white" font-size="9" font-weight="700" transform="rotate(${(180/Math.PI)*(a1+seg/2)},${mx},${my})">${p.label?.slice(0,8)||p.name?.slice(0,8)||''}</text>`;
              }).join('')}
              <circle cx="100" cy="100" r="14" fill="#0d1020" stroke="rgba(255,255,255,.15)" stroke-width="2"/>
              <polygon points="100,6 104,20 96,20" fill="#f59e0b"/>
            </svg>
          </div>
        </div>
        ${lastWin ? `<div class="chip chip-yellow" style="margin:8px auto;display:inline-flex">🎉 Oxirgi: ${lastWin.first_name} — ${fm(lastWin.result_amount)}</div>` : ''}
        <div style="font-size:13px;color:var(--t2);margin:12px 0 16px">
          Bugungi urinishlar: <strong style="color:var(--t1)">${spinsLeft}</strong> ta qoldi
        </div>
        <button class="btn btn-p" onclick="spinWheel()" ${spinsLeft < 1 ? 'disabled style="opacity:.5"' : ''} id="spin-btn">
          ${ic('spin',18)} Aylantirish
        </button>
      </div>`;
  });
}

function spinWheel() {
  const btn = document.getElementById('spin-btn');
  const wheel = document.getElementById('lottery-wheel');
  if (btn) btn.disabled = true;
  const spins = 5 + Math.floor(Math.random() * 4);
  const deg   = spins * 360 + Math.floor(Math.random() * 360);
  if (wheel) {
    wheel.style.transition = 'transform 3s cubic-bezier(.17,.67,.12,1)';
    wheel.style.transform  = `rotate(${deg}deg)`;
  }
  apiCall('lottery.php', { action: 'spin' }).then(r => {
    setTimeout(() => {
      if (r.success) {
        const reward = r.prize_amount || 0;
        updateBalanceUI(parseFloat(user.balance||0) + reward);
        toast(r.message || `🎉 ${r.prize_name}!`, 'ok');
        if (reward > 0) confetti();
      } else toast(r.message || 'Xatolik', 'err');
      openLottery();
    }, 3200);
  });
}

/* ── VIP MODAL ────────────────────────────────────────────── */
function openVip() {
  openModal('vip');
  apiCall('vip.php', { action: 'tiers' }).then(r => {
    const el = document.getElementById('m-vip-cnt');
    if (!el || !r.success) return;
    const cur = r.current_vip;
    const tiers = r.tiers || [];
    el.innerHTML = tiers.map(t => {
      const cls = {silver:'vip-silver',gold:'vip-gold',diamond:'vip-diamond'}[t.id]||'';
      const glyph = {silver:'🥈',gold:'🥇',diamond:'💎'}[t.id]||'⭐';
      const active = cur && cur.tier === t.id;
      return `<div class="vip-card ${cls} ${active?'vip-active':''}">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <div style="font-size:16px;font-weight:800">${glyph} ${t.name}</div>
          ${active ? `<span class="chip chip-green">${ic('ok',12)} Aktiv</span>` : ''}
        </div>
        <div class="sr"><span class="sl">${ic('coins')} Narx</span><span class="sv">${fm(t.price)}</span></div>
        <div class="sr"><span class="sl">${ic('clock')} Muddat</span><span class="sv">${t.duration_days} kun</span></div>
        <div style="margin:10px 0 12px">
          ${(t.benefits||[]).map(b=>`<div style="font-size:12px;color:var(--t2);padding:3px 0;display:flex;align-items:center;gap:6px">${ic('ok',12,'#22c55e')} ${b}</div>`).join('')}
        </div>
        ${!active ? `<button class="btn btn-p btn-sm" style="width:100%" onclick="buyVip('${t.id}',${t.price})">
          ${ic('star',14)} Sotib olish
        </button>` : `<div style="text-align:center;font-size:12px;color:var(--t2)">Amal qilish muddati: ${cur.expires_at||''}</div>`}
      </div>`;
    }).join('');
  });
}

function buyVip(tierId, price) {
  if (!confirm(`${tierId.toUpperCase()} VIP ni ${fm(price)} ga sotib olasizmi?`)) return;
  apiCall('vip.php', { action: 'purchase', tier_id: tierId }).then(r => {
    if (r.success) {
      updateBalanceUI(Math.max(0, parseFloat(user.balance||0) - price));
      toast(r.message || 'VIP faollashtirildi!', 'ok');
      confetti();
      openVip();
    } else toast(r.message || 'Xatolik', 'err');
  });
}

/* ── PROMO MODAL ──────────────────────────────────────────── */
function openPromo() { openModal('promo'); }

function redeemPromo() {
  const code = document.getElementById('promo-inp').value.trim().toUpperCase();
  if (!code) { toast('Promo kod kiriting', 'err'); return; }
  apiCall('promo.php', { action: 'redeem', code }).then(r => {
    const el = document.getElementById('promo-res');
    if (r.success) {
      updateBalanceUI(parseFloat(user.balance||0) + (r.reward||0));
      if (el) el.innerHTML = `<div class="card card-g"><div class="st">${ic('ok',16,'#22c55e')} Muvaffaqiyat!</div><div style="font-size:18px;font-weight:800;color:var(--g)">+${fm(r.reward)}</div></div>`;
      document.getElementById('promo-inp').value = '';
      confetti();
      toast(r.message || 'Promo kod faollashtirildi!', 'ok');
    } else {
      if (el) el.innerHTML = `<div class="card" style="border-color:rgba(239,68,68,.25)"><div style="color:var(--r);font-size:13px">${r.message||'Kod noto\'g\'ri'}</div></div>`;
      toast(r.message || 'Xatolik', 'err');
    }
  });
}

/* ── ACHIEVEMENTS MODAL ───────────────────────────────────── */
function openAchievements() {
  openModal('achievements');
  apiCall('achievements.php', { action: 'list' }).then(r => {
    const el = document.getElementById('m-ach-cnt');
    if (!el || !r.success) return;
    const list = r.achievements || [];
    if (!list.length) { el.innerHTML = `<div class="empty">${ic('trophy',36)}<div>Yutuqlar yo'q</div></div>`; return; }
    el.innerHTML = list.map(a => {
      const unlocked = a.is_unlocked || a.can_claim;
      const claimed  = a.is_claimed;
      const pct = Math.min(100, Math.round(((+a.progress||0) / (+a.requirement_value||1)) * 100));
      return `<div class="card ${unlocked&&!claimed?'card-y':''}" style="margin-bottom:8px">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="font-size:32px;opacity:${unlocked?1:.35}">${a.icon||'🏆'}</div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:13px;${unlocked?'':'color:var(--t2)'}">${a.name}</div>
            <div style="font-size:11px;color:var(--t3);margin-top:2px">${a.description}</div>
            ${!claimed ? `<div class="lvl-bar" style="margin-top:6px"><div class="lvl-fill" style="width:${pct}%;background:${unlocked?'linear-gradient(90deg,var(--g),var(--c))':'linear-gradient(90deg,var(--t3),var(--t2))'}"></div></div>
            <div style="font-size:10px;color:var(--t3);margin-top:3px">${a.progress||0} / ${a.requirement_value}</div>` : ''}
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:12px;color:var(--g);font-weight:700">+${fm(a.reward)}</div>
            ${unlocked && !claimed ? `<button class="btn btn-g btn-sm" style="margin-top:6px" onclick="claimAch('${a.key||a.achievement_key}')">Olish</button>` : ''}
            ${claimed ? `<span style="font-size:18px">✅</span>` : ''}
          </div>
        </div>
      </div>`;
    }).join('');
  });
}

function claimAch(key) {
  apiCall('achievements.php', { action: 'claim', achievement_key: key }).then(r => {
    if (r.success) {
      updateBalanceUI(parseFloat(user.balance||0) + (r.reward||0));
      toast(r.message || 'Yutuq olindi!', 'ok');
      confetti();
      openAchievements();
    } else toast(r.message || 'Xatolik', 'err');
  });
}

/* ── LEADERBOARD MODAL ────────────────────────────────────── */
function openLeaderboard() {
  openModal('leaderboard');
  apiCall('leaderboard.php', { action: 'top_earners' }).then(r => {
    const el = document.getElementById('m-lb-cnt');
    if (!el || !r.success) return;
    const top = r.leaders || [];
    const medals = ['🥇','🥈','🥉'];
    el.innerHTML = `
      <div class="st">${ic('trophy')} Top daromadchilar</div>
      ${top.slice(0,10).map((u,i) => `
        <div class="fi">
          <div style="width:36px;text-align:center;font-size:${i<3?'22':'14'}px;flex-shrink:0">${medals[i]||`#${i+1}`}</div>
          <div style="flex:1">
            <div class="fn">${u.first_name||u.display_name||'Foydalanuvchi'}</div>
            <div style="font-size:11px;color:var(--t3)">${u.level_name||'Level '+u.level}</div>
          </div>
          <span style="color:var(--g);font-weight:700;font-size:13px">${fm(u.total_earned)}</span>
        </div>`).join('')}
      <div class="div"></div>
      <div class="st">${ic('users')} Top referalchilar</div>
      <div id="lb-ref-list"><div class="spin-w"></div></div>`;

    apiCall('leaderboard.php', { action: 'top_referrals' }).then(rr => {
      const rel = document.getElementById('lb-ref-list');
      if (!rel || !rr.success) return;
      const rtop = rr.leaders || [];
      rel.innerHTML = rtop.slice(0,10).map((u,i) => `
        <div class="fi">
          <div style="width:36px;text-align:center;font-size:${i<3?'22':'14'}px;flex-shrink:0">${medals[i]||`#${i+1}`}</div>
          <div style="flex:1">
            <div class="fn">${u.first_name||u.display_name||'Foydalanuvchi'}</div>
            <div style="font-size:11px;color:var(--t3)">${u.referral_count||0} ta referal</div>
          </div>
          <span style="color:var(--c);font-weight:700;font-size:13px">${(+u.referral_count||0)} kishi</span>
        </div>`).join('') || `<div class="empty">${ic('users',32)}<div>Ma'lumot yo'q</div></div>`;
    });
  });
}

/* ── NEWS MODAL ───────────────────────────────────────────── */
function openNews() {
  openModal('news');
  apiCall('notify.php', {}).then(r => {
    const el = document.getElementById('m-news-list');
    if (!el || !r.success) return;
    const list = r.news || [];
    if (!list.length) { el.innerHTML = `<div class="empty">${ic('news',36)}<div>Yangiliklar yo'q</div></div>`; return; }
    el.innerHTML = list.map(n => `
      <div class="card" style="margin-bottom:8px">
        <div style="font-size:22px;margin-bottom:8px">${n.emoji||'📰'}</div>
        <div style="font-weight:700;font-size:14px;margin-bottom:6px">${n.title}</div>
        <div style="font-size:13px;color:var(--t2);line-height:1.6">${n.content}</div>
        <div style="font-size:11px;color:var(--t3);margin-top:8px">${n.created_at||''}</div>
      </div>`).join('');
  });
}
