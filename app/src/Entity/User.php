<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
class User extends BaseEntity
{
    #[ORM\Column(type: 'integer', unique: true, nullable: false)]
    private int $telegramId;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist'], fetch: "EAGER")]

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist', 'remove'])]
    private Subscription $subscription;

    public function getSubscription():Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Rate $rate):self {
        $subscription = new Subscription();
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

    //TODO - проверяет есть ли активная подписка у пользователя: возвращает true|false
    public function hasActiveSubscription():bool {
        $subscription = $this->subscription;
        if (!$subscription) {
            return  false;
        }

        $currentDate = new DateTimeImmutable();

        $subscriptionDate = $subscription?->getDate();

        $rate = $subscription->getRate();
        $subscriptionInterval = $rate->getDuration();
        $endDate = $subscriptionDate->add($subscriptionInterval);

        return ($currentDate > $subscriptionDate) && ($currentDate < $endDate);
    }
}
