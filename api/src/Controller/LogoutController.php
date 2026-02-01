<?php

namespace App\Controller;

use App\Security\JwtTokenCookieFactory;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class LogoutController
{
    public function __construct(
        private readonly JwtTokenCookieFactory $cookieFactory,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // 1) Invalidation du refresh token en BDD (si présent)
        // Nom du cookie du refresh token = "refresh_token" par défaut avec Gesdinet
        $refreshTokenString = $request->cookies->get('refresh_token');

        if (\is_string($refreshTokenString) && $refreshTokenString !== '') {
            // On récupère l'entité RefreshToken correspondante
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);

            if ($refreshToken !== null) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        // 2) Construction de la réponse HTTP 204
        $response = new Response(null, Response::HTTP_NO_CONTENT);

        // 3) Suppression des cookies côté client
        $response->headers->setCookie($this->cookieFactory->createAccessTokenRemovalCookie());
        $response->headers->setCookie($this->cookieFactory->createCsrfRemovalCookie());

        // Cookie refresh_token (géré côté Gesdinet)
        // On le supprime aussi côté client pour être propre
        $response->headers->clearCookie('refresh_token', '/', null);

        return $response;
    }
}
