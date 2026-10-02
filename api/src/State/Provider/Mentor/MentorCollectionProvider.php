<?php

namespace App\State\Provider\Mentor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Mentor\Mentor;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * GET /mentors : recherche paginée de mentors (filtres skill, category, q, minRating ; tri par note ou nombre de reviews).
 *
 * @implements ProviderInterface<Mentor>
 */
final class MentorCollectionProvider implements ProviderInterface
{
    private const SORTABLE_FIELDS = ['averageRating', 'reviewCount'];

    public function __construct(
        private UserRepository $userRepository,
        private Pagination $pagination,
        private Security $security,
        private MentorMapper $mentorMapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var Request|null $request */
        $request = $context['request'] ?? null;
        $filters = $this->parseFilters($request);
        $order = $this->parseOrder($request);

        $currentUser = $this->security->getUser();
        $exclude = $currentUser instanceof User ? $currentUser : null;

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        $ids = $this->userRepository->findMentorIds($filters, $exclude, $order, $offset, $limit);
        $mentors = array_map(
            $this->mentorMapper->toMentor(...),
            $this->userRepository->findWithSkillsByIds($ids),
        );

        return new TraversablePaginator(
            new \ArrayIterator($mentors),
            $page,
            $limit,
            $this->userRepository->countMentors($filters, $exclude),
        );
    }

    /**
     * @return array{skill?: int, category?: int, q?: string, minRating?: float}
     */
    private function parseFilters(?Request $request): array
    {
        $query = $request?->query;
        $filters = [];

        foreach (['skill', 'category'] as $name) {
            $value = $query?->get($name);
            if ($value === null || $value === '') {
                continue;
            }

            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new BadRequestHttpException(sprintf('"%s" must be a positive integer.', $name));
            }
            $filters[$name] = $id;
        }

        $q = trim((string) $query?->get('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $minRating = $query?->get('minRating');
        if ($minRating !== null && $minRating !== '') {
            $rating = filter_var($minRating, FILTER_VALIDATE_FLOAT);
            if ($rating === false || $rating < 0 || $rating > 5) {
                throw new BadRequestHttpException('"minRating" must be a number between 0 and 5.');
            }
            $filters['minRating'] = $rating;
        }

        return $filters;
    }

    /**
     * @return array<'averageRating'|'reviewCount', 'ASC'|'DESC'>
     */
    private function parseOrder(?Request $request): array
    {
        $order = $request?->query->all()['order'] ?? [];
        if (!\is_array($order)) {
            throw new BadRequestHttpException('"order" must be an object, e.g. order[averageRating]=desc.');
        }

        $parsed = [];
        foreach ($order as $field => $direction) {
            $direction = strtoupper((string) $direction);
            if (!\in_array($field, self::SORTABLE_FIELDS, true) || !\in_array($direction, ['ASC', 'DESC'], true)) {
                throw new BadRequestHttpException(sprintf(
                    'Invalid sort "order[%s]". Allowed fields: %s ; allowed directions: asc, desc.',
                    $field,
                    implode(', ', self::SORTABLE_FIELDS),
                ));
            }
            $parsed[$field] = $direction;
        }

        return $parsed;
    }
}
