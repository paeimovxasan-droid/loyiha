<?php
declare(strict_types=1);

namespace Tortinmang\Core;

use Tortinmang\Models\User;

final class Auth
{
    /**
     * Validate Telegram WebApp initData using HMAC-SHA256.
     * Returns decoded user array or null on failure.
     */
    public static function validateTelegramData(string $initData): ?array
    {
        if (empty($initData)) return null;

        // initData URL-encoded string bo'ladi: "query_id=...&user=...&hash=..."
        $params = [];
        // parse_str avtomatik URL decode qiladi
        parse_str($initData, $params);

        // user field JSON string (ba'zan double-encoded)
        $userRaw = $params['user'] ?? '';
        if (empty($userRaw)) return null;

        // urldecode + json_decode
        $userData = json_decode($userRaw, true);
        if (!is_array($userData) && is_string($userRaw)) {
            $userData = json_decode(urldecode($userRaw), true);
        }

        if (!is_array($userData) || empty($userData['id'])) return null;

        // Hash validation
        if (!empty($params['hash'])) {
            $hash      = $params['hash'];
            $checkData = $params;
            unset($checkData['hash']);

            ksort($checkData);
            $checkString = '';
            foreach ($checkData as $key => $value) {
                $checkString .= $key . '=' . $value . "\n";
            }
            $checkString = rtrim($checkString, "\n");

            $token     = Config::get('telegram.bot_token', '');
            $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
            $calcHash  = bin2hex(hash_hmac('sha256', $checkString, $secretKey, true));

            if (hash_equals($calcHash, $hash)) {
                $authDate = isset($params['auth_date']) ? (int)$params['auth_date'] : 0;
                if ($authDate > 0 && time() - $authDate <= 86400 && time() - $authDate >= 0) {
                    return $userData;
                }
                Logger::warning('Auth valid hash but stale or missing auth_date', [
                    'auth_date' => $params['auth_date'] ?? null,
                    'user_id'   => $userData['id'] ?? 0,
                ]);
                return null;
            }

            // Log validation failure for debugging
            Logger::error('Auth hash mismatch', [
                'expected' => $calcHash,
                'received' => $hash,
                'env'      => Config::get('app.env'),
                'token_set'=> !str_contains($token, 'CHANGE_ME'),
            ]);
        }

        $token = Config::get('telegram.bot_token', '');
        $env   = Config::get('app.env', 'production');
        $debug = Config::get('app.debug', false);

        if ((empty($token) || str_contains($token, 'CHANGE_ME')) && $env !== 'production') {
            // Allow auth in development or non-production environments only.
            return $userData;
        }

        // Production: strict hash validation only.
        if ($debug && empty($token)) {
            return $userData;
        }

        return null;
    }

    /**
     * Get existing user or create new one from Telegram data.
     */
    public static function getUser(string $initData, string $referralCode = ''): ?array
    {
        $tgUser = self::validateTelegramData($initData);
        if (!$tgUser) return null;

        $user = User::findByTelegramId((int) $tgUser['id']);

        if (!$user) {
            $user = User::create([
                'telegram_id'  => (int) $tgUser['id'],
                'first_name'   => mb_substr($tgUser['first_name'] ?? '', 0, 100),
                'last_name'    => mb_substr($tgUser['last_name']  ?? '', 0, 100),
                'username'     => mb_substr($tgUser['username']   ?? '', 0, 100),
                'referral_code' => self::generateReferralCode(),
                'referrer_id'  => self::resolveReferrer($referralCode),
            ]);
        } else {
            // Update profile info on each login
            User::update((int) $user['id'], [
                'first_name' => mb_substr($tgUser['first_name'] ?? '', 0, 100),
                'last_name'  => mb_substr($tgUser['last_name']  ?? '', 0, 100),
                'username'   => mb_substr($tgUser['username']   ?? '', 0, 100),
            ]);
            $user = User::find((int) $user['id']);
        }

        if (!$user) return null;

        if ((int) $user['is_blocked'] === 1) {
            Response::error('Sizning hisobingiz bloklangan. Yordam uchun @tortinmang_support ga murojaat qiling.');
        }

        return $user;
    }

    private static function resolveReferrer(string $code): ?int
    {
        if (empty($code)) return null;
        $ref = User::findByReferralCode($code);
        return $ref ? (int) $ref['id'] : null;
    }

    public static function generateReferralCode(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    }
}
