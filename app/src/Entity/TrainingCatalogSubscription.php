<?php

namespace App\Entity;

use App\Repository\TrainingCatalogSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingCatalogSubscriptionRepository::class)]
class TrainingCatalogSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $step = 1;

    #[ORM\ManyToOne(inversedBy: 'trainingCatalogSubscriptions')]
    private ?Subscription $subscription = null;

    #[ORM\ManyToOne(inversedBy: 'trainingCatalogSubscriptions')]
    private ?TrainingCatalog $trainingCatalog = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getTrainingCatalog(): ?TrainingCatalog
    {
        return $this->trainingCatalog;
    }

    public function setTrainingCatalog(?TrainingCatalog $trainingCatalog): static
    {
        $this->trainingCatalog = $trainingCatalog;

        return $this;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function setStep(int $step): static
    {
        $this->step = $step;

        return $this;
    }
}
