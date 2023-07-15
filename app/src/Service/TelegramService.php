<?php

namespace App\Service;

use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use GuzzleHttp\Client;


class TelegramService
{
    public function getPostId(
        int $orderNumber,
    ): int|null
    {
        $allPosts = $this->getAllPosts();
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
    public function startCommand(
        Update $update
    ): void
    {
        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }
        $chatId = $update->getMessage()->getChat()->getId();
        $message = $update->getMessage()->getText();
        if ($message !== '/start') {
            return;
        }
        $welcomeText = 'Приветствую! Выберите действие:';
        $inlineKeyboard = new InlineKeyboard(
            [
                new InlineKeyboardButton(['text' => 'Кнопка 1', 'callback_data' => 'button1']),
                new InlineKeyboardButton(['text' => 'Кнопка 2', 'callback_data' => 'button2']),
                new InlineKeyboardButton(['text' => 'Кнопка 3', 'callback_data' => 'button3']),
            ]
        );
        $data = [
            'chat_id' => $chatId,
            'text' => $welcomeText,
            'reply_markup' => $inlineKeyboard,
        ];

        Request::sendMessage($data);
    }

    public function handleButton(
        Update $update
    ): void
    {
        if (!($update->getCallbackQuery() instanceof CallbackQuery)) {
            return;
        }
        $callbackQuery = $update->getCallbackQuery();
        $chatId = $callbackQuery->getFrom()->getId();
        $callbackData = $callbackQuery->getData();
        if ($callbackData == 'button1') {
            $data = [
                'chat_id' => $chatId,
                'text' => 'button1',
            ];
            Request::sendMessage($data);
        }
        if ($callbackData == 'button2') {
            $data = [
                'chat_id' => $chatId,
                'text' => 'button2',
            ];
            Request::sendMessage($data);
        }
        if ($callbackData == 'button3') {
            $data = [
                'chat_id' => $chatId,
                'text' => 'button3',
            ];
            Request::sendMessage($data);
        }
    }
}