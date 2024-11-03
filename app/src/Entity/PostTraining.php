<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class PostTraining extends BaseEntity
{
    #[ORM\Column(type: 'integer', nullable: false)]
    private int $messageId = 0;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $botName = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $algorithmNumber = 1;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $postOrder = 1;

    #[ORM\ManyToOne(targetEntity: TrainingCatalog::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'training_catalog_id', referencedColumnName: 'id', nullable: false)]
    private TrainingCatalog $catalog;

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getBotName(): ?string
    {
        return $this->botName;
    }

    public function setBotName(?string $botName): self
    {
        $this->botName = $botName;

        return $this;
    }

    public function getAlgorithmNumber(): int
    {
        return $this->algorithmNumber;
    }

    public function setAlgorithmNumber(int $algorithmNumber): self
    {
        $this->algorithmNumber = $algorithmNumber;

        return $this;
    }

    public function getCatalog(): TrainingCatalog
    {
        return $this->catalog;
    }

    public function setCatalog(TrainingCatalog $catalog): self
    {
        $this->catalog = $catalog;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;

        return $this;
    }
}
