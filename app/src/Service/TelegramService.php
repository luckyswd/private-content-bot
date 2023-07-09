<?php

namespace App\Service;

use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;


class TelegramService
{


    public function getPost(
        int $channelId,
        int $orderNumber,
    ): string
    {
        $allPosts = $this->getAllPosts($channelId);
        dd($allPosts);

    }

    public function getAllPosts(
        int $channelId,
            $update,
    ): array
    {
//        $botToken = getenv('BOT_API_KEY');
//        $client = new Client([
//            'base_uri' => 'https://api.telegram.org/bot' . $botToken . '/',
//        ]);
//        $response = $client->get('getUpdates', [
//            'query' => [
//                'chat_id' => $channelId
//            ],
//        ]);
//        $data = json_decode($response->getBody(), true);
//        if (!$data['ok']) {
//            return [];
//        }


        return [];
    }

    public function setToDbPostId(
        Update $update,
        int $groupId,
        string $groupName = null,
    ): void {
        $message = $update->getMessage();
        if ($message && $message->getChat()->getId() === $groupId) {
            $postId = $message->getMessageId();
            //Добовлять ид в базу
        }
    }

    public function forwardMessage(
        array $postsId,
        int $groupIdFrom,
        int $chatIdTo,
    ): void {
        foreach ($postsId as $postId) {
            Request::copyMessage([
                'chat_id' => $chatIdTo,
                'from_chat_id' => $groupIdFrom,
                'message_id' => $postId,
                'protect_content' => true,
            ]);
        }
    }
}