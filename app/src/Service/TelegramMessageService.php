<?php

namespace App\Service;

use App\Entity\Price;
use App\Entity\User;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use Longman\TelegramBot\Request;

class TelegramMessageService
{
    private bool $isSend = false;

    public function __construct(
        private SettingService $settingService,
        private RateRepository $rateRepository,
        private UserRepository $userRepository,
    )
    {}

    public function sendEndMessage(): void {
        Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getCallbackQuery()->getMessage()->getChat()->getId(),
            'text' => $this->settingService->getParameterValue('endMessage'),
        ]);
    }

    public function sendDeniedReceiptMessage(): void {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['telegramId' => TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId()]);

        Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getCallbackQuery()->getMessage()->getChat()->getId(),
            'text' => sprintf('Следующее видео станет доступно после %s', $user->getSubscription()->getNextDate()),
        ]);
    }

    public function sendPaymentsMessageAndOptions(): void {
        $rates = $this->rateRepository->findAll();
        $inlineKeyboardButton = [];

        foreach ($rates as $rate) {
            $callbackData = [
                'rate' => $rate->getId(),
                'currency' => Price::RUB_CURRENCY,
            ];

            $inlineKeyboardButton['inline_keyboard'][] = [
                [
                    'text' => $rate->getName(),
                    'callback_data' => json_encode($callbackData),
                ],
            ];
        }

        Request::sendMessage(
            [
                'chat_id' =>  TelegramService::getUpdate()->getMessage()->getChat()->getId(),
                'text' => $this->getStartMessage(),
                'reply_markup' => json_encode($inlineKeyboardButton),
            ]
        );
    }

    private function getStartMessage(): string {
        $result = $this->settingService->getParameterValue('startMessage') ?? '';
        $result .= " \n";
        $rates = $this->rateRepository->findAll();
        $seperator = 'или';

        foreach ($rates as $rate) {
            $prices = $rate?->getPrices();
            $result .= $rate?->getName() . ' -';
            $lastKeyPrices = array_key_last($prices->toArray());

            /** @var Price $price */
            foreach ($prices as $key => $price) {
                $result .= sprintf(' %s %s %s', $price?->getPrice(), $price->getCurrency(), $key !== $lastKeyPrices ? $seperator : '');
            }

            $result .= " \n";
        }

        return $result;
    }

    public static function getMenuButtons(): array {
        return [
            "inline_keyboard" => [
                [
                    [
                        'text' => 'Получить следующее видео',
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

    public function getSubscriptionErrorMessage(
        User $user,
    ): string {
        return sprintf('У вас есть активная подписка до %s',  $user->getSubscription()?->getLeftDateString());
    }

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
            'reply_markup' => json_encode(TelegramMessageService::getMenuButtons()),
        ]);

        $this->isSend = true;

        return true;
    }
}