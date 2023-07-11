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
    #[ORM\Column(type: 'integer', unique: true, nullable: false)]
    private int $telegramId;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist'], fetch: "EAGER")]
    private Collection $subscriptions;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
    }

    public function getSubscriptions(): ArrayCollection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription):self {
        $subscription->setUser($this);
        if ($this->subscriptions->contains($subscription)) {
            return $this;
        }

        $this->subscriptions->add($subscription);

        return $this;
    }

    public function removeSubscription(Subscription $subscription): bool
    {
        return $this->subscriptions->removeElement($subscription);
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

    public function getActiveSubscriptions():ArrayCollection {
        return $this->subscriptions->filter(function (Subscription $subscription) {
            $currentDate = new DateTimeImmutable();
            $subscriptionDate = $subscription->getDate();

            $rate = $subscription->getRate();
            $subscriptionInterval = $rate->getDuration();

            $endDate = $subscriptionDate->add($subscriptionInterval);

            return ($currentDate > $subscriptionDate) && ($currentDate < $endDate);
        });
    }
}
