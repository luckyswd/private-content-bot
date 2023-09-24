<?php

namespace App\Service;

use App\Entity\Presentation;
use App\Entity\Price;
use App\Entity\User;
use App\Handler\TelegramMessageHandler;
use App\Repository\PresentationRepository;
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
        private TelegramMessageHandler $telegramMessageHandler,
        private PresentationRepository $presentationRepository,
    )
    {}

    public function sendEndMessage(
        string $message,
    ): void {
        $response = Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getCallbackQuery()->getMessage()->getChat()->getId(),
            'text' => $message,
        ]);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function sendDeniedReceiptMessage(): void {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['telegramId' => TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId()]);

        $response = Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getCallbackQuery()->getMessage()->getChat()->getId(),
            'text' => sprintf('Следующее видео станет доступно после %s', $user->getSubscription()->getNextDate()),
        ]);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function sendPaymentsMessageAndOptions(
        int $chatId,
    ): void {
        $rates = $this->rateRepository->findAll();
        $presentations = $this->presentationRepository->findAll();
        $inlineKeyboardButton = [];

        foreach ($rates as $rate) {
            $callbackData = [
                'type' => 'rate',
                'id' => $rate->getId(),
                'currency' => Price::RUB_CURRENCY,
            ];

            $inlineKeyboardButton['inline_keyboard'][] = [
                [
                    'text' => $rate->getName(),
                    'callback_data' => json_encode($callbackData),
                ],
            ];
        }

        foreach ($presentations as $presentation) {
            $callbackData = [
                'type' => 'presentationInfo',
                'id' => $presentation->getId(),
                'currency' => Price::RUB_CURRENCY,
            ];

            $inlineKeyboardButton['inline_keyboard'][] = [
                [
                    'text' => $presentation->getName(),
                    'callback_data' => json_encode($callbackData),
                ],
            ];
        }

        $response = Request::sendMessage(
            [
                'chat_id' => $chatId,
                'text' => $this->getStartMessage(),
                'reply_markup' => json_encode($inlineKeyboardButton),
            ]
        );

        $this->telegramMessageHandler->addMessage($response);
    }

    private function getStartMessage(): string {
        $result = $this->settingService->getParameterValue('startMessage') ?? '';
        $result .= " \n";
        $rates = $this->rateRepository->findAll();
        $presentations = $this->presentationRepository->findAll();
        $seperator = 'или';

        foreach ($rates as $rate) {
            $prices = $rate?->getPrices();
            $result .= $rate?->getName() . ' зарядок' . ' -';
            $lastKeyPrices = array_key_last($prices->toArray());

            /** @var Price $price */
            foreach ($prices as $key => $price) {
                $result .= sprintf(' %s %s %s', $price?->getPrice(), '₽', $key !== $lastKeyPrices ? $seperator : '');
            }

            $result .= " \n";
        }

        /** @var Presentation $presentation */
        foreach ($presentations as $presentation) {
            $result .= sprintf('%s за %s ₽', $presentation->getName(), $presentation->getPrice());
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

        $response = Request::sendMessage([
            'chat_id' =>  $telegramId,
            'text' => $this->getSubscriptionErrorMessage($user),
            'reply_markup' => json_encode(TelegramMessageService::getMenuButtons()),
        ]);

        $this->telegramMessageHandler->addMessage($response);

        $this->isSend = true;

        return true;
    }
}