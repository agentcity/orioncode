<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: \App\Repository\AttachmentRepository::class)]
#[ORM\Table(name: 'attachments')]
// Выносим индекс в отдельный атрибут:
#[ORM\Index(columns: ['message_id'], name: 'idx_attachment_message')]
#[ORM\HasLifecycleCallbacks]
class Attachment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName;

    #[ORM\Column(type: 'integer')]
    private int $fileSize;

    #[ORM\Column(type: 'string', length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'string')]
    private string $url;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): self
    {
        $this->message = $message;
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

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
