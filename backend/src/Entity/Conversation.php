<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

#[ORM\Entity]
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
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Contact::class, inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Contact $contact;

    #[ORM\Column(type: 'string', length: 255)]
    private string $externalId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastMessageAt = null;

    #[ORM\Column(type: 'integer')]
    private int $unreadCount = 0;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, cascade: ['persist', 'remove'])]
    private Collection $messages;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;
        return $this;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): self
    {
        $this->contact = $contact;
        return $this;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getLastMessageAt(): ?DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function setLastMessageAt(?DateTimeImmutable $lastMessageAt): self
    {
        $this->lastMessageAt = $lastMessageAt;
        return $this;
    }

    public function getUnreadCount(): int
    {
        return $this->unreadCount;
    }

    public function setUnreadCount(int $unreadCount): self
    {
        $this->unreadCount = $unreadCount;
        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?User $assignedTo): self
    {
        $this->assignedTo = $assignedTo;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

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

    #[ORM\PreUpdate]
    public function updatedTimestamps(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
