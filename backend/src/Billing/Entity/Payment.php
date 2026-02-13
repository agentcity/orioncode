<?php

namespace App\Billing\Entity;

use App\Billing\Repository\PaymentRepository;
use App\Organization\Entity\Organization;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'billing_payments')]
#[ORM\Index(columns: ['external_id'], name: 'idx_payment_external_id')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_status')]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount; // Сумма пополнения в рублях

    #[ORM\Column(type: 'string', length: 20)]
    private string $status; // 'new', 'pending', 'confirmed', 'rejected'

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalId = null; // Номер транзакции в Т-Банке (PaymentId)

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $bankResponse = null; // Полный лог ответа для разбора спорных ситуаций

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    public function __construct(Organization $organization, float $amount)
    {
        $this->id = Uuid::v4();
        $this->organization = $organization;
        $this->amount = number_format($amount, 2, '.', '');
        $this->status = 'new';
        $this->createdAt = new DateTimeImmutable();
    }

    // Геттеры
    public function getId(): Uuid { return $this->id; }
    public function getOrganization(): Organization { return $this->organization; }
    public function getAmount(): float { return (float)$this->amount; }
    public function getStatus(): string { return $this->status; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function getBankResponse(): ?array { return $this->bankResponse; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getConfirmedAt(): ?DateTimeImmutable { return $this->confirmedAt; }

    // Сеттеры
    public function setStatus(string $status): self
    {
        $this->status = $status;
        if ($status === 'confirmed' && $this->confirmedAt === null) {
            $this->confirmedAt = new DateTimeImmutable();
        }
        return $this;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function setBankResponse(?array $bankResponse): self
    {
        $this->bankResponse = $bankResponse;
        return $this;
    }
}
