<?php

namespace App\Service;

use App\Entity\Post;
use App\Handler\TelegramValidationHandler;
use App\Repository\PostRepository;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramService
{
    private ?Telegram $telegram = null;
    public function __construct(
        private PostRepository $postRepository,
    )
    {}
    public function setWebhook(): string|null {
        try {
            $result = $this->getTelegram()->setWebhook(getenv('HOOK_URL'));

            if ($result->isOk()) {
                return $result->getDescription();
            }

            return null;
        } catch (TelegramException $e) {
            return $e->getMessage();
        }
    }

    public function deleteWebhook(): string {
        try {
            $result = $this->getTelegram()->deleteWebhook();

            return $result->getDescription();
        } catch (TelegramException $e) {
            return $e->getMessage();
        }
    }

    public function getTelegram(): Telegram {
        if (!$this->telegram) {
            $this->telegram = new Telegram(getenv('BOT_TOKEN'), getenv('BOT_USERNAME'));
        }

        return $this->telegram;
    }

    public static function getUpdate(): Update {
        $input = Request::getInput();
        $post = json_decode($input, true);

        return new Update($post, getenv('BOT_USERNAME'));
    }

    public function getPostById(
        int $orderNumber,
    ): ?Post {
        $posts = $this->postRepository->findBy(['botName' => TelegramService::getUpdate()->getBotUsername()]);

        foreach ($posts as $key => $post) {
            if (($key + 1) === $orderNumber) {
                return $post;
            }
        }

        return null;
    }

    public function forwardMessage(
        int $orderNumber,
        int $groupIdFrom,
        string $chatIdTo,
        bool $isLast = true,
    ): void {
        $post = $this->getPostById($orderNumber);

        if (!$post || !$groupIdFrom || !$chatIdTo) {
            return;
        }

        Request::copyMessage([
            'chat_id' => $chatIdTo,
            'from_chat_id' => $groupIdFrom,
            'message_id' => $post->getMessageId() ?? '',
            'protect_content' => true,
            'reply_markup' => $isLast ? json_encode(TelegramValidationHandler::getMenuButtons()) : '',
        ]);
    }

    public function getCountAllPostByBotName(
        string $botName,
    ): int {
        $posts = $this->postRepository->findBy(['botName' => $botName]);

        return count($posts);
    }
}