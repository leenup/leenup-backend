<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use App\State\UserPasswordHasher;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: 'Only admins can list users.'
        ),
        new Get(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: 'Only admins can view user details.'
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: "Only admins can update users.",
            securityPostDenormalize: "!('ROLE_ADMIN' in previous_object.getRoles())",
            securityPostDenormalizeMessage: "Admins cannot modify admin users (including themselves).",
            processor: UserPasswordHasher::class
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: "Only admins can delete users.",
            securityPostDenormalize: "!('ROLE_ADMIN' in previous_object.getRoles())",
            securityPostDenormalizeMessage: "Admins cannot delete admin users (including themselves).",
        ),
        new Post(
            uriTemplate: '/register',
            security: "is_granted('PUBLIC_ACCESS')",
            securityPostDenormalize: "is_granted('ROLE_ADMIN') or !object.hasRole('ROLE_ADMIN')",
            securityPostDenormalizeMessage: "Only admins can assign admin roles.",
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserPasswordHasher::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:create', 'user:update:admin']],
    security: "is_granted('ROLE_ADMIN')",
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:create', 'user:update', 'user:update:admin'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:read', 'user:create', 'user:update:admin'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private ?string $password = null;

    #[Assert\NotBlank(groups: ['user:create'])]
    #[Groups(['user:create', 'user:update:admin'])]
    private ?string $plainPassword = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // === Lifecycle Callbacks ===

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // === Getters / Setters ===

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
