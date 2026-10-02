<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\UserSkill;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->save($user, true);
    }

    /**
     * Ids des mentors correspondant aux filtres, triés et paginés.
     * Un mentor = un utilisateur actif qui enseigne au moins une compétence (on ne se fie pas au flag isMentor).
     *
     * @param array{skill?: int, category?: int, q?: string, minRating?: float} $filters
     * @param array<'averageRating'|'reviewCount', 'ASC'|'DESC'>                $order
     *
     * @return int[]
     */
    public function findMentorIds(array $filters, ?User $exclude, array $order, int $offset, int $limit): array
    {
        $qb = $this->createMentorSearchQueryBuilder($filters, $exclude)
            ->select('u.id AS id')
            // Les mentors sans note passent toujours en dernier, quel que soit le sens du tri.
            ->addSelect('CASE WHEN u.averageRating IS NULL THEN 1 ELSE 0 END AS HIDDEN ratingIsNull')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($order === []) {
            $order = ['averageRating' => 'DESC', 'reviewCount' => 'DESC'];
        }

        foreach ($order as $field => $direction) {
            if ($field === 'averageRating') {
                $qb->addOrderBy('ratingIsNull', 'ASC');
            }
            $qb->addOrderBy('u.'.$field, $direction);
        }
        $qb->addOrderBy('u.id', 'ASC');

        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'id'));
    }

    /**
     * @param array{skill?: int, category?: int, q?: string, minRating?: float} $filters
     */
    public function countMentors(array $filters, ?User $exclude): int
    {
        return (int) $this->createMentorSearchQueryBuilder($filters, $exclude)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneMentor(int $id): ?User
    {
        $isMentor = (bool) $this->createMentorSearchQueryBuilder([], null)
            ->select('COUNT(u.id)')
            ->andWhere('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();

        return $isMentor ? ($this->findWithSkillsByIds([$id])[0] ?? null) : null;
    }

    /**
     * Charge les utilisateurs avec leurs compétences (skill + catégorie) en une seule requête,
     * dans l'ordre des ids fournis.
     *
     * @param int[] $ids
     *
     * @return User[]
     */
    public function findWithSkillsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $users = $this->createQueryBuilder('u')
            ->addSelect('us', 's', 'c')
            ->leftJoin('u.userSkills', 'us')
            ->leftJoin('us.skill', 's')
            ->leftJoin('s.category', 'c')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($users as $user) {
            $byId[$user->getId()] = $user;
        }

        return array_values(array_filter(array_map(static fn (int $id) => $byId[$id] ?? null, $ids)));
    }

    /**
     * @param array{skill?: int, category?: int, q?: string, minRating?: float} $filters
     */
    private function createMentorSearchQueryBuilder(array $filters, ?User $exclude): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->setParameter('teachType', UserSkill::TYPE_TEACH);

        // Le mentor doit enseigner au moins une compétence (correspondant au filtre skill/category s'il y en a un).
        $teach = $this->getEntityManager()->createQueryBuilder()
            ->select('ts.id')
            ->from(UserSkill::class, 'ts')
            ->join('ts.skill', 'tsk')
            ->where('ts.owner = u')
            ->andWhere('ts.type = :teachType');

        if (isset($filters['skill'])) {
            $teach->andWhere('tsk.id = :skillId');
            $qb->setParameter('skillId', $filters['skill']);
        }

        if (isset($filters['category'])) {
            $teach->andWhere('IDENTITY(tsk.category) = :categoryId');
            $qb->setParameter('categoryId', $filters['category']);
        }

        $qb->andWhere($qb->expr()->exists($teach->getDQL()));

        if (isset($filters['q'])) {
            $searchedSkill = $this->getEntityManager()->createQueryBuilder()
                ->select('qs.id')
                ->from(UserSkill::class, 'qs')
                ->join('qs.skill', 'qsk')
                ->where('qs.owner = u')
                ->andWhere('qs.type = :teachType')
                ->andWhere('LOWER(qsk.title) LIKE :q');

            $qb->andWhere($qb->expr()->orX(
                "LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE :q",
                $qb->expr()->exists($searchedSkill->getDQL()),
            ));
            $qb->setParameter('q', '%'.addcslashes(mb_strtolower($filters['q']), '%_\\').'%');
        }

        if (isset($filters['minRating'])) {
            $qb->andWhere('u.averageRating >= :minRating')
                ->setParameter('minRating', (string) $filters['minRating']);
        }

        if ($exclude !== null) {
            $qb->andWhere('u != :exclude')
                ->setParameter('exclude', $exclude);
        }

        return $qb;
    }
}
