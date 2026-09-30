-- ============================================================
-- TORTINMANG — Update v4
-- Mavjud bazaga apply qiling (yangi baza uchun shart emas)
-- ============================================================

-- Karta settings qo'shish (mavjud bo'lmasa)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('deposit_card_uzcard',  '8600 XXXX XXXX XXXX'),
('deposit_card_humo',    '9860 XXXX XXXX XXXX'),
('deposit_card_holder',  'Tortinmang Admin');

-- Min deposit 10,000 ga tushirildi (paketlar ham yangilandi)
UPDATE packages SET min_amount = 10000  WHERE id = 1 AND min_amount = 50000;
UPDATE packages SET min_amount = 50000  WHERE id = 2 AND min_amount = 100000;
UPDATE packages SET min_amount = 100000 WHERE id = 3 AND min_amount = 300000;
UPDATE packages SET min_amount = 200000 WHERE id = 4 AND min_amount = 500000;
UPDATE packages SET min_amount = 500000 WHERE id = 5 AND min_amount = 1000000;
UPDATE packages SET min_amount = 1000000 WHERE id = 6 AND min_amount = 3000000;

-- phone, phone_verified, device_registered_at kolonkalari (mavjud bazalarda yo'q bo'lsa)
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone               VARCHAR(20)     DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified      TINYINT(1)      NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS device_registered_at DATETIME       DEFAULT NULL;

-- Yetishmayotgan settings kalitlari (mavjud bazalar uchun)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('require_deposit_for_withdraw',      '1'),
('min_deposit_before_withdraw',       '10000'),
('max_bonus_spins_per_day',           '3'),
('max_daily_bonus_streak_multiplier', '3.0');
