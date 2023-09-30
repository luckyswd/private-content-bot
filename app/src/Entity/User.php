<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
class User extends BaseEntity
{
    #[ORM\Column(type: 'bigint', unique: true, nullable: false)]
    private int $telegramId;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist', 'remove'])]
    private ?Subscription $subscription = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Message::class, cascade: ['persist'], fetch: "EAGER")]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function getMessages(): Collection {
        return $this->messages;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Rate $rate):self {
        if ($this->subscription) {
            $subscription = $this->subscription;
        } else {
            $subscription = new Subscription();
            $subscription->setStep(1);
        }
        $subscription->setRate($rate);
        $subscription->setUser($this);
        $subscription->setDate(new DateTimeImmutable());

        $this->subscription = $subscription;

        return $this;
    }

    public function getTelegramId(): int
    {
        return $this->telegramId;
    }

    public function setTelegramId(int $telegramId): self
    {
        $this->telegramId = $telegramId;

        return $this;
    }

    public function hasActiveSubscription(): bool {
        $subscription = $this->subscription;

        if (!$subscription) {
            return false;
        }

        $currentDate = new DateTimeImmutable();

        $subscriptionDate = $subscription?->getDate();

        $rate = $subscription->getRate();
        $subscriptionInterval = $rate->getDuration();
        $endDate = $subscriptionDate->add($subscriptionInterval);

        return ($currentDate > $subscriptionDate) && ($currentDate < $endDate);
    }
}
