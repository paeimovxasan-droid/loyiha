<?php
declare(strict_types=1);

namespace Tortinmang\Models;

use Tortinmang\Core\{DB, Helpers, Logger};

final class Investment
{
    /**
     * @param int $userId
     * @param int $packageId
     * @param float $amount
     * @return array|false
     */
    public static function create(int $userId, int $packageId, float $amount)
    {
        $db = DB::get();

        $package = $db->fetch(
            'SELECT * FROM packages WHERE id = ? AND is_active = 1',
            [$packageId]
        );
        if (!$package) return false;

        $user = User::find($userId);
        if (!$user || (float) $user['balance'] < $amount) return false;

        $dailyProfit = Helpers::dailyProfit($amount, (float) $package['daily_percent']);
        $durationDays = (int) $package['duration_days'];
        $expiresAt   = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

        $db->beginTransaction();
        try {
            // Deduct balance
            $deducted = User::deductBalance($userId, $amount);
            if (!$deducted) {
                $db->rollBack();
                return false;
            }

            $id = $db->insert('investments', [
                'user_id'      => $userId,
                'package_id'   => $packageId,
                'amount'       => $amount,
                'daily_profit' => $dailyProfit,
                'total_earned' => 0,
                'days_passed'  => 0,
                'duration_days'=> $durationDays,
                'status'       => 'active',
                'started_at'   => date('Y-m-d H:i:s'),
                'expires_at'   => $expiresAt,
            ]);

            // Process referral bonus on investment
            Referral::processBonus($userId, $amount, 'investment');

            $db->commit();
            return $db->fetch('SELECT i.*, p.name as package_name FROM investments i JOIN packages p ON i.package_id = p.id WHERE i.id = ?', [$id]);
        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error('Investment create failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function findActive(int $userId): array
    {
        return DB::get()->fetchAll(
            'SELECT i.*, p.name as package_name, p.daily_percent, p.icon, p.color
             FROM investments i
             JOIN packages p ON i.package_id = p.id
             WHERE i.user_id = ? AND i.status = ?
             ORDER BY i.started_at DESC',
            [$userId, 'active']
        );
    }

    public static function findAll(int $userId): array
    {
        return DB::get()->fetchAll(
            'SELECT i.*, p.name as package_name, p.daily_percent, p.icon, p.color
             FROM investments i
             JOIN packages p ON i.package_id = p.id
             WHERE i.user_id = ?
             ORDER BY i.started_at DESC
             LIMIT 50',
            [$userId]
        );
    }

    /**
     * Process daily profit for all active investments.
     * Called by cron job.
     */
    public static function processDaily(): array
    {
        $db     = DB::get();
        $today  = date('Y-m-d');
        $now    = date('Y-m-d H:i:s');
        $result = ['processed' => 0, 'completed' => 0, 'errors' => 0];

        $investments = $db->fetchAll(
            'SELECT i.*, u.telegram_id, p.daily_percent, p.name as package_name
             FROM investments i
             JOIN users u ON i.user_id = u.id
             JOIN packages p ON i.package_id = p.id
             WHERE i.status = ?
               AND (i.last_profit_date IS NULL OR i.last_profit_date < ?)
             ORDER BY i.id ASC',
            ['active', $today]
        );

        foreach ($investments as $inv) {
            try {
                $db->beginTransaction();

                $dailyProfit = (float) $inv['daily_profit']
                    ?: Helpers::dailyProfit((float) $inv['amount'], (float) $inv['daily_percent']);

                $isExpired = strtotime($inv['expires_at']) <= time();

                if ($isExpired) {
                    // Complete investment: return principal
                    $db->query(
                        "UPDATE investments SET status = 'completed', days_passed = days_passed + 1, last_profit_date = ? WHERE id = ?",
                        [$today, $inv['id']]
                    );
                    // Return principal — addEarnings updates both balance AND total_earned
                    // so the returned capital becomes withdrawable
                    User::addEarnings((int) $inv['user_id'], (float) $inv['amount']);
                    // Notify user
                    (new \Tortinmang\Core\TelegramBot())->notifyInvestmentComplete(
                        (int) $inv['telegram_id'],
                        (string) $inv['package_name'],
                        (float) $inv['amount'],
                        (float) $inv['total_earned']
                    );
                    $result['completed']++;
                } else {
                    // Add daily profit
                    $db->query(
                        'UPDATE investments SET days_passed = days_passed + 1, total_earned = total_earned + ?, last_profit_date = ? WHERE id = ?',
                        [$dailyProfit, $today, $inv['id']]
                    );
                    User::addEarnings((int) $inv['user_id'], $dailyProfit);
                    // Notify daily profit
                    (new \Tortinmang\Core\TelegramBot())->notifyDailyProfit(
                        (int) $inv['telegram_id'],
                        $dailyProfit
                    );
                    $result['processed']++;
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                Logger::error("Daily profit error for investment {$inv['id']}: " . $e->getMessage());
                $result['errors']++;
            }
        }

        return $result;
    }

    public static function getStats(int $userId): array
    {
        $db = DB::get();
        return [
            'active_count'  => (int)   $db->fetchColumn("SELECT COUNT(*) FROM investments WHERE user_id = ? AND status = 'active'", [$userId]),
            'total_invested' => (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE user_id = ? AND status != 'cancelled'", [$userId]),
            'total_earned'   => (float) $db->fetchColumn("SELECT COALESCE(SUM(total_earned), 0) FROM investments WHERE user_id = ?", [$userId]),
            'active_amount'  => (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM investments WHERE user_id = ? AND status = 'active'", [$userId]),
            'daily_income'   => (float) $db->fetchColumn("SELECT COALESCE(SUM(daily_profit), 0) FROM investments WHERE user_id = ? AND status = 'active'", [$userId]),
        ];
    }
}
