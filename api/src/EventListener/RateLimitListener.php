<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * EventListener pour appliquer le rate limiting sur les endpoints critiques
 */
class RateLimitListener
{
    public function __construct(
        private RateLimiterFactory $authLimiter,
        private RateLimiterFactory $registerLimiter,
        private RateLimiterFactory $refreshTokenLimiter
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Désactiver le rate limiting en environnement test
        if ($this->environment === 'test') {
            return;
        }

        $request = $event->getRequest();

        // Ne s'applique que sur les requêtes principales
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Identifier l'IP du client
        $identifier = $request->getClientIp();

        // Rate limit sur /auth (POST)
        if ($path === '/auth' && $method === 'POST') {
            $limiter = $this->authLimiter->create($identifier);

            if (!$limiter->consume(1)->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    '@context' => '/contexts/Error',
                    '@type' => 'Error',
                    'title' => 'Too Many Requests',
                    'detail' => 'Too many login attempts. Please try again later.',
                    'status' => 429,
                ], 429));
                return;
            }
        }

        // Rate limit sur /register (POST)
        if ($path === '/register' && $method === 'POST') {
            $limiter = $this->registerLimiter->create($identifier);

            if (!$limiter->consume(1)->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    '@context' => '/contexts/Error',
                    '@type' => 'Error',
                    'title' => 'Too Many Requests',
                    'detail' => 'Too many registration attempts. Please try again later.',
                    'status' => 429,
                ], 429));
                return;
            }
        }

        // Rate limit sur /api/token/refresh (POST)
        if ($path === '/api/token/refresh' && $method === 'POST') {
            $limiter = $this->refreshTokenLimiter->create($identifier);

            if (!$limiter->consume(1)->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    '@context' => '/contexts/Error',
                    '@type' => 'Error',
                    'title' => 'Too Many Requests',
                    'detail' => 'Too many token refresh attempts. Please try again later.',
                    'status' => 429,
                ], 429));
                return;
            }
        }
    }
}
