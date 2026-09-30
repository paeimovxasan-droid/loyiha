<?php
declare(strict_types=1);

namespace Tortinmang\Models;

use Tortinmang\Core\{DB, Config, Logger, TelegramBot};

final class Referral
{
    /** Level => percent */
    public static function levels(): array
    {
        return [
            1 => (int) Config::get('finance.ref_level_1', 10),
            2 => (int) Config::get('finance.ref_level_2', 5),
            3 => (int) Config::get('finance.ref_level_3', 2),
        ];
    }

    /**
     * Process referral bonus FAQAT investitsiyadan.
     * Depozitdan chaqirilmasligi kerak — aks holda ikki marta bonus ketadi.
     * @param int    $userId     Investitsiya qilgan user
     * @param float  $amount     Investitsiya summasi
     * @param string $sourceType 'investment' bo'lishi shart
     */
    public static function processBonus(int $userId, float $amount, string $sourceType = 'investment'): void
    {
        // Faqat investitsiyadan bonus — depozit bo'lsa o'tkazib yuborish
        if ($sourceType !== 'investment') {
            return;
        }

        $db      = DB::get();
        $levels  = self::levels();
        $currentId = $userId;

        foreach ($levels as $level => $percent) {
            $row = $db->fetch('SELECT id, referrer_id FROM users WHERE id = ?', [$currentId]);
            if (!$row || !$row['referrer_id']) break;

            $parentId = (int) $row['referrer_id'];
            $bonus    = (int) floor($amount * $percent / 100);

            if ($bonus <= 0) {
                $currentId = $parentId;
                continue;
            }

            try {
                $db->query(
                    'UPDATE users SET balance = balance + ?, referral_earnings = referral_earnings + ?, total_earned = total_earned + ? WHERE id = ?',
                    [$bonus, $bonus, $bonus, $parentId]
                );

                $db->insert('referral_earnings', [
                    'user_id'      => $parentId,
                    'from_user_id' => $userId,
                    'level'        => $level,
                    'amount'       => $bonus,
                    'source_type'  => $sourceType,
                ]);

                User::updateLevel($parentId);

                // Referal egasiga Telegram xabar
                $fromUser = $db->fetch('SELECT first_name FROM users WHERE id=?', [$currentId]);
                $fromName = $fromUser['first_name'] ?? 'Foydalanuvchi';

                $parentRow = $db->fetch('SELECT telegram_id FROM users WHERE id=?', [$parentId]);
                if ($parentRow) {
                    (new TelegramBot())->notifyReferralBonus(
                        (int) $parentRow['telegram_id'],
                        $bonus,
                        $fromName
                    );
                }
            } catch (\Exception $e) {
                Logger::error("Referral bonus error L{$level}: " . $e->getMessage());
            }

            $currentId = $parentId;
        }
    }

    public static function getStats(int $userId): array
    {
        $db = DB::get();

        $level1 = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE referrer_id = ?',
            [$userId]
        );

        $level2 = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE referrer_id IN (SELECT id FROM users WHERE referrer_id = ?)',
            [$userId]
        );

        $level3 = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE referrer_id IN (SELECT id FROM users WHERE referrer_id IN (SELECT id FROM users WHERE referrer_id = ?))',
            [$userId]
        );

        $totalEarnings = (float) $db->fetchColumn(
            'SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE user_id = ?',
            [$userId]
        );

        $user = User::find($userId);

        return [
            'referral_code'  => $user['referral_code'] ?? '',
            'referral_link'  => 'https://t.me/' . Config::get('telegram.bot_username') . '?start=' . ($user['referral_code'] ?? ''),
            'level_1'        => $level1,
            'level_2'        => $level2,
            'level_3'        => $level3,
            'total_referrals'=> $level1 + $level2 + $level3,
            'total_earnings' => $totalEarnings,
            'levels_percent' => self::levels(),
        ];
    }

    public static function getEarningsHistory(int $userId, int $limit = 20): array
    {
        return DB::get()->fetchAll(
            'SELECT re.amount, re.level, re.source_type, re.created_at, u.first_name, u.username
             FROM referral_earnings re
             JOIN users u ON re.from_user_id = u.id
             WHERE re.user_id = ?
             ORDER BY re.created_at DESC
             LIMIT ?',
            [$userId, $limit]
        );
    }

    public static function getReferrals(int $userId, int $level = 1): array
    {
        return match ($level) {
            1 => DB::get()->fetchAll(
                'SELECT id, first_name, username, level, badge, total_earned, total_deposit, created_at FROM users WHERE referrer_id = ? ORDER BY created_at DESC LIMIT 50',
                [$userId]
            ),
            2 => DB::get()->fetchAll(
                'SELECT id, first_name, username, level, badge, created_at FROM users WHERE referrer_id IN (SELECT id FROM users WHERE referrer_id = ?) ORDER BY created_at DESC LIMIT 50',
                [$userId]
            ),
            3 => DB::get()->fetchAll(
                'SELECT id, first_name, username, level, badge, created_at FROM users WHERE referrer_id IN (SELECT id FROM users WHERE referrer_id IN (SELECT id FROM users WHERE referrer_id = ?)) ORDER BY created_at DESC LIMIT 50',
                [$userId]
            ),
            default => [],
        };
    }
}
