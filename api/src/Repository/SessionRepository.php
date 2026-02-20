<?php

namespace App\Repository;

use App\Entity\Session;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    public function hasOverlappingActiveSession(User $mentor, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $sessions = $this->createQueryBuilder('s')
            ->andWhere('s.mentor = :mentor')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('mentor', $mentor)
            ->setParameter('statuses', [Session::STATUS_PENDING, Session::STATUS_CONFIRMED])
            ->getQuery()
            ->getResult();

        foreach ($sessions as $session) {
            $sessionStart = $session->getScheduledAt();
            if (!$sessionStart) {
                continue;
            }

            $sessionEnd = $sessionStart->modify(sprintf('+%d minutes', (int) $session->getDuration()));
            if ($sessionStart < $end && $sessionEnd > $start) {
                return true;
            }
        }

        return false;
    }

    //    /**
    //     * @return Session[] Returns an array of Session objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Session
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
