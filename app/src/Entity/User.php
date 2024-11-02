<?php

namespace App\Entity;

use App\Enum\SubscriptionType;
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

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist', 'remove'])]
    private Collection $subscriptions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Message::class, cascade: ['persist'], fetch: "EAGER")]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
    }

    public function getMessages(): Collection {
        return $this->messages;
    }

    public function addSubscription(Rate $rate): self {
        $subscription = $this->getSubscriptionByType($rate->getSubscriptionType());

        if (!$subscription) {
            $subscription = new Subscription();
        }

        $subscription->setStep(1);
        $subscription->setRate($rate);
        $subscription->setUser($this);
        $subscription->setDate(new DateTimeImmutable());
        $subscription->setType($rate->getSubscriptionType());

        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions[] = $subscription;
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): void
    {
        if ($this->subscriptions->contains($subscription)) {
            $this->subscriptions->removeElement($subscription);
        }
    }

    public function getSubscriptionByType(SubscriptionType $type = SubscriptionType::CHARGERS): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->getType() === $type) {
                return $subscription;
            }
        }

        return null;
    }

    public function getSubscriptions(): ArrayCollection|Collection
    {
        return $this->subscriptions;
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

    public function hasActiveSubscription(SubscriptionType $type = SubscriptionType::CHARGERS): bool {
        $subscription = $this->getSubscriptionByType($type);

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

    public function getDescription(): string
    {
        return match($this->getSubscriptionByType()) {
            SubscriptionType::CHARGERS => 'Зарядки',
            SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT => 'Тренировки для дома без оборудования',
            SubscriptionType::TRAINING_HOME_WITH_ELASTIC => 'Тренировки для дома с эспандером',
            SubscriptionType::TRAINING_FOR_GYM => 'Тренировки для зала',
        };
    }
}
