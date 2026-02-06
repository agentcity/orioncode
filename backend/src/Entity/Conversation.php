<?php

namespace App\Entity;

use Ramsey\Uuid\Uuid;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(
    name: 'conversations',
    indexes: [
        new ORM\Index(columns: ['account_id'], name: 'idx_conversation_account'),
        new ORM\Index(columns: ['contact_id'], name: 'idx_conversation_contact'),
        new ORM\Index(columns: ['external_id'], name: 'idx_conversation_external_id'),
        new ORM\Index(columns: ['type'], name: 'idx_conversation_type'),
        new ORM\Index(columns: ['status'], name: 'idx_conversation_status'),
        new ORM\Index(columns: ['last_message_at'], name: 'idx_conversation_last_message'),
        new ORM\Index(columns: ['assigned_to_id'], name: 'idx_conversation_assigned_to'),
        new ORM\Index(columns: ['unread_count'], name: 'idx_conversation_unread_count'),
        new ORM\Index(columns: ['account_id', 'type', 'external_id'], name: 'uniq_conversation_external')
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(columns: ['account_id', 'type', 'external_id'], name: 'uniq_conversation_external')
    ]
)]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['chat', 'conversation:list'])]
    private $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['chat', 'conversation:list'])]
    private ?string $externalId = null;

    #[ORM\Column(length: 50)]
    #[Groups(['chat', 'conversation:list'])]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    #[Groups(['chat', 'conversation:list'])]
    private ?string $status = 'active';

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['chat', 'conversation:list'])]
    private ?\DateTimeImmutable $lastMessageAt = null;

    #[ORM\ManyToOne(inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['chat', 'conversation:list'])]
    private ?Contact $contact = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['chat', 'conversation:list'])]
    private ?User $assignedTo = null;

    // ДОБАВЛЕНО: связь со вторым пользователем системы для внутреннего мессенджера
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['chat', 'conversation:list'])]
    private ?User $targetUser = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['chat', 'conversation:list'])]
    private int $unreadCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->messages = new ArrayCollection();
        $this->lastMessageAt = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();

    }

    #[ORM\PrePersist]
    public function setInitialTimestamps(): void
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updatedTimestamps(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId()
    {
        return $this->id;
    }
    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }
    public function getAccount(): ?Account { return $this->account; }
    public function setAccount(?Account $account): self { $this->account = $account; return $this; }

    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): self { $this->externalId = $externalId; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getLastMessageAt(): ?\DateTimeImmutable { return $this->lastMessageAt; }
    public function setLastMessageAt(\DateTimeImmutable $lastMessageAt): self { $this->lastMessageAt = $lastMessageAt; return $this; }

    public function getContact(): ?Contact { return $this->contact; }
    public function setContact(?Contact $contact): self { $this->contact = $contact; return $this; }

    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $assignedTo): self { $this->assignedTo = $assignedTo; return $this; }

    // ГЕТТЕР И СЕТТЕР ДЛЯ НОВОГО ПОЛЯ
    public function getTargetUser(): ?User { return $this->targetUser; }
    public function setTargetUser(?User $targetUser): self { $this->targetUser = $targetUser; return $this; }

    public function getUnreadCount(): int { return $this->unreadCount; }
    public function setUnreadCount(int $unreadCount): self { $this->unreadCount = $unreadCount; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    // ДОБАВЛЕНО: Метод для красивого имени чата в нашей экосистеме
    #[Groups(['conversation:list', 'chat'])]
    public function getDisplayName(): string
    {
        if ($this->type === 'internal' && $this->targetUser) {
            return $this->targetUser->getFirstName() . ' ' . $this->targetUser->getLastName();
        }
        return $this->contact ? $this->contact->getMainName() : 'Системный чат';
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getConversation() === $this) {
                $message->setConversation(null);
            }
        }
        return $this;
    }
}
