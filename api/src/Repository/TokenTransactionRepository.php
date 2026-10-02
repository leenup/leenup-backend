<?php

namespace App\Repository;

use App\Entity\TokenTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TokenTransaction>
 */
class TokenTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TokenTransaction::class);
    }

    /**
     * Mouvements d'un utilisateur, du plus récent au plus ancien (session et compétence pré-chargées).
     */
    public function createForUserQueryBuilder(User $user, ?string $type = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('s', 'sk')
            ->leftJoin('t.session', 's')
            ->leftJoin('s.skill', 'sk')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        if ($type !== null) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb;
    }
}
