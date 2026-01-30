<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Enum\ProfilesEnum;
use App\State\Processor\Profile\MyProfilesAddProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MyProfile',
    operations: [
        new Post(
            uriTemplate: '/me/addProfiles',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: MyProfilesAddProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_profile:read']],
    denormalizationContext: ['groups' => ['my_profile:write']],
)]
class MyProfiles
{
    #[Assert\NotBlank(message: 'The profile cannot be blank')]
    #[Assert\Choice(
        choices: [ProfilesEnum::MENTOR->value, ProfilesEnum::STUDENT->value],
        message: 'Profile must be student or/and mentor.',
    )]
    #[Groups(['my_profile:write'])]
    public ?string $profile = null;

    /**
     * @var string[]
     */
    #[Groups(['my_profile:read'])]
    public array $profiles = [];
}
