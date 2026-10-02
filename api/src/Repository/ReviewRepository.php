<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return array{average: float, count: int}
     */
    public function getRatingStatsForMentor(User $mentor): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS average, COUNT(r.id) AS count')
            ->join('r.session', 's')
            ->where('s.mentor = :mentor')
            ->setParameter('mentor', $mentor)
            ->getQuery()
            ->getSingleResult();

        return [
            'average' => (float) ($row['average'] ?? 0),
            'count' => (int) $row['count'],
        ];
    }

    /**
     * Dernières reviews reçues par un mentor, avec le reviewer pré-chargé.
     *
     * @return Review[]
     */
    public function findRecentForMentor(User $mentor, int $limit): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('reviewer')
            ->join('r.session', 's')
            ->join('r.reviewer', 'reviewer')
            ->where('s.mentor = :mentor')
            ->setParameter('mentor', $mentor)
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
