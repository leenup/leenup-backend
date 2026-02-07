<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class RateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.auth')]
        private readonly RateLimiterFactory $authLimiter,

        #[Autowire(service: 'limiter.register')]
        private readonly RateLimiterFactory $registerLimiter,

        #[Autowire(service: 'limiter.refresh_token')]
        private readonly RateLimiterFactory $refreshTokenLimiter,

        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Désactiver le rate limiting en environnement test
        if ($this->environment === 'test') {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        $identifier = $request->getClientIp() ?? 'unknown';

        if ($path === '/auth' && $method === 'POST') {
            $this->consumeOr429($event, $this->authLimiter->create($identifier), 'Too many login attempts. Please try again later.');
            return;
        }

        if ($path === '/register' && $method === 'POST') {
            $this->consumeOr429($event, $this->registerLimiter->create($identifier), 'Too many registration attempts. Please try again later.');
            return;
        }

        if ($path === '/api/token/refresh' && $method === 'POST') {
            $this->consumeOr429($event, $this->refreshTokenLimiter->create($identifier), 'Too many token refresh attempts. Please try again later.');
            return;
        }
    }

    private function consumeOr429(RequestEvent $event, $limiter, string $detail): void
    {
        if ($limiter->consume(1)->isAccepted()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            '@context' => '/contexts/Error',
            '@type' => 'Error',
            'title' => 'Too Many Requests',
            'detail' => $detail,
            'status' => 429,
        ], 429));
    }
}
