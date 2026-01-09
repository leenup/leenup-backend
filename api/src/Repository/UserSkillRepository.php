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

    public function hasPerfectMatch(User $student, User $mentor): bool
    {
        $count = $this->createQueryBuilder('teach')
            ->select('COUNT(teach.id)')
            ->innerJoin(UserSkill::class, 'learn', 'WITH', 'teach.skill = learn.skill')
            ->andWhere('teach.owner = :student')
            ->andWhere('teach.type = :teachType')
            ->andWhere('learn.owner = :mentor')
            ->andWhere('learn.type = :learnType')
            ->setParameters([
                'student' => $student,
                'mentor' => $mentor,
                'teachType' => UserSkill::TYPE_TEACH,
                'learnType' => UserSkill::TYPE_LEARN,
            ])
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
