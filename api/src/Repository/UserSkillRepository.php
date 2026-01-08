<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSkill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSkill>
 */
class UserSkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSkill::class);
    }

    public function hasReciprocalMatch(User $mentor, User $student): bool
    {
        $count = $this->createQueryBuilder('studentSkill')
            ->select('COUNT(studentSkill.id)')
            ->innerJoin(
                UserSkill::class,
                'mentorSkill',
                'WITH',
                'mentorSkill.skill = studentSkill.skill
                AND mentorSkill.owner = :mentor
                AND mentorSkill.type = :learn'
            )
            ->andWhere('studentSkill.owner = :student')
            ->andWhere('studentSkill.type = :teach')
            ->setParameter('student', $student)
            ->setParameter('mentor', $mentor)
            ->setParameter('teach', UserSkill::TYPE_TEACH)
            ->setParameter('learn', UserSkill::TYPE_LEARN)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    //    /**
    //     * @return UserSkill[] Returns an array of UserSkill objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserSkill
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
