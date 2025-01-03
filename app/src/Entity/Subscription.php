<?php

namespace App\Entity;

use App\Enum\SubscriptionType;
use App\Repository\SubscriptionRepository;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: 'user_type',
    columns: ['user_id', 'type']
)]
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

    #[ORM\Column(type: 'integer', enumType: SubscriptionType::class)]
    private SubscriptionType $type;

    #[ORM\OneToMany(mappedBy: 'subscription', targetEntity: TrainingCatalogSubscription::class)]
    private Collection $trainingCatalogSubscriptions;

    public function __construct()
    {
        $this->trainingCatalogSubscriptions = new ArrayCollection();
    }

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

    public function getType(): SubscriptionType
    {
        return $this->type;
    }

    public function setType(SubscriptionType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTrainingCatalogSubscriptionByCatalog(TrainingCatalog $catalog): ?TrainingCatalogSubscription
    {
        /** @var TrainingCatalogSubscription $trainingCatalogSubscription */
        foreach ($this->trainingCatalogSubscriptions as $trainingCatalogSubscription) {
            if ($trainingCatalogSubscription->getTrainingCatalog() === $catalog) {
                return $trainingCatalogSubscription;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, TrainingCatalogSubscription>
     */
    public function getTrainingCatalogSubscriptions(): Collection
    {
        return $this->trainingCatalogSubscriptions;
    }

    public function addTrainingCatalogSubscription(TrainingCatalogSubscription $trainingCatalogSubscription): static
    {
        if (!$this->trainingCatalogSubscriptions->contains($trainingCatalogSubscription)) {
            $this->trainingCatalogSubscriptions->add($trainingCatalogSubscription);
            $trainingCatalogSubscription->setSubscription($this);
        }

        return $this;
    }

    public function removeTrainingCatalogSubscription(TrainingCatalogSubscription $trainingCatalogSubscription): static
    {
        if ($this->trainingCatalogSubscriptions->removeElement($trainingCatalogSubscription)) {
            if ($trainingCatalogSubscription->getSubscription() === $this) {
                $trainingCatalogSubscription->setSubscription(null);
            }
        }

        return $this;
    }
}
