<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: \App\Repository\ContactRepository::class)]
#[ORM\Table(name: 'contacts')]
// Выносим индекс в отдельный атрибут под таблицей
#[ORM\Index(columns: ['main_name'], name: 'idx_contact_main_name')]
#[ORM\HasLifecycleCallbacks]
class Contact
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $mainName;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $source = null; // 'telegram', 'whatsapp'

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalId = null; // ID пользователя в мессенджере

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Account $account = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'contact', targetEntity: Conversation::class, cascade: ['remove'])]
    private Collection $conversations;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isOnline = false; // Добавь "= false" здесь

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->conversations = new ArrayCollection();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getMainName(): string
    {
        return $this->mainName;
    }

    public function setMainName(string $mainName): self
    {
        $this->mainName = $mainName;
        return $this;
    }

    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $source): self { $this->source = $source; return $this; }

    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): self { $this->externalId = $externalId; return $this; }

    public function getAccount(): ?Account { return $this->account; }
    public function setAccount(?Account $account): self { $this->account = $account; return $this; }


    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;
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
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    #[ORM\PreUpdate]
    public function updatedTimestamps(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
