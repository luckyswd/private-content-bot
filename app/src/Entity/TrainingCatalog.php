<?php

namespace App\Entity;

use App\Enum\SubscriptionType;
use App\Repository\TrainingCatalogRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingCatalogRepository::class)]
class TrainingCatalog extends BaseEntity
{
    const FULL_BODY = 'FULL_BODY';
    const UPPER_BODY = 'UPPER_BODY';
    const LOWER_BODY = 'LOWER_BODY';
    const GLUTES_LEGS_SHOULDERS = 'GLUTES_LEGS_SHOULDERS';
    const CHEST_ARMS = 'CHEST_ARMS';
    const BACK_GLUTES = 'BACK_GLUTES';

    const MAPPING = [
        self::FULL_BODY => 'На всё тело',
        self::UPPER_BODY => 'На верх тела',
        self::LOWER_BODY => 'На низ тела',
        self::GLUTES_LEGS_SHOULDERS => 'Ягодицы + ноги + плечи',
        self::CHEST_ARMS => 'Грудь + руки',
        self::BACK_GLUTES => 'Спина + ягодицы',
    ];

    #[ORM\Column(type: 'string', nullable: false)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: TrainingCatalog::class)]
    #[ORM\JoinColumn(name: 'sub_catalog_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TrainingCatalog $subCatalog = null;

    #[ORM\Column(type: 'integer', nullable: true, enumType: SubscriptionType::class)]
    private ?SubscriptionType $subscriptionType;

    #[ORM\OneToMany(mappedBy: 'catalog', targetEntity: PostTraining::class, cascade: ['persist'], fetch: "EAGER")]
    private Collection $posts;

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

    public function getPosts(): Collection
    {
        return $this->posts;
    }
}
