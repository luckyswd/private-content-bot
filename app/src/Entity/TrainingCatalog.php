<?php

namespace App\Entity;

use App\Enum\SubscriptionType;
use App\Repository\TrainingCatalogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingCatalogRepository::class)]
class TrainingCatalog extends BaseEntity
{
    const FULL_BODY = 'FULL_BODY';
    const UPPER_BODY = 'UPPER_BODY';
    const LOWER_BODY = 'LOWER_BODY';

    const MAPPING = [
        self::FULL_BODY => 'На всё тело',
        self::UPPER_BODY => 'На верх тела',
        self::LOWER_BODY => 'На низ тела',
    ];

    #[ORM\Column(type: 'string', nullable: false)]
    private string $name;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxAlgorithmCount = null;

    #[ORM\ManyToOne(targetEntity: TrainingCatalog::class)]
    #[ORM\JoinColumn(name: 'sub_catalog_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TrainingCatalog $subCatalog = null;

    #[ORM\Column(type: 'integer', nullable: true, enumType: SubscriptionType::class)]
    private ?SubscriptionType $subscriptionType;

    #[ORM\OneToMany(mappedBy: 'catalog', targetEntity: PostTraining::class, cascade: ['persist'], fetch: "EAGER")]
    private Collection $posts;

    #[ORM\OneToMany(mappedBy: 'trainingCatalog', targetEntity: TrainingCatalogSubscription::class)]
    private Collection $trainingCatalogSubscriptions;

    public function __construct()
    {
        $this->trainingCatalogSubscriptions = new ArrayCollection();
    }

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

    public function getMaxAlgorithmCount(): ?int
    {
        return $this->maxAlgorithmCount;
    }

    public function setMaxAlgorithmCount(?int $maxAlgorithmCount): self
    {
        $this->maxAlgorithmCount = $maxAlgorithmCount;

        return $this;
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
            $trainingCatalogSubscription->setTrainingCatalog($this);
        }

        return $this;
    }

    public function removeTrainingCatalogSubscription(TrainingCatalogSubscription $trainingCatalogSubscription): static
    {
        if ($this->trainingCatalogSubscriptions->removeElement($trainingCatalogSubscription)) {
            // set the owning side to null (unless already changed)
            if ($trainingCatalogSubscription->getTrainingCatalog() === $this) {
                $trainingCatalogSubscription->setTrainingCatalog(null);
            }
        }

        return $this;
    }
}
