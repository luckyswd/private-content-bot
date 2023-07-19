<?php

namespace App\Entity;

use App\Repository\PriceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Price extends BaseEntity
{
    public const RUB_CURRENCY = 'RUB';
    public const EUR_CURRENCY = 'EUR';
    public const USD_CURRENCY = 'USD';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $price;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $currency;

    #[ORM\ManyToOne(targetEntity: Rate::class, cascade: ["persist"])]
    #[ORM\JoinColumn(name: 'rate', nullable: false)]
    private Rate $rate;

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

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
}
