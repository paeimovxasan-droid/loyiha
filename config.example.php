<?php
declare(strict_types=1);

/*
 * Copy this file to config.php and fill in production values.
 * config.php is intentionally ignored by Git.
 */
return [
    'app' => [
        'name'            => 'Tortinmang',
        'url'             => 'https://example.com',
        'miniapp_url'     => 'https://example.com/miniapp/',
        'env'             => 'production',
        'debug'           => false,
        'trusted_proxies' => [],
        'install_secret'  => 'CHANGE_ME_USE_A_LONG_RANDOM_SECRET',
        'debug_secret'    => 'CHANGE_ME_USE_A_LONG_RANDOM_SECRET',
    ],
    'db' => [
        'host'    => 'localhost',
        'name'    => 'tortinmang_db',
        'user'    => 'tortinmang_user',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'telegram' => [
        'bot_token'      => 'CHANGE_ME',
        'bot_username'   => 'your_bot_username',
        'webhook_secret' => 'CHANGE_ME_USE_A_LONG_RANDOM_SECRET',
        'channel_id'     => '@your_channel',
        'channel_url'    => 'https://t.me/your_channel',
    ],
    'finance' => [
        'min_deposit'          => 25000,
        'max_deposit'          => 50000000,
        'min_withdraw'         => 25000,
        'max_withdraw'         => 5000000,
        'daily_withdraw_limit' => 10000000,
        'ref_level_1'          => 10,
        'ref_level_2'          => 5,
        'ref_level_3'          => 2,
        'daily_bonus_base'     => 1000,
        'platform_commission'  => 5,
        'deposit_card_uzcard'  => '8600 XXXX XXXX XXXX',
        'deposit_card_humo'    => '9860 XXXX XXXX XXXX',
        'deposit_card_holder'  => 'Karta egasi',
    ],
    'levels' => [
        1 => 0, 2 => 100000, 3 => 500000, 4 => 1000000, 5 => 5000000,
        6 => 10000000, 7 => 50000000, 8 => 100000000, 9 => 500000000, 10 => 1000000000,
    ],
    'level_names' => [
        1 => 'Yangi boshlovchi', 2 => 'Kichik investor', 3 => "O'rta investor",
        4 => 'Tajribali investor', 5 => 'Senior investor', 6 => 'Pro investor',
        7 => 'Master investor', 8 => 'Grand Master', 9 => 'Legend', 10 => 'Tortinmang Elite',
    ],
    'vip_tiers' => [
        'silver' => [
            'id' => 'silver', 'name' => 'Silver VIP', 'price' => 100000, 'duration_days' => 30,
            'daily_bonus_multiplier' => 2, 'lottery_spins' => 2, 'ref_bonus_extra' => 2,
            'benefits' => ['Kunlik bonus x2', 'Lotereya 2 marta/kun', 'Referal bonus +2%'],
        ],
        'gold' => [
            'id' => 'gold', 'name' => 'Gold VIP', 'price' => 500000, 'duration_days' => 30,
            'daily_bonus_multiplier' => 3, 'lottery_spins' => 3, 'ref_bonus_extra' => 5,
            'benefits' => ['Kunlik bonus x3', 'Lotereya 3 marta/kun', 'Referal bonus +5%'],
        ],
        'diamond' => [
            'id' => 'diamond', 'name' => 'Diamond VIP', 'price' => 2000000, 'duration_days' => 30,
            'daily_bonus_multiplier' => 5, 'lottery_spins' => 5, 'ref_bonus_extra' => 10,
            'benefits' => ['Kunlik bonus x5', 'Lotereya 5 marta/kun', 'Referal bonus +10%'],
        ],
    ],
    'rate_limits' => [
        'auth' => ['attempts' => 30, 'window' => 60],
        'deposit' => ['attempts' => 5, 'window' => 3600],
        'withdraw' => ['attempts' => 5, 'window' => 3600],
        'invest' => ['attempts' => 10, 'window' => 3600],
        'task' => ['attempts' => 20, 'window' => 3600],
        'lottery' => ['attempts' => 10, 'window' => 86400],
        'promo' => ['attempts' => 5, 'window' => 3600],
        'api_global' => ['attempts' => 120, 'window' => 60],
    ],
    'storage' => [
        'cache_dir' => __DIR__ . '/storage/cache/',
        'log_dir'   => __DIR__ . '/storage/logs/',
    ],
];
