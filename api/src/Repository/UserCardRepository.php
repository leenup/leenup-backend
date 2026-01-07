<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCard::class);
    }

    /**
     * Retourne les UserCard de l'utilisateur avec la Card jointe.
     */
    public function findUserCards(User $user): array
    {
        return $this->createQueryBuilder('uc')
            ->addSelect('c')
            ->join('uc.card', 'c')
            ->andWhere('uc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('uc.obtainedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
