<?php

namespace App\EventListener;

use App\Security\JwtTokenCookieFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class CsrfCookieRequestListener implements EventSubscriberInterface
{
    private const CSRF_COOKIE = JwtTokenCookieFactory::CSRF_COOKIE;
    private const CSRF_HEADER = 'X-CSRF-TOKEN';
    private const ACCESS_TOKEN_COOKIE = JwtTokenCookieFactory::ACCESS_TOKEN_COOKIE;
    private const EXCLUDED_PATHS = ['/auth', '/api/token/refresh', '/register'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // GET / HEAD / OPTIONS → pas de CSRF
        if ($request->isMethodSafe()) {
            return;
        }

        // Login + refresh → pas de CSRF (token pas encore là / rotation)
        if (\in_array($request->getPathInfo(), self::EXCLUDED_PATHS, true)) {
            return;
        }

        // Si pas de cookie access_token → pas connecté, pas besoin de CSRF
        $accessTokenCookie = $request->cookies->get(self::ACCESS_TOKEN_COOKIE);
        if ($accessTokenCookie === null) {
            return;
        }

        $csrfCookie = $request->cookies->get(self::CSRF_COOKIE);
        $csrfHeader = $request->headers->get(self::CSRF_HEADER);

        if (!\is_string($csrfCookie) || $csrfCookie === '' || !\is_string($csrfHeader) || $csrfHeader === '') {
            throw new AccessDeniedHttpException('Missing CSRF token');
        }

        if (!hash_equals($csrfCookie, $csrfHeader)) {
            throw new AccessDeniedHttpException('Invalid CSRF token');
        }
    }
}
