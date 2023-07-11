<?php

namespace App\Entity;

use App\Repository\RateRepository;
use DateInterval;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Rate extends BaseEntity
{
    #[ORM\Column(type: 'string', length: '50', unique: true, nullable: false)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: false)]
    private string $price;

    #[ORM\Column(type: 'dateinterval', nullable: false)]
    private DateInterval $duration;

    public function __construct()
    {
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

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

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
}
