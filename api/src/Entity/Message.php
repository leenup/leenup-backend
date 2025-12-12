<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\MessageRepository;
use App\Security\Voter\MessageVoter;
use App\State\Processor\Message\MessageCreateProcessor;
use App\State\Processor\Message\MessageUpdateProcessor;
use App\State\Provider\Conversation\ConversationMessagesProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/conversations/{conversationId}/messages',
            uriVariables: [
                'conversationId' => new Link(
                    fromProperty: 'messages',
                    fromClass: Conversation::class
                )
            ],
            security: "is_granted('ROLE_USER')",
            provider: ConversationMessagesProvider::class,
        ),
        new Get(
            security: "is_granted('" . MessageVoter::VIEW . "', object)"
        ),
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Post(
            securityPostDenormalize: "is_granted('" . MessageVoter::CREATE . "', object)",
            validationContext: ['groups' => ['Default', 'message:create']],
            processor: MessageCreateProcessor::class,
        ),
        new Patch(
            denormalizationContext: ['groups' => ['message:update']],
            security: "is_granted('" . MessageVoter::UPDATE . "', object) or is_granted('" . MessageVoter::MARK_READ . "', object)",
            processor: MessageUpdateProcessor::class,
        ),
        new Delete(
            security: "is_granted('" . MessageVoter::DELETE . "', object)"
        ),
    ],
    normalizationContext: ['groups' => ['message:read']],
    denormalizationContext: ['groups' => ['message:create', 'message:update']],
)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['message:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'The conversation cannot be null')]
    #[Groups(['message:read', 'message:create'])]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['message:read'])]
    private ?User $sender = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'The message content cannot be empty')]
    #[Assert\Length(min: 1, max: 5000, maxMessage: 'Message cannot be longer than {{ limit }} characters')]
    #[Groups(['message:read', 'message:create', 'message:update'])]
    private ?string $content = null;

    #[ORM\Column]
    #[Groups(['message:read', 'message:update'])]
    private ?bool $read = false;

    #[ORM\Column]
    #[Groups(['message:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->read;
    }

    public function setRead(bool $read): static
    {
        $this->read = $read;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
