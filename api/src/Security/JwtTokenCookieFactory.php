<?php

namespace App\Security;

use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Cookie;

class JwtTokenCookieFactory
{
    public const ACCESS_TOKEN_COOKIE = 'access_token';
    public const CSRF_COOKIE = 'XSRF-TOKEN';

    public function __construct(
        private readonly int $jwtTtl,
        private readonly bool $cookieSecure, // 👈 ajouté
    ) {
    }

    public function createAccessTokenCookie(string $jwt): Cookie
    {
        $expiration = (new DateTimeImmutable())->add(new DateInterval('PT'.$this->jwtTtl.'S'));

        return Cookie::create(
            name: self::ACCESS_TOKEN_COOKIE,
            value: $jwt,
            expire: $expiration,
            path: '/',
            domain: null,
            secure: $this->cookieSecure, // 👈 ici
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
            partitioned: false,
        );
    }

    public function createCsrfCookie(string $csrfToken): Cookie
    {
        $expiration = (new DateTimeImmutable())->add(new DateInterval('PT'.$this->jwtTtl.'S'));

        return Cookie::create(
            name: self::CSRF_COOKIE,
            value: $csrfToken,
            expire: $expiration,
            path: '/',
            domain: null,
            secure: $this->cookieSecure, // 👈 idem
            httpOnly: false,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
            partitioned: false,
        );
    }
}
