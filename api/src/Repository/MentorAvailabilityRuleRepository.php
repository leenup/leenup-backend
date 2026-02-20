<?php

namespace App\Repository;

use App\Entity\MentorAvailabilityRule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MentorAvailabilityRule>
 */
class MentorAvailabilityRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MentorAvailabilityRule::class);
    }

    /**
     * @return MentorAvailabilityRule[]
     */
    public function findActiveByMentor(User $mentor): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.mentor = :mentor')
            ->andWhere('r.isActive = true')
            ->setParameter('mentor', $mentor)
            ->getQuery()
            ->getResult();
    }
}
