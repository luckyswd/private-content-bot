<?php

namespace App\Entity;

use App\Repository\MethodRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MethodRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Course extends BaseEntity
{
    #[ORM\Column(type: 'string', length: '180', nullable: false)]
    private string $name;

    #[ORM\Column(type: 'string', length: '200', nullable: false)]
    private string $groupId;

    public function __construct(
        string $name,
        string $groupId,
    )
    {
        $this->name = $name;
        $this->groupId = $groupId;
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

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(
        string $groupId,
    ): self
    {
        $this->groupId = $groupId;

        return $this;
    }
}
