<?php

namespace App\State\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use App\Service\SessionTokenRefunder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rembourse l'étudiant avant de supprimer une session pending.
 *
 * @implements ProcessorInterface<Session, null>
 */
final class SessionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private SessionTokenRefunder $tokenRefunder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Session) {
            $this->tokenRefunder->refund($data);
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
