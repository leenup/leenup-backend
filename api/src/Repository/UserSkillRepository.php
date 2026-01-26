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
        $teachSkillIds = $this->createQueryBuilder('teach')
            ->select('IDENTITY(teach.skill) AS skillId')
            ->andWhere('teach.owner = :student')
            ->andWhere('teach.type = :teachType')
            ->setParameters([
                'student' => $student,
                'teachType' => UserSkill::TYPE_TEACH,
            ])
            ->getQuery()
            ->getScalarResult();

        if ($teachSkillIds === []) {
            return false;
        }

        $skillIds = array_map(static fn (array $row): int => (int) $row['skillId'], $teachSkillIds);

        $count = $this->createQueryBuilder('learn')
            ->select('COUNT(learn.id)')
            ->andWhere('learn.owner = :mentor')
            ->andWhere('learn.type = :learnType')
            ->andWhere('learn.skill IN (:skillIds)')
            ->setParameters([
                'mentor' => $mentor,
                'learnType' => UserSkill::TYPE_LEARN,
                'skillIds' => $skillIds,
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
