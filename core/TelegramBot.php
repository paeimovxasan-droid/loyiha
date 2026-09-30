<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class TelegramBot
{
    private string $token;
    private string $baseUrl;

    public function __construct(string $token = '')
    {
        $this->token   = $token ?: Config::get('telegram.bot_token', '');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}/";
    }

    public static function instance(): self
    {
        return new self();
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        $params = array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ], $options);

        // parse_mode bo'sh string bo'lsa olib tashla (plain text yuborish)
        if (isset($params['parse_mode']) && $params['parse_mode'] === '') {
            unset($params['parse_mode']);
        }

        $result = $this->request('sendMessage', $params);

        // Markdown parse xatosi bo'lsa (400) parse_mode siz qayta urinib ko'r
        if (!($result['ok'] ?? false) && ($result['error_code'] ?? 0) === 400
            && isset($params['parse_mode'])) {
            unset($params['parse_mode']);
            $result = $this->request('sendMessage', $params);
        }

        return $result;
    }

    public function sendPhoto(int|string $chatId, string $photo, string $caption = '', array $options = []): array
    {
        return $this->request('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo'   => $photo,
            'caption' => $caption,
        ], $options));
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): array
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
        ]);
    }

    public function getChatMember(string|int $chatId, int $userId): array
    {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function isChannelMember(int $userId): bool
    {
        $channelId = Config::get('telegram.channel_id', '');
        if (empty($channelId)) return true;

        $result = $this->getChatMember($channelId, $userId);
        if (empty($result['ok'])) return false;

        $status = $result['result']['status'] ?? '';
        return in_array($status, ['member', 'administrator', 'creator'], true);
    }

    // === NOTIFICATION HELPERS ===

    public function notifyDeposit(int $telegramId, float $amount, string $status, string $note = ''): void
    {
        $icon    = $status === 'approved' ? '✅' : '❌';
        $action  = $status === 'approved' ? 'tasdiqlandi' : 'rad etildi';
        $noteStr = ($status === 'rejected' && $note) ? "\n📝 Sabab: {$note}" : '';

        $this->sendMessage(
            $telegramId,
            "{$icon} *Depozit {$action}!*\n\n💰 Summa: *" . $this->fmt($amount) . "*{$noteStr}"
        );
    }

    public function notifyWithdrawal(int $telegramId, float $amount, string $status, string $note = ''): void
    {
        $icon    = $status === 'approved' ? '✅' : '❌';
        $action  = $status === 'approved' ? 'tasdiqlandi' : 'rad etildi';
        $extra   = $status === 'rejected' ? "\n💸 Pul balansingizga qaytarildi." : '';
        $noteStr = ($status === 'rejected' && $note) ? "\n📝 Sabab: {$note}" : '';

        $this->sendMessage(
            $telegramId,
            "{$icon} *Yechish {$action}!*\n\n💸 Summa: *" . $this->fmt($amount) . "*{$noteStr}{$extra}"
        );
    }

    public function notifyInvestmentComplete(
        int    $telegramId,
        string $packageName,
        float  $amount,
        float  $profit
    ): void {
        $this->sendMessage(
            $telegramId,
            "🎉 *Investitsiya yakunlandi!*\n\n📦 Paket: *{$packageName}*\n💰 Asosiy: *" . $this->fmt($amount) .
            "*\n📈 Daromad: *" . $this->fmt($profit) . "*\n\n💎 Asosiy summa balansingizga qaytarildi!"
        );
    }

    public function notifyDailyProfit(int $telegramId, float $profit): void
    {
        $this->sendMessage(
            $telegramId,
            "💰 *Kunlik daromad!*\n\n📈 Bugun: *+" . $this->fmt($profit) . "*\n\n🚀 Tortinmang bilan boyib boring!"
        );
    }

    public function notifyLotteryWin(int $telegramId, string $prizeName, float $amount): void
    {
        $this->sendMessage(
            $telegramId,
            "🎰 *Lotereya yutuqi!*\n\n🏆 Sovrin: *{$prizeName}*\n💰 +" . $this->fmt($amount) . "\n\n🚀 Tortinmang bilan omad kulib boqmoqda!"
        );
    }

    public function notifyBonus(int $telegramId, float $amount, int $streak): void
    {
        $this->sendMessage(
            $telegramId,
            "🎁 *Kunlik bonus!*\n\n💰 +" . $this->fmt($amount) . "\n🔥 Streak: {$streak} kun\n\n💎 Har kuni kiring va ko'proq bonus oling!"
        );
    }

    public function notifyVipPurchase(int $telegramId, string $tierName): void
    {
        $this->sendMessage(
            $telegramId,
            "👑 *VIP faollashtirildi!*\n\n{$tierName} a'zolik 30 kun davomida faol.\n\n🎁 Barcha imtiyozlardan foydalaning!"
        );
    }

    public function notifyReferralBonus(int $telegramId, float $amount, string $fromName): void
    {
        $this->sendMessage(
            $telegramId,
            "🤝 *Referal bonusi!*\n\n+*" . $this->fmt($amount) . "* — {$fromName} investitsiyasidan"
        );
    }

    private function request(string $method, array $params = []): array
    {
        $url = $this->baseUrl . $method;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("Telegram API cURL error: {$error}", ['method' => $method]);
            return ['ok' => false, 'error' => $error];
        }

        $decoded = json_decode((string) $result, true);
        if (is_array($decoded)) {
            if (empty($decoded['ok'])) {
                // Log Telegram API error (avoid logging full message text)
                Logger::warning('Telegram API error', ['method' => $method, 'chat_id' => $params['chat_id'] ?? null, 'error' => $decoded['description'] ?? null, 'error_code' => $decoded['error_code'] ?? null]);
            }
            return $decoded;
        }

        Logger::error('Telegram API returned non-JSON response', ['method' => $method]);
        return ['ok' => false];
    }

    private function fmt(float $amount): string
    {
        return number_format($amount, 0, '', ' ') . " so'm";
    }
}
