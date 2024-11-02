<?php

namespace App\Entity;

use App\Enum\SubscriptionType;
use App\Repository\RateRepository;
use DateInterval;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Rate extends BaseEntity
{
    #[ORM\Column(type: 'string', length: '50', nullable: false)]
    private string $name;

    #[ORM\Column(type: 'dateinterval', nullable: false)]
    private DateInterval $duration;

    #[ORM\OneToMany(mappedBy: 'rate', targetEntity: Price::class, cascade: ['persist'], fetch: "EAGER")]
    private Collection $prices;

    #[ORM\Column(type: 'integer', enumType: SubscriptionType::class)]
    private SubscriptionType $subscriptionType;

    public function __construct()
    {
        $this->prices = new ArrayCollection();
    }

    public function addPrice(
        Price $price,
    ):void {
        if ($this->prices->contains($price)) {
            return;
        }
        $price->setRate($this);
        $this->prices->add($price);
    }

    public function getPrices(): Collection
    {
        return $this->prices;
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

    public function getDuration(): DateInterval
    {
        return $this->duration;
    }

    public function setDuration(DateInterval $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getSubscriptionType(): SubscriptionType
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(SubscriptionType $subscriptionType): self
    {
        $this->subscriptionType = $subscriptionType;

        return $this;
    }
}
