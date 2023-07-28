<?php

namespace App\Handler;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TelegramService;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Request;

class TelegramValidationHandler
{
    private bool $isSend = false;

    public function __construct(
        private UserRepository $userRepository,
    )
    {}

    public function sendMessageActiveSubscription(
        ?string $telegramId,
    ): bool {
        if ($this->isSend) {
            return true;
        }

        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user || !$user->hasActiveSubscription()) {
            return false;
        }

        Request::sendMessage([
            'chat_id' =>  $telegramId,
            'text' => $this->getSubscriptionErrorMessage($user),
            'reply_markup' => json_encode(self::getMenuButtons()),
        ]);

        $this->isSend = true;

        return true;
    }

    public function getSubscriptionErrorMessage(
        User $user,
    ): string {
        return sprintf('У вас есть активная подписка до %s',  $user->getSubscription()?->getLeftDateString());
    }

    public function isMenuButtonsClick(): bool {
        $update = TelegramService::getUpdate();

        if (!$update->getCallbackQuery() instanceof CallbackQuery) {
            return false;
        }

        $data = $update->getCallbackQuery()->getData();

        if (in_array($data, ['get_next_video', 'get_all_video'])) {
            return true;
        }

        return false;
    }

    public static function getMenuButtons(): array {
        return [
            "inline_keyboard" => [
                [
                    [
                        'text' => 'Получить следующие видео',
                        'callback_data' => 'get_next_video'
                    ],
                ],
                [
                    [
                        'text' => 'Получить все предыдущие видео',
                        'callback_data' => 'get_all_video'
                    ]
                ]
            ]
        ];
    }
}