<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Message::class)]
class Message extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: User::class, cascade: ["persist"], inversedBy: 'messages')]
    private User $user;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $messageId = null;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function setMessageId(?int $messageId): void
    {
        $this->messageId = $messageId;
    }
}