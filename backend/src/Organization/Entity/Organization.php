<?php

namespace App\Organization\Entity;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
class Organization
{
    #[ORM\Id, ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Account::class)]
    private Collection $accounts;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'organizations')]
    #[ORM\JoinTable(name: 'organization_users')]
    private Collection $users;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private string $balance = '0.00';

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'trial'])]
    private string $subscriptionPlan = 'trial'; // 'trial', 'business', 'enterprise'

    public function __construct() {
        $this->id = Uuid::v4();
        $this->accounts = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, Account> */
    public function getAccounts(): Collection { return $this->accounts; }

    /** @return Collection<int, User> */
    public function getUsers(): Collection { return $this->users; }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addOrganization($this);
        }
        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            $user->removeOrganization($this);
        }
        return $this;
    }

    public function getBalance(): string { return $this->balance; }

    public function setBalance(string $balance): self { $this->balance = $balance; return $this; }

    public function getSubscriptionPlan(): string { return $this->subscriptionPlan; }

    public function setSubscriptionPlan(string $subscriptionPlan): self { $this->subscriptionPlan = $subscriptionPlan; return $this; }
}
