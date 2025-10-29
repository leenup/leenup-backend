<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Trouve une conversation entre deux utilisateurs
     * Fonctionne peu importe l'ordre des participants grâce à la normalisation
     */
    public function findConversationBetweenUsers(User $user1, User $user2): ?Conversation
    {
        // Normaliser l'ordre des IDs pour la recherche
        $smallerId = min($user1->getId(), $user2->getId());
        $biggerId = max($user1->getId(), $user2->getId());

        return $this->createQueryBuilder('c')
            ->join('c.participant1', 'p1')
            ->join('c.participant2', 'p2')
            ->where('p1.id = :smallerId AND p2.id = :biggerId')
            ->setParameter('smallerId', $smallerId)
            ->setParameter('biggerId', $biggerId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les conversations d'un utilisateur
     * Optimisée avec eager loading pour éviter les N+1 queries
     */
    public function findUserConversations(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.messages', 'm')
            ->addSelect('m')
            ->leftJoin('c.participant1', 'p1')
            ->leftJoin('c.participant2', 'p2')
            ->addSelect('p1', 'p2')
            ->where('c.participant1 = :user OR c.participant2 = :user')
            ->setParameter('user', $user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
