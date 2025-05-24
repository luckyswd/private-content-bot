<?php

namespace App\Handler;

use App\Entity\Message;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\ServerResponse;

class TelegramMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    )
    {}

    public function addMessage(
        ServerResponse $response,
        ?int $chatId = null,
    ): void {
        if (!$response->isOk()) {
            return;
        }

        if (is_bool($response->getResult())) {
            return;
        }

        $chatId = $chatId ?: $response->getResult()->getChat()->getId();

        $user = $this->userRepository->findOneBy(['telegramId' => $chatId]);

        if (!$user) {
            return;
        }

        if ($user->getSubscriptions()->isEmpty()) {
            return;
        }

        $message = new Message();
        $message->setUser($user);
        $message->setCreatedAt();
        $message->setMessageId($response->getResult()->getMessageId());

        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }
}