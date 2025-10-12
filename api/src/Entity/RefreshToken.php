<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Entité RefreshToken pour gérer les tokens de rafraîchissement JWT
 *
 * Cette entité étend la classe de base du bundle gesdinet/jwt-refresh-token-bundle
 * et permet de stocker les refresh tokens en base de données.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
    // La classe parente BaseRefreshToken définit déjà l'id
    // Pas besoin de le redéfinir ici
}
