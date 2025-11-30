<?php

namespace App\EventListener;

use App\Security\JwtTokenCookieFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class JwtAccessTokenCookieListener implements EventSubscriberInterface
{
    private const TOKEN_KEY = 'token';
    private const TARGET_PATHS = ['/auth', '/api/token/refresh'];
    private const CSRF_COOKIE = JwtTokenCookieFactory::CSRF_COOKIE;
    private const CSRF_HEADER = 'X-CSRF-TOKEN';

    public function __construct(private readonly JwtTokenCookieFactory $cookieFactory)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST')) {
            return;
        }

        if (!\in_array($request->getPathInfo(), self::TARGET_PATHS, true)) {
            return;
        }

        $response = $event->getResponse();
        if (!$response->isSuccessful()) {
            return;
        }

        $content = $response->getContent();
        if (empty($content)) {
            return;
        }

        $payload = json_decode($content, true);
        if (!\is_array($payload)) {
            return;
        }

        $token = $payload[self::TOKEN_KEY] ?? null;
        if (!\is_string($token) || $token === '') {
            return;
        }

        $response->headers->setCookie($this->cookieFactory->createAccessTokenCookie($token));

        $csrfToken = bin2hex(random_bytes(32));
        $response->headers->setCookie($this->cookieFactory->createCsrfCookie($csrfToken));
        $response->headers->set(self::CSRF_HEADER, $csrfToken);

        unset($payload[self::TOKEN_KEY]);
        $response->setContent(json_encode($payload));
    }
}
