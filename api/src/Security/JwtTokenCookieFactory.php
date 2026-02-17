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
        private readonly bool $cookieSecure,
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
            secure: $this->cookieSecure,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_NONE,
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
            secure: $this->cookieSecure,
            httpOnly: false, // lisible par le front
            raw: false,
            sameSite: Cookie::SAMESITE_NONE,
            partitioned: false,
        );
    }

    public function createAccessTokenRemovalCookie(): Cookie
    {
        return $this->createRemovalCookie(self::ACCESS_TOKEN_COOKIE, true);
    }

    public function createCsrfRemovalCookie(): Cookie
    {
        return $this->createRemovalCookie(self::CSRF_COOKIE, false);
    }

    private function createRemovalCookie(string $name, bool $httpOnly): Cookie
    {
        $expiration = (new DateTimeImmutable())->sub(new DateInterval('PT1H'));

        return Cookie::create(
            name: $name,
            value: '',
            expire: $expiration,
            path: '/',
            domain: null,
            secure: $this->cookieSecure,
            httpOnly: $httpOnly,
            raw: false,
            sameSite: Cookie::SAMESITE_NONE,
            partitioned: false,
        );
    }
}
