<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Subscription extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: User::class, cascade: ["persist"], inversedBy: 'subscriptions')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Rate::class, cascade: ["persist"])]
    #[ORM\JoinColumn(name: 'rate', nullable: false)]
    private Rate $rate;

    #[ORM\Column(type: 'integer', nullable: false)]
    private string $step;

    #[ORM\Column(name: 'date', type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $date;

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(
        DateTimeImmutable $date
    ): self
    {
        $this->date = $date;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getRate(): Rate
    {
        return $this->rate;
    }

    public function setRate(Rate $rate): self
    {
        $this->rate = $rate;

        return $this;
    }

    public function getAllowedCountPost():int {
        $difference = $this->date->diff
            (new DateTime()
        );

        return $difference->days;
    }

    public function getStep(): string
    {
        return $this->step;
    }

    public function setStep(string $step): self
    {
        $this->step = $step;

        return $this;
    }
}
