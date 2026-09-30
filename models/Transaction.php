<?php
declare(strict_types=1);

namespace Tortinmang\Models;

use Tortinmang\Core\{DB, Config, Logger, TelegramBot};

final class Transaction
{
    // =========================================================
    // DEPOSITS
    // =========================================================

    public static function createDeposit(
        int    $userId,
        float  $amount,
        string $cardType,
        string $receiptInfo
    ): int {
        return DB::get()->insert('deposits', [
            'user_id'      => $userId,
            'amount'       => $amount,
            'card_type'    => $cardType,
            'receipt_info' => $receiptInfo,
            'status'       => 'pending',
        ]);
    }

    public static function approveDeposit(int $depositId, string $note = ''): bool
    {
        $db  = DB::get();
        $dep = $db->fetch("SELECT * FROM deposits WHERE id = ? AND status = 'pending'", [$depositId]);
        if (!$dep) return false;

        $db->beginTransaction();
        try {
            $db->update('deposits', [
                'status'     => 'approved',
                'admin_note' => $note,
            ], 'id = ?', [$depositId]);

            $db->query(
                'UPDATE users SET balance = balance + ?, total_deposit = total_deposit + ? WHERE id = ?',
                [$dep['amount'], $dep['amount'], $dep['user_id']]
            );

            User::updateLevel((int) $dep['user_id']);
            User::updateBadge((int) $dep['user_id']);

            // Depozitdan referal bonus BERILMAYDI — faqat investitsiyadan beriladi
            // (aks holda bir mablag'dan 2 marta bonus ketadi: depozit + invest)

            // Add to payment feed
            $user = User::find((int) $dep['user_id']);
            if ($user) {
                self::addFeed($user, (float) $dep['amount'], 'deposit');
            }

            $db->commit();

            // Notify user
            $u = $db->fetch('SELECT telegram_id FROM users WHERE id = ?', [$dep['user_id']]);
            if ($u) {
                (new TelegramBot())->notifyDeposit((int) $u['telegram_id'], (float) $dep['amount'], 'approved');
            }

            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error("Deposit approve failed #{$depositId}: " . $e->getMessage());
            return false;
        }
    }

    public static function rejectDeposit(int $depositId, string $note = ''): bool
    {
        $db = DB::get();
        $rows = $db->update('deposits', [
            'status'     => 'rejected',
            'admin_note' => $note,
        ], 'id = ? AND status = ?', [$depositId, 'pending']);

        if ($rows === 0) return false;

        $dep = $db->fetch('SELECT d.amount, u.telegram_id FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.id = ?', [$depositId]);
        if ($dep) {
            (new TelegramBot())->notifyDeposit((int) $dep['telegram_id'], (float) $dep['amount'], 'rejected', $note);
        }

        return true;
    }

    public static function getUserDeposits(int $userId, int $limit = 20): array
    {
        return DB::get()->fetchAll(
            'SELECT id, amount, card_type, status, admin_note, created_at FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    // =========================================================
    // WITHDRAWALS
    // =========================================================

    public static function createWithdrawal(
        int    $userId,
        float  $amount,
        string $cardType,
        string $cardNumber
    ) {
        $db = DB::get();
        $db->beginTransaction();
        try {
            $deducted = User::deductBalance($userId, $amount);
            if (!$deducted) {
                $db->rollBack();
                return false;
            }

            $id = $db->insert('withdrawals', [
                'user_id'     => $userId,
                'amount'      => $amount,
                'card_type'   => $cardType,
                'card_number' => preg_replace('/\s+/', '', $cardNumber),
                'status'      => 'pending',
            ]);

            $db->commit();
            return $id;
        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error("Withdrawal create failed: " . $e->getMessage());
            return false;
        }
    }

    public static function approveWithdrawal(int $withdrawalId, string $note = ''): bool
    {
        $db = DB::get();
        $wd = $db->fetch("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'", [$withdrawalId]);
        if (!$wd) return false;

        $db->beginTransaction();
        try {
            $db->update('withdrawals', [
                'status'     => 'approved',
                'admin_note' => $note,
            ], 'id = ?', [$withdrawalId]);

            $db->query(
                'UPDATE users SET total_withdraw = total_withdraw + ? WHERE id = ?',
                [$wd['amount'], $wd['user_id']]
            );

            $user = User::find((int) $wd['user_id']);
            if ($user) self::addFeed($user, (float) $wd['amount'], 'withdraw');

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error("Withdrawal approve failed #{$withdrawalId}: " . $e->getMessage());
            return false;
        }

        $u = $db->fetch('SELECT telegram_id FROM users WHERE id = ?', [$wd['user_id']]);
        if ($u) {
            (new TelegramBot())->notifyWithdrawal((int) $u['telegram_id'], (float) $wd['amount'], 'approved');
        }

        return true;
    }

    public static function rejectWithdrawal(int $withdrawalId, string $note = ''): bool
    {
        $db = DB::get();
        $wd = $db->fetch("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'", [$withdrawalId]);
        if (!$wd) return false;

        $db->beginTransaction();
        try {
            $db->update('withdrawals', [
                'status'     => 'rejected',
                'admin_note' => $note,
            ], 'id = ?', [$withdrawalId]);

            // Return balance atomically
            User::updateBalance((int) $wd['user_id'], (float) $wd['amount']);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error("Withdrawal reject failed #{$withdrawalId}: " . $e->getMessage());
            return false;
        }

        $u = $db->fetch('SELECT telegram_id FROM users WHERE id = ?', [$wd['user_id']]);
        if ($u) {
            (new TelegramBot())->notifyWithdrawal((int) $u['telegram_id'], (float) $wd['amount'], 'rejected', $note);
        }

        return true;
    }

    public static function getUserWithdrawals(int $userId, int $limit = 20): array
    {
        return DB::get()->fetchAll(
            'SELECT id, amount, card_type, card_number, status, admin_note, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    // =========================================================
    // COMMON
    // =========================================================

    public static function getTodayWithdrawalTotal(int $userId): float
    {
        return (float) DB::get()->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE user_id = ? AND status != 'rejected' AND DATE(created_at) = CURDATE()",
            [$userId]
        );
    }

    public static function getPendingCount(int $userId, string $type): int
    {
        $table = $type === 'deposit' ? 'deposits' : 'withdrawals';
        return (int) DB::get()->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE user_id = ? AND status = 'pending'",
            [$userId]
        );
    }

    private static function addFeed(array $user, float $amount, string $type): void
    {
        $lastName = $user['last_name'] ?? '';
        $name = trim($user['first_name'] . ' ' . mb_substr($lastName, 0, 1));
        if ($name && $lastName) $name .= '.';

        DB::get()->insert('payment_feed', [
            'user_display_name' => $name ?: 'Foydalanuvchi',
            'amount'            => $amount,
            'feed_type'         => $type,
            'is_fake'           => 0,
        ]);
    }
}
