<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Tortinmang\Core\{Request, Response, RateLimiter, Cache, DB};

$req = new Request();

if (!RateLimiter::throttle($req->ip(), 'api_global', 120, 60)) {
    Response::tooManyRequests();
}

$news = Cache::remember('news:active', 300, fn () =>
    DB::get()->fetchAll(
        "SELECT id, title, content, emoji, created_at
         FROM news WHERE is_active = 1
         ORDER BY created_at DESC LIMIT 20"
    )
);

Response::success(['news' => $news]);
