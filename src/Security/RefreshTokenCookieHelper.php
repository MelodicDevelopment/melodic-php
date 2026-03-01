<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Response;

class RefreshTokenCookieHelper
{
    public static function attach(Response $response, string $rawToken, RefreshTokenConfig $config): Response
    {
        return $response->withCookie($config->cookieName, $rawToken, [
            'expires' => time() + $config->tokenLifetime,
            'path' => $config->cookiePath,
            'domain' => $config->cookieDomain,
            'secure' => $config->cookieSecure,
            'httponly' => true,
            'samesite' => $config->cookieSameSite,
        ]);
    }

    public static function clear(Response $response, RefreshTokenConfig $config): Response
    {
        return $response->withCookie($config->cookieName, '', [
            'expires' => 1,
            'path' => $config->cookiePath,
            'domain' => $config->cookieDomain,
            'secure' => $config->cookieSecure,
            'httponly' => true,
            'samesite' => $config->cookieSameSite,
        ]);
    }
}
