<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
#[ORM\HasLifecycleCallbacks]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\Column(type: 'string', length: 20)]
    private string $senderType;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $senderId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'string', length: 20)]
    private string $direction;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $sentAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
    }

    // === Getters and Setters ===

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function setSenderType(string $senderType): self
    {
        $this->senderType = $senderType;
        return $this;
    }

    public function getSenderId(): ?UuidInterface
    {
        return $this->senderId;
    }

    public function setSenderId(?UuidInterface $senderId): self
    {
        $this->senderId = $senderId;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    // === Lifecycle Callbacks ===

    #[ORM\PreUpdate]
    public function updatedTimestamp(): void
    {
        // Обновляем только `updatedAt`, если понадобится в будущем
    }
}
