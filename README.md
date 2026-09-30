# 💎 TORTINMANG

Telegram Mini App asosidagi investitsiya platformasi.

**Texnologiyalar:** PHP 8.0+, MySQL 8.0+, Telegram Bot API, vanilla JavaScript va PWA-admin panel.

## Tuzilma

```text
admin/          Admin panel va uning PWA fayllari
api/v1/         Mini App API endpointlari
bot/            Telegram webhook
core/           Core xizmatlar: DB, Auth, Cache, Validator va boshqalar
models/         User, Investment, Transaction, Referral modellari
miniapp/        Telegram Mini App interfeysi
jobs/            Cron vazifalari
database/        Schema va migratsiyalar
storage/         Runtime cache/loglar (Git'da saqlanmaydi)
config.example.php  Konfiguratsiya namunasi
```

## O‘rnatish

1. `config.example.php`dan nusxa oling:

   ```bash
   cp config.example.php config.php
   ```

2. `config.php` ichida DB, Telegram bot, webhook secret va karta ma’lumotlarini to‘ldiring.

3. MySQL bazaga `database/schema.sql`ni import qiling.

4. Telegram webhookni secret token bilan ulang:

   ```text
   https://api.telegram.org/bot<TOKEN>/setWebhook
   ```

   `url` sifatida `https://SIZNING-DOMENINGIZ/bot/webhook.php`,
   `secret_token` sifatida esa `config.php`dagi `telegram.webhook_secret` qiymatini yuboring.

5. Kunlik profit uchun cron qo‘shing:

   ```cron
   5 0 * * * php /home/user/site/jobs/DailyProfit.php
   ```

## Admin PWA

Admin panel endi mobil qurilmaga ilova sifatida o‘rnatiladi.

- **Android / Chrome:** `https://domeningiz/admin/` sahifasini oching, login qiling va `📲 O‘rnatish` tugmasini bosing.
- **iPhone / iPad:** Safari’da admin panelni oching, `Share` → `Add to Home Screen`ni tanlang.
- PWA uchun HTTPS talab qilinadi.
- Xavfsizlik uchun service worker admin HTML yoki API javoblarini offline cache qilmaydi; faqat install ikonkalari va manifest cache qilinadi.

Mobil admin panelda menyu chap tomondan ochiladigan drawer ko‘rinishida ishlaydi. Desktopda esa mavjud sidebar saqlanadi.

## Muhim runtime fayllar

Quyidagilar Git’ga qo‘shilmaydi:

- `config.php`
- `storage/logs/`
- `storage/cache/`
- `uploads/`

Production uchun `install.php`, `api/v1/debug.php` va `admin/reset_admin.php` ishlatib bo‘lingach web root’dan olib tashlanishi kerak.
