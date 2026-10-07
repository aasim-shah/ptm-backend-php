<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Proxies come from TRUSTED_PROXIES: "*" (behind a reverse proxy that is the
     * only way in, e.g. Caddy on the same host) or a comma-separated IP list.
     */
    protected function proxies()
    {
        $value = config('environment.TRUSTED_PROXIES');
        if (!$value) {
            return $this->proxies;
        }
        return $value === '*' ? '*' : array_map('trim', explode(',', $value));
    }
}
