<?php

namespace App\Security;

use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Cookie;

class JwtTokenCookieFactory
{
    public function __construct(private readonly int $jwtTtl)
    {
    }

    public function createAccessTokenCookie(string $jwt): Cookie
    {
        $expiration = (new DateTimeImmutable())->add(new DateInterval('PT' . $this->jwtTtl . 'S'));

        return Cookie::create(
            name: 'access_token',
            value: $jwt,
            expire: $expiration,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
            partitioned: false,
        );
    }
}
