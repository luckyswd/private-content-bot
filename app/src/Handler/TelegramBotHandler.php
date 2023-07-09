<?php

namespace App\Handler;

use App\Service\TelegramService;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;

class TelegramBotHandler
{
    private ?Telegram $telegram = null;

    public function __construct()
    {
        $this->telegram = new Telegram(getenv('BOT_API_KEY'), getenv('BOT_USERNAME'));
    }

    public function setWebhook(): string|null
    {
        try {
            $result = $this->telegram->setWebhook(getenv('HOOK_URL'));
            if ($result->isOk()) {
                return $result->getDescription();
            }

            return null;
        } catch (TelegramException $e) {
            return $e->getMessage();
        }
    }

    public function deleteWebhook(): string
    {
        try {
            $result = $this->telegram->deleteWebhook();
            return $result->getDescription();
        } catch (TelegramException $e) {
            return $e->getMessage();
        }
    }

    public function handleWebhook()
    {
        try {
            $this->telegram->handle();


            $telegramService = new TelegramService();

//            $telegramService->setToDbPostId($this->getUpdate(), getenv('ADMIN_GROUP_ID'));
//            $telegramService->forwardMessage(['22'],getenv('ADMIN_GROUP_ID'), 725014793);

        } catch (TelegramException $e) {
            var_dump($e->getMessage());
        }

        return [];
    }

    private function getUpdate(): Update
    {
        $input = Request::getInput();
        $post = json_decode($input, true);
        return new Update($post, getenv('BOT_USERNAME'));
    }

}