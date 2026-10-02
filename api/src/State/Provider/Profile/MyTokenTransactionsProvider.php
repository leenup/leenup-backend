<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyTokenTransactions;
use App\Entity\TokenTransaction;
use App\Entity\User;
use App\Repository\TokenTransactionRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * GET /me/token-transactions : historique paginé des tokens de l'utilisateur connecté.
 *
 * @implements ProviderInterface<MyTokenTransactions>
 */
final class MyTokenTransactionsProvider implements ProviderInterface
{
    public function __construct(
        private TokenTransactionRepository $tokenTransactionRepository,
        private Pagination $pagination,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?TraversablePaginator
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $type = $context['request']?->query->get('type');
        if ($type === '') {
            $type = null;
        }
        if ($type !== null && !\in_array($type, TokenTransaction::TYPES, true)) {
            throw new BadRequestHttpException(sprintf('"type" must be one of: %s.', implode(', ', TokenTransaction::TYPES)));
        }

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        $query = $this->tokenTransactionRepository->createForUserQueryBuilder($user, $type)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();
        $paginator = new Paginator($query, fetchJoinCollection: false);

        $items = [];
        foreach ($paginator as $transaction) {
            $items[] = $this->mapToDto($transaction);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $limit, \count($paginator));
    }

    private function mapToDto(TokenTransaction $transaction): MyTokenTransactions
    {
        $dto = new MyTokenTransactions();
        $dto->id = $transaction->getId();
        $dto->amount = $transaction->getAmount();
        $dto->type = $transaction->getType();
        $dto->balanceAfter = $transaction->getBalanceAfter();
        $dto->createdAt = $transaction->getCreatedAt();

        $session = $transaction->getSession();
        if ($session !== null) {
            $dto->session = '/sessions/'.$session->getId();
            $dto->skillTitle = $session->getSkill()?->getTitle();
        }

        return $dto;
    }
}
