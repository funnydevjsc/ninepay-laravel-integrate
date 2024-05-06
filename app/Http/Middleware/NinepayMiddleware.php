<?php

namespace App\Http\Middleware;

use Closure;

class NinepayMiddleware
{
    const VALID_IPS = ['42.96.42.107', '42.96.55.214', '42.96.55.178'];

    public function handle($request, Closure $next)
    {
        if ($this->containsValidIpAddress($request->header('X-Forwarded-For')) ||
            $this->containsValidIpAddress($request->header('X-Real-Ip')) ||
            $this->containsValidIpAddress($request->ip())
        ) {
            return $next($request);
        }

        abort(403, 'Permission denied');
    }

    private function containsValidIpAddress($ip): bool
    {
        return in_array($ip, self::VALID_IPS);
    }
}
