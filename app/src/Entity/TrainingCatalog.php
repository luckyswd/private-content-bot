<?php

namespace App\Entity;

use App\Enum\SubscriptionType;
use App\Repository\TrainingCatalogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingCatalogRepository::class)]
class TrainingCatalog extends BaseEntity
{
    #[ORM\Column(type: 'string', nullable: false)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: TrainingCatalog::class)]
    #[ORM\JoinColumn(name: 'sub_catalog_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TrainingCatalog $subCatalog = null;

    #[ORM\Column(type: 'integer', nullable: true, enumType: SubscriptionType::class)]
    private ?SubscriptionType $subscriptionType;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setSubCatalog(?TrainingCatalog $subCatalog): self
    {
        $this->subCatalog = $subCatalog;

        return $this;
    }

    public function getSubCatalog(): ?TrainingCatalog
    {
        return $this->subCatalog;
    }

    public function getParentCategory(): ?string
    {
        return $this->subCatalog?->getName();
    }

    public function getSubscriptionType(): ?SubscriptionType
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(?SubscriptionType $subscriptionType): self
    {
        $this->subscriptionType = $subscriptionType;

        return $this;
    }
}
