<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use DateInterval;
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
    private int $step = 0;

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

    public function getStep(): int
    {
        return $this->step;
    }

    public function setStep(int $step): void
    {
        $this->step = $step;
    }

    public function getAllowedCountPost():int {
        $difference = $this->date->diff
        (new DateTime()
        );

        return ($difference->days + 1);
    }

    public function getLeftDateString():string {
        $subscriptionInterval = $this->getRate()->getDuration();
        $endDate = $this->date->add($subscriptionInterval);

        return $endDate->format('d.m.Y H:i');
    }

    public function getNextDate(): string {
        $duration = sprintf('P%sD', $this->getAllowedCountPost());

        return $this->date->add(new DateInterval($duration))->format('d.m.Y H:i');
    }
}
