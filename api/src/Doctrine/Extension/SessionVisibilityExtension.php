<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final class SessionVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        $this->addVisibilityConstraint($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, string $operationName = null, array $context = []): void
    {
        $this->addVisibilityConstraint($queryBuilder, $resourceClass);
    }

    private function addVisibilityConstraint(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if ($resourceClass !== Session::class) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->andWhere(sprintf('%1$s.mentor = :currentUser OR %1$s.student = :currentUser', $rootAlias))
            ->setParameter('currentUser', $user);
    }
}
