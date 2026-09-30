-- ============================================================
-- TORTINMANG — Database Schema v1.0
-- PHP 8.4 + MySQL 8.0
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+05:00';

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telegram_id         BIGINT UNSIGNED NOT NULL UNIQUE,
    first_name          VARCHAR(100)    NOT NULL DEFAULT '',
    last_name           VARCHAR(100)    NOT NULL DEFAULT '',
    username            VARCHAR(100)    NOT NULL DEFAULT '',
    balance             DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_earned        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_deposit       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_withdraw      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    referral_earnings   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    referrer_id         BIGINT UNSIGNED          DEFAULT NULL,
    referral_code       VARCHAR(20)     NOT NULL DEFAULT '' UNIQUE,
    level               TINYINT UNSIGNED NOT NULL DEFAULT 1,
    badge               ENUM('yangi','investor','lider','kurator') NOT NULL DEFAULT 'yangi',
    daily_bonus_streak  INT UNSIGNED    NOT NULL DEFAULT 0,
    last_bonus_date     DATE                     DEFAULT NULL,
    is_blocked          TINYINT(1)      NOT NULL DEFAULT 0,
    is_trusted          TINYINT(1)      NOT NULL DEFAULT 0,
    phone               VARCHAR(20)              DEFAULT NULL,
    phone_verified      TINYINT(1)      NOT NULL DEFAULT 0,
    device_registered_at DATETIME                DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_referrer  (referrer_id),
    INDEX idx_level     (level),
    INDEX idx_badge     (badge)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PACKAGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS packages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    description     TEXT            NOT NULL,
    min_amount      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    max_amount      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    daily_percent   DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    duration_days   INT UNSIGNED    NOT NULL DEFAULT 30,
    icon            VARCHAR(10)     NOT NULL DEFAULT '📦',
    color           VARCHAR(30)     NOT NULL DEFAULT '#7c3aed',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- INVESTMENTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS investments (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    package_id       INT UNSIGNED    NOT NULL,
    amount           DECIMAL(15,2)   NOT NULL,
    daily_profit     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_earned     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    days_passed      INT UNSIGNED    NOT NULL DEFAULT 0,
    duration_days    INT UNSIGNED    NOT NULL DEFAULT 30,
    status           ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    last_profit_date DATE                     DEFAULT NULL,
    started_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at       DATETIME        NOT NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    INDEX idx_status     (status),
    INDEX idx_user       (user_id),
    INDEX idx_profit_date(last_profit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- DEPOSITS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deposits (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    amount       DECIMAL(15,2)   NOT NULL,
    card_type    ENUM('uzcard','humo') NOT NULL DEFAULT 'uzcard',
    card_number  VARCHAR(20)     NOT NULL DEFAULT '',
    receipt_info VARCHAR(1000)   NOT NULL DEFAULT '',
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note   VARCHAR(500)    NOT NULL DEFAULT '',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status  (status),
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- WITHDRAWALS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS withdrawals (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    amount      DECIMAL(15,2)   NOT NULL,
    card_type   ENUM('uzcard','humo') NOT NULL DEFAULT 'uzcard',
    card_number VARCHAR(20)     NOT NULL DEFAULT '',
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note  VARCHAR(500)    NOT NULL DEFAULT '',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status  (status),
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- REFERRAL EARNINGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS referral_earnings (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    from_user_id BIGINT UNSIGNED NOT NULL,
    level        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    amount       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    source_type  VARCHAR(50)     NOT NULL DEFAULT 'investment',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TASKS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200)   NOT NULL,
    description TEXT           NOT NULL,
    reward      DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
    task_type   ENUM('daily','one_time') NOT NULL DEFAULT 'one_time',
    action_type VARCHAR(50)    NOT NULL DEFAULT 'visit',
    action_url  VARCHAR(500)   NOT NULL DEFAULT '',
    icon        VARCHAR(10)    NOT NULL DEFAULT '✅',
    is_active   TINYINT(1)     NOT NULL DEFAULT 1,
    sort_order  INT            NOT NULL DEFAULT 0,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_completions (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    task_id      INT UNSIGNED    NOT NULL,
    completed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id)  ON DELETE CASCADE,
    INDEX idx_user_task (user_id, task_id),
    INDEX idx_date      (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- VIP MEMBERSHIPS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vip_memberships (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    tier       ENUM('silver','gold','diamond') NOT NULL,
    started_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME    NOT NULL,
    is_active  TINYINT(1)  NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user   (user_id),
    INDEX idx_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LOTTERY
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lottery_prizes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    prize_type ENUM('balance','spin','nothing') NOT NULL DEFAULT 'balance',
    amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    weight     INT UNSIGNED  NOT NULL DEFAULT 1,
    is_active  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lottery_spins (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    prize_id      INT UNSIGNED    DEFAULT NULL,
    result_amount DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    spun_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (prize_id) REFERENCES lottery_prizes(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, spun_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PROMO CODES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS promo_codes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(50)   NOT NULL UNIQUE,
    reward_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    max_uses      INT UNSIGNED  NOT NULL DEFAULT 100,
    used_count    INT UNSIGNED  NOT NULL DEFAULT 0,
    expires_at    DATETIME      DEFAULT NULL,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_usages (
    id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id   BIGINT UNSIGNED NOT NULL,
    promo_id  INT UNSIGNED    NOT NULL,
    amount    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    used_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_promo (user_id, promo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ACHIEVEMENTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS achievements (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    achievement_key    VARCHAR(50)  NOT NULL UNIQUE,
    name               VARCHAR(100) NOT NULL,
    description        VARCHAR(300) NOT NULL DEFAULT '',
    icon               VARCHAR(10)  NOT NULL DEFAULT '🏆',
    requirement_type   ENUM('deposits_count','referrals_count','streak_days','total_earned','investments_count') NOT NULL,
    requirement_value  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    reward             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active          TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order         INT           NOT NULL DEFAULT 0,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_achievements (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    achievement_id INT UNSIGNED    NOT NULL,
    claimed        TINYINT(1)      NOT NULL DEFAULT 0,
    unlocked_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at     DATETIME        DEFAULT NULL,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_ach (user_id, achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- NEWS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS news (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    content    TEXT         NOT NULL,
    emoji      VARCHAR(10)  NOT NULL DEFAULT '📰',
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SETTINGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT         NOT NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ADMIN USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(100) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    last_login     DATETIME     DEFAULT NULL,
    login_attempts INT          NOT NULL DEFAULT 0,
    locked_until   DATETIME     DEFAULT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PAYMENT FEED
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_feed (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_display_name VARCHAR(100)  NOT NULL DEFAULT '',
    amount            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    feed_type         ENUM('deposit','withdraw') NOT NULL DEFAULT 'withdraw',
    is_fake           TINYINT(1)    NOT NULL DEFAULT 0,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT IGNORE INTO packages (name, description, min_amount, max_amount, daily_percent, duration_days, icon, color, sort_order) VALUES
('Boshlang\'ich',  'Yangi investorlar uchun ideal. Xavfsiz va barqaror daromad.',       50000,    1000000,  1.50, 30, '🌱', '#10b981', 1),
('Standart',       'Eng mashhur paket. Muvozanatli foyda va muddat.',                   100000,   3000000,  2.00, 30, '⭐', '#3b82f6', 2),
('Premium',        'Yuqori daromad istovchilar uchun. Keng investitsiya diapazoni.',    300000,   7000000,  2.50, 25, '💎', '#8b5cf6', 3),
('Pro',            'Professional investorlar tanlovi. Jadal o\'sish.',                  500000,   15000000, 3.00, 20, '🚀', '#f59e0b', 4),
('Elite',          'Katta investitsiyalar uchun. Premium daromad kafolati.',            1000000,  30000000, 3.50, 15, '👑', '#ef4444', 5),
('Platinum',       'Eng yuqori daraja. Maksimal foyda va eksklyuziv imtiyozlar.',       3000000,  50000000, 4.00, 10, '🏆', '#06b6d4', 6);

INSERT IGNORE INTO lottery_prizes (name, prize_type, amount, weight) VALUES
('1 000 so\'m',   'balance',  1000,   30),
('2 500 so\'m',   'balance',  2500,   22),
('5 000 so\'m',   'balance',  5000,   15),
('10 000 so\'m',  'balance',  10000,  10),
('25 000 so\'m',  'balance',  25000,  6),
('50 000 so\'m',  'balance',  50000,  3),
('+1 Spin',       'spin',     1,      9),
('Omadsiz 😅',    'nothing',  0,      5);

INSERT IGNORE INTO achievements (achievement_key, name, description, icon, requirement_type, requirement_value, reward, sort_order) VALUES
('first_deposit',    'Birinchi qadam',      'Birinchi depozitingizni kiriting',     '🎯', 'deposits_count',    1,   1000,  1),
('five_deposits',    'Faol investor',       '5 ta tasdiqlangan depozit kiriting',   '💰', 'deposits_count',    5,   5000,  2),
('ten_deposits',     'Katta investor',      '10 ta tasdiqlangan depozit kiriting',  '🏦', 'deposits_count',    10,  15000, 3),
('first_ref',        'Birinchi do\'st',     'Birinchi referalingizni taklif qiling','🤝', 'referrals_count',   1,   2000,  4),
('five_refs',        'Jamoachi',            '5 ta do\'st taklif qiling',            '👥', 'referrals_count',   5,   8000,  5),
('ten_refs',         'Lider',               '10 ta do\'st taklif qiling',           '🌟', 'referrals_count',   10,  20000, 6),
('week_streak',      'Bir haftalik streak', '7 kun ketma-ket kiring',               '🔥', 'streak_days',       7,   5000,  7),
('month_streak',     'Bir oylik streak',    '30 kun ketma-ket kiring',              '💫', 'streak_days',       30,  25000, 8),
('first_million',    'Millioner yo\'li',    '100 000 so\'m daromad oling',          '📈', 'total_earned',      100000,  10000, 9),
('big_earner',       'Katta daromadchi',    '1 000 000 so\'m daromad oling',        '💎', 'total_earned',      1000000, 50000, 10);

INSERT IGNORE INTO tasks (title, description, reward, task_type, action_type, action_url, icon, sort_order) VALUES
('Kanalga obuna',        'Tortinmang rasmiy kanaliga obuna bo\'ling',     500,  'one_time', 'subscribe', 'https://t.me/tortinmang_app', '📢', 1),
('Kunlik kirish',        'Har kuni platformaga kiring',               200,  'daily',    'visit',     '',                            '📅', 2),
('Do\'st taklif qiling', 'Referal havola orqali do\'st taklif qiling',1000, 'one_time', 'referral',  '',                            '👥', 3),
('Birinchi depozit',     'Birinchi marta pul kiriting',               2000, 'one_time', 'deposit',   '',                            '💰', 4),
('Ijtimoiy ulashish',    'Tortinmang haqida ulashing',                    300,  'daily',    'share',     'https://t.me/tortinmang_app', '🔗', 5);

INSERT IGNORE INTO news (title, content, emoji) VALUES
('Tortinmang — yangi bosqich!', 'Tortinmang investitsiya platformasiga xush kelibsiz! Bu yerda siz ishonchli va foydali investitsiya paketlarini topasiz. Kunlik daromad, referal tizimi va ko\'plab sovrinlar sizni kutmoqda!', '🚀'),
('3 bosqichli referal',     'Do\'stlaringizni taklif qiling va 3 bosqichli referal dasturi orqali passiv daromad oling: 1-daraja 10%, 2-daraja 5%, 3-daraja 2%.', '👥'),
('VIP imtiyozlar',          'Silver, Gold va Diamond VIP a\'zoliklarimiz mavjud. VIP bo\'lgan foydalanuvchilar ko\'proq kunlik bonus, qo\'shimcha lottery spins va ustuvor xizmat oladi!', '👑');

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('fake_online_min',       '847'),
('fake_online_max',       '2341'),
('fake_total_users',      '15847'),
('fake_total_paid',       '2450000000'),
('deposit_enabled',       '1'),
('withdrawal_enabled',    '1'),
('maintenance_mode',      '0'),
('deposit_card_uzcard',   '8600 XXXX XXXX XXXX'),
('deposit_card_humo',     '9860 XXXX XXXX XXXX'),
('deposit_card_holder',   'Tortinmang Admin'),
('require_deposit_for_withdraw', '1'),
('min_deposit_before_withdraw',  '10000'),
('max_bonus_spins_per_day',      '3'),
('max_daily_bonus_streak_multiplier', '3.0');

-- Admin: password = tortinmang2024 (CHANGE IN PRODUCTION!)
INSERT IGNORE INTO admin_users (username, password_hash) VALUES
('admin', '$2y$12$ePqGDGCmEKGhH1iJrFCjKeI7YQOB.DEXSy7qIpzF2mV2TZPXiBjDC');
