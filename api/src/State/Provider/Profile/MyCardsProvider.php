<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyCards;
use App\Entity\Card;
use App\Entity\User;
use App\Entity\UserCard;
use App\Repository\UserCardRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MyCards>
 */
final class MyCardsProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UserCardRepository $userCardRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        // Méthode custom dans UserCardRepository (voir plus bas)
        $userCards = $this->userCardRepository->findUserCards($user);

        return array_map(
            fn (UserCard $userCard) => $this->mapToDto($userCard),
            $userCards
        );
    }

    private function mapToDto(UserCard $userCard): MyCards
    {
        $dto = new MyCards();
        $dto->id = $userCard->getId();

        $card = $userCard->getCard();
        if (!$card instanceof Card) {
            return $dto;
        }

        // Infos de la Card
        $dto->cardId = $card->getId();
        $dto->code = $card->getCode();
        $dto->family = $card->getFamily();
        $dto->title = $card->getTitle();
        $dto->subtitle = $card->getSubtitle();
        $dto->description = $card->getDescription();
        $dto->category = $card->getCategory();
        $dto->level = $card->getLevel();
        $dto->imageUrl = $card->getImageUrl();
        $dto->conditions = $card->getConditions();
        $dto->isActive = $card->isActive();

        // Infos de la relation user ↔ card
        $dto->obtainedAt = $userCard->getObtainedAt();
        $dto->seenAt = $userCard->getSeenAt();
        $dto->source = $userCard->getSource();
        $dto->meta = $userCard->getMeta();

        return $dto;
    }
}
