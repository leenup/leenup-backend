<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MySessionsAsStudent;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\SessionRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MySessionsAsStudent>
 */
final class MySessionsAsStudentProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private SessionRepository $sessionRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $sessions = $this->sessionRepository->findBy(
            ['student' => $user],
            ['scheduledAt' => 'DESC']
        );

        return array_map(fn(Session $session) => $this->mapToDto($session), $sessions);
    }

    private function mapToDto(Session $session): MySessionsAsStudent
    {
        $dto = new MySessionsAsStudent();
        $dto->id = $session->getId();
        $dto->mentor = $session->getMentor();
        $dto->skill = $session->getSkill();
        $dto->status = $session->getStatus();
        $dto->scheduledAt = $session->getScheduledAt();
        $dto->duration = $session->getDuration();
        $dto->location = $session->getLocation();
        $dto->notes = $session->getNotes();
        $dto->createdAt = $session->getCreatedAt();

        return $dto;
    }
}
