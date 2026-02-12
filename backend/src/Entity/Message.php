<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: \App\Repository\MessageRepository::class)]
#[ORM\Table(name: 'messages')]
// Выносим каждый индекс в свой атрибут:
#[ORM\Index(columns: ['conversation_id'], name: 'idx_message_conversation')]
#[ORM\Index(columns: ['sender_type'], name: 'idx_message_sender_type')]
#[ORM\Index(columns: ['manager_id'], name: 'idx_message_manager')]
#[ORM\Index(columns: ['contact_id'], name: 'idx_message_contact')]
#[ORM\Index(columns: ['direction'], name: 'idx_message_direction')]
#[ORM\Index(columns: ['sent_at'], name: 'idx_message_sent_at')]
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

    // 1. Связь с Менеджером (User) - заполняется, когда пишем МЫ
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "manager_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?User $manager = null;

    // 2. Связь с Клиентом (Contact) - заполняется, когда пишут НАМ (из ТГ/ВК)
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(name: "contact_id", referencedColumnName: "id", nullable: true, onDelete: "CASCADE")]
    private ?Contact $contact = null;


    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['chat'])]
    private ?array $payload = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'string', length: 20)]
    private string $direction; // incoming, outgoing

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'sent'])]
    private string $status = 'sent'; // sent, delivered, read, replied, failed

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $sentAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'message', targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: "reply_to_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?Message $replyTo = null;


    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->attachments = new ArrayCollection();
    }

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

    // --- Поле Manager (User) ---
    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    // --- Поле Contact ---
    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setMessage($this);
        }
        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachments->removeElement($attachment)) {
            if ($attachment->getMessage() === $this) {
                $attachment->setMessage(null);
            }
        }
        return $this;
    }
    public function getReplyTo(): ?self { return $this->replyTo; }

    public function setReplyTo(?self $replyTo): self
    {
        $this->replyTo = $replyTo;
        return $this;
    }
}
