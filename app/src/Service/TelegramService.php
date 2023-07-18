<?php

namespace App\Service;

use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use GuzzleHttp\Client;
use Longman\TelegramBot\Telegram;

class TelegramService
{
    private ?Telegram $telegram = null;

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

    public function getPostId(
        int $orderNumber,
    ): int|null {
        $allPosts = $this->getAllPosts();
        foreach ($allPosts as $key => $post) {
            if (++$key == $orderNumber) {
                return $post['message']['message_id'];
            }
        }

        return null;
    }

    private function getAllPosts(): array {
        $botToken = getenv('ADMIN_BOT_TOKEN');
        $client = new Client([
            'base_uri' => 'https://api.telegram.org/bot' . $botToken . '/',
        ]);
        $response = $client->get('getUpdates', [
            'query' => [
                'chat_id' => getenv('ADMIN_GROUP_ID')
            ],
        ]);
        $data = json_decode($response->getBody(), true);
        if (!$data['ok']) {
            return [];
        }

        return $data['result'];
    }

    public function forwardMessage(
        ?int $orderNumber,
        int  $groupIdFrom,
        int  $chatIdTo,
    ): void
    {
        $postId = $this->getPostId($orderNumber);
        if (!$postId) {
            return;
        }
        if (!$groupIdFrom) {
            return;
        }
        if (!$chatIdTo) {
            return;
        }
        Request::copyMessage([
            'chat_id' => $chatIdTo,
            'from_chat_id' => $groupIdFrom,
            'message_id' => $postId,
            'protect_content' => true,
        ]);
    }
}