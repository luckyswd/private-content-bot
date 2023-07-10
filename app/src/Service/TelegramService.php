<?php

namespace App\Service;

use Longman\TelegramBot\Request;
use GuzzleHttp\Client;


class TelegramService
{
    public function getPostId(
        int $orderNumber,
    ): int|null
    {
        $allPosts = $this->getAllPosts();
        var_dump($allPosts);
        foreach ($allPosts as $key => $post) {
            if (++$key == $orderNumber) {
                return $post['message']['message_id'];
            }
        }

        return null;
    }
    private function getAllPosts(): array
    {
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
        int $groupIdFrom,
        int $chatIdTo,
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