<?php
declare(strict_types=1);

namespace Tortinmang\Models;

use Tortinmang\Core\{DB, Config, Logger};

final class User
{
    /**
     * @param int $id
     * @return array|false
     */
    public static function find(int $id)
    {
        return DB::get()->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /**
     * @param int $telegramId
     * @return array|false
     */
    public static function findByTelegramId(int $telegramId)
    {
        return DB::get()->fetch('SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
    }

    /**
     * @param string $code
     * @return array|false
     */
    public static function findByReferralCode(string $code)
    {
        return DB::get()->fetch('SELECT * FROM users WHERE referral_code = ?', [strtoupper($code)]);
    }

    /**
     * @param array $data
     * @return array|false
     */
    public static function create(array $data)
    {
        try {
            $id = DB::get()->insert('users', array_merge([
                'balance'           => 0,
                'total_earned'      => 0,
                'total_deposit'     => 0,
                'total_withdraw'    => 0,
                'referral_earnings' => 0,
                'level'             => 1,
                'badge'             => 'yangi',
                'daily_bonus_streak'=> 0,
                'is_blocked'        => 0,
                'is_trusted'        => 0,
            ], $data));

            return self::find($id);
        } catch (\Exception $e) {
            Logger::error('User create failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function update(int $id, array $data): bool
    {
        return DB::get()->update('users', $data, 'id = ?', [$id]) >= 0;
    }

    /** Add amount to balance only */
    public static function updateBalance(int $id, float $amount): bool
    {
        $rows = DB::get()->query(
            'UPDATE users SET balance = balance + ? WHERE id = ?',
            [$amount, $id]
        )->rowCount();
        return $rows > 0;
    }

    /** Deduct from balance with safety check */
    public static function deductBalance(int $id, float $amount): bool
    {
        $rows = DB::get()->query(
            'UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?',
            [$amount, $id, $amount]
        )->rowCount();
        return $rows > 0;
    }

    /** Add to balance AND total_earned */
    public static function addEarnings(int $id, float $amount): bool
    {
        $rows = DB::get()->query(
            'UPDATE users SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?',
            [$amount, $amount, $id]
        )->rowCount();
        self::updateLevel($id);
        return $rows > 0;
    }

    /** Recalculate level based on total_earned */
    public static function updateLevel(int $id): void
    {
        $user = self::find($id);
        if (!$user) return;

        $earned = (float) $user['total_earned'];
        $levels = Config::get('levels', []);
        $newLevel = 1;

        foreach ($levels as $lvl => $required) {
            if ($earned >= $required) $newLevel = $lvl;
        }

        DB::get()->update('users', ['level' => $newLevel], 'id = ?', [$id]);
    }

    /** Update badge based on rules */
    public static function updateBadge(int $id): void
    {
        $user = self::find($id);
        if (!$user) return;

        $badge       = $user['badge'] ?? 'yangi';
        $totalDep    = (float) $user['total_deposit'];
        $refCount    = self::getReferralCount($id);
        $liderRefs   = self::getReferralCountByBadge($id, 'lider');
        $investorRefs = self::getReferralCountByBadge($id, 'investor');

        $newBadge = match (true) {
            $liderRefs >= 10 && $investorRefs >= 5 && $refCount >= 100 => 'kurator',
            $refCount >= 50                                              => 'lider',
            $totalDep >= 10_000_000                                     => 'investor',
            default                                                      => $badge,
        };

        if ($newBadge !== $badge) {
            DB::get()->update('users', ['badge' => $newBadge], 'id = ?', [$id]);
        }
    }

    public static function getReferralCount(int $userId): int
    {
        return (int) DB::get()->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE referrer_id = ?',
            [$userId]
        );
    }

    private static function getReferralCountByBadge(int $userId, string $badge): int
    {
        return (int) DB::get()->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE referrer_id = ? AND badge = ?',
            [$userId, $badge]
        );
    }

    public static function getLevelInfo(int $level): array
    {
        $names = Config::get('level_names', []);
        $levels = Config::get('levels', []);

        return [
            'level'    => $level,
            'name'     => $names[$level] ?? "Daraja {$level}",
            'min_earn' => $levels[$level] ?? 0,
            'max_earn' => $levels[$level + 1] ?? null,
            'color'    => self::levelColor($level),
        ];
    }

    public static function getBadgeInfo(string $badge): array
    {
        return match ($badge) {
            'investor' => ['icon' => '💰', 'color' => '#f59e0b', 'label' => 'Investor'],
            'lider'    => ['icon' => '👑', 'color' => '#a855f7', 'label' => 'Lider'],
            'kurator'  => ['icon' => '🏆', 'color' => '#10b981', 'label' => 'Kurator'],
            default    => ['icon' => '🆕', 'color' => '#94a3b8', 'label' => 'Yangi'],
        };
    }

    private static function levelColor(int $level): string
    {
        return match (true) {
            $level >= 10 => '#f59e0b',
            $level >= 8  => '#a855f7',
            $level >= 6  => '#06b6d4',
            $level >= 4  => '#10b981',
            $level >= 2  => '#3b82f6',
            default      => '#94a3b8',
        };
    }

    public static function getStats(int $id): array
    {
        $db = DB::get();
        return [
            'deposits_count'     => (int) $db->fetchColumn('SELECT COUNT(*) FROM deposits WHERE user_id = ?', [$id]),
            'deposits_approved'  => (int) $db->fetchColumn("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status='approved'", [$id]),
            'withdrawals_count'  => (int) $db->fetchColumn('SELECT COUNT(*) FROM withdrawals WHERE user_id = ?', [$id]),
            'investments_count'  => (int) $db->fetchColumn('SELECT COUNT(*) FROM investments WHERE user_id = ?', [$id]),
            'active_investments' => (int) $db->fetchColumn("SELECT COUNT(*) FROM investments WHERE user_id = ? AND status='active'", [$id]),
            'referral_count'     => self::getReferralCount($id),
        ];
    }

    /** Get public profile data (safe to expose) */
    public static function publicData(array $user): array
    {
        $levelInfo = self::getLevelInfo((int) $user['level']);
        $badgeInfo = self::getBadgeInfo($user['badge'] ?? 'yangi');

        return [
            'id'                  => (int)   $user['id'],
            'telegram_id'         => (int)   $user['telegram_id'],
            'first_name'          => $user['first_name'],
            'last_name'           => $user['last_name'],
            'username'            => $user['username'],
            'balance'             => (float) $user['balance'],
            'total_earned'        => (float) $user['total_earned'],
            'total_deposit'       => (float) $user['total_deposit'],
            'total_withdraw'      => (float) $user['total_withdraw'],
            'referral_earnings'   => (float) $user['referral_earnings'],
            'referral_code'       => $user['referral_code'],
            'level'               => (int)   $user['level'],
            'level_name'          => $levelInfo['name'],
            'level_color'         => $levelInfo['color'],
            'badge'               => $user['badge'] ?? 'yangi',
            'badge_icon'          => $badgeInfo['icon'],
            'badge_color'         => $badgeInfo['color'],
            'badge_label'         => $badgeInfo['label'],
            'daily_bonus_streak'  => (int)   $user['daily_bonus_streak'],
            'last_bonus_date'     => $user['last_bonus_date'],
            'is_trusted'          => (int)   ($user['is_trusted'] ?? 0),
            'phone_verified'      => (bool)  ($user['phone_verified'] ?? false),
            'available_withdraw'  => max(0.0, (float) $user['total_earned'] - (float) $user['total_withdraw']),
            'next_level_threshold'=> $levelInfo['max_earn'] ?? null,
            'created_at'          => $user['created_at'],
        ];
    }
}
