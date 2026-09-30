<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Helpers
{
    /** Format money: 1500000 → "1 500 000 so'm" */
    public static function money(float $amount): string
    {
        return number_format($amount, 0, '', ' ') . " so'm";
    }

    /** Format percent: 2.5 → "2.5%" */
    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.') . '%';
    }

    /** Format date to Uzbek-friendly string */
    public static function date(string $date): string
    {
        $months = [
            1  => 'yanvar',  2  => 'fevral',  3  => 'mart',
            4  => 'aprel',   5  => 'may',     6  => 'iyun',
            7  => 'iyul',    8  => 'avgust',  9  => 'sentyabr',
            10 => 'oktyabr', 11 => 'noyabr',  12 => 'dekabr',
        ];
        $ts    = strtotime($date);
        $day   = (int) date('j', $ts);
        $month = $months[(int) date('n', $ts)];
        $year  = date('Y', $ts);
        return "{$day} {$month} {$year}";
    }

    /** Generate a secure random token */
    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /** Safe integer addition avoiding float precision issues */
    public static function addMoney(float $a, float $b): float
    {
        return round($a + $b, 2);
    }

    /** Safe subtraction */
    public static function subMoney(float $a, float $b): float
    {
        return round($a - $b, 2);
    }

    /** Calculate daily profit */
    public static function dailyProfit(float $amount, float $percent): float
    {
        return floor($amount * $percent / 100);
    }

    /** Mask card number: 8600123456789012 → 8600 **** **** 9012 */
    public static function maskCard(string $card): string
    {
        $clean = preg_replace('/\s+/', '', $card);
        if (strlen($clean) !== 16) return $card;
        return substr($clean, 0, 4) . ' **** **** ' . substr($clean, -4);
    }

    /** Mask name: "Sardor Karimov" → "S*** K***" */
    public static function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));
        return implode(' ', array_map(
            fn(string $p): string => mb_substr($p, 0, 1) . str_repeat('*', max(2, mb_strlen($p) - 1)),
            $parts
        ));
    }

    /** Get client IP safely */
    public static function clientIp(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /** Check if string is valid card number (16 digits) */
    public static function isValidCard(string $card): bool
    {
        $clean = preg_replace('/\s+/', '', $card);
        return (bool) preg_match('/^\d{16}$/', $clean);
    }

    /** Sanitize card number to digits only */
    public static function cleanCard(string $card): string
    {
        return preg_replace('/\D/', '', $card);
    }

    /** Format card number with spaces */
    public static function formatCard(string $card): string
    {
        $clean = self::cleanCard($card);
        return implode(' ', str_split($clean, 4));
    }
}
