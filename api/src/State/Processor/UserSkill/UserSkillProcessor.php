<?php

namespace App\State\Processor\UserSkill;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Entity\UserSkill;

class UserSkillProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $processor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof UserSkill) {
            $owner = $data->getOwner();

            if ($owner instanceof User
                && $data->getType() === UserSkill::TYPE_TEACH
                && !$owner->getIsMentor()
            ) {
                $owner->setIsMentor(true);
            }
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
