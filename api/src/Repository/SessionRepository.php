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

    public function hasOverlappingActiveSession(User $mentor, \DateTimeImmutable $start, \DateTimeImmutable $end, ?Session $ignoredSession = null): bool
    {
        foreach ($this->findActiveSessionRanges($mentor, $start, $end, $ignoredSession) as [$sessionStart, $sessionEnd]) {
            if ($sessionStart < $end && $sessionEnd > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * Plages [début, fin] des sessions pending/confirmed du mentor qui peuvent chevaucher [$from, $to].
     *
     * @return array<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    public function findActiveSessionRanges(User $mentor, \DateTimeImmutable $from, \DateTimeImmutable $to, ?Session $ignoredSession = null): array
    {
        // Les sessions durent au plus une journée : on élargit la borne basse pour attraper celles commencées avant $from.
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.mentor = :mentor')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.scheduledAt < :to')
            ->andWhere('s.scheduledAt > :from')
            ->setParameter('mentor', $mentor)
            ->setParameter('statuses', [Session::STATUS_PENDING, Session::STATUS_CONFIRMED])
            ->setParameter('to', $to)
            ->setParameter('from', $from->modify('-1 day'));

        if ($ignoredSession?->getId() !== null) {
            $qb->andWhere('s.id != :ignoredId')->setParameter('ignoredId', $ignoredSession->getId());
        }

        $ranges = [];
        foreach ($qb->getQuery()->getResult() as $session) {
            $sessionStart = $session->getScheduledAt();
            $ranges[] = [$sessionStart, $sessionStart->modify(sprintf('+%d minutes', (int) $session->getDuration()))];
        }

        return $ranges;
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
