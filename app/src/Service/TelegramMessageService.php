<?php

namespace App\Service;

use App\Entity\Price;
use App\Entity\Rate;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Handler\TelegramMessageHandler;
use App\Repository\PresentationRepository;
use App\Repository\RateRepository;
use App\Repository\TrainingCatalogRepository;
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
        private TelegramService $telegramService,
        private TrainingCatalogRepository $trainingCatalogRepository,
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
            'text' => sprintf('Следующее видео станет доступно после %s', $user->getSubscriptionByType()->getNextDate()),
        ]);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function sendStartMenu(int $chatId): void {
        $user = $this->userRepository->getCacheUser($chatId);

        $response = Request::sendMessage(
            [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'text' => $user?->hasActiveSubscription() ? $this->messageActiveSubscription($user) : $this->getStartMessage(),
                'reply_markup' => json_encode($this->telegramService->startMenuButtons()),
            ]
        );

        $this->telegramMessageHandler->addMessage($response);
    }

    public function sendCharges(int $chatId): void {
        $user = $this->userRepository->getCacheUser($chatId);

        if ($user && $user->getSubscriptionByType()) {
            Request::sendMessage(
                [
                    'chat_id' => $chatId,
                    'text' => $this->getStartMessage(),
                    'reply_markup' => json_encode($this->telegramService->getButtonForChargersVideo()),
                    'parse_mode' => 'HTML',
                ]
            );

            return;
        }

        $rates = $this->rateRepository->findBy(['subscriptionType' => SubscriptionType::CHARGERS]);
        $inlineKeyboardButton = [];

        foreach ($rates as $rate) {
            $callbackData = [
                'type' => 'rate',
                'id' => $rate->getId(),
                'currency' => Price::RUB_CURRENCY,
            ];

            if ($rate->getId() === 3) {
                $text = sprintf("%s зарядок за %s₽ вместо 3499₽", $rate->getName(), $rate->getPrices()->toArray()[0]->getPrice());
            } else {
                $text = sprintf("%s зарядок - %s ₽", $rate->getName(), $rate->getPrices()->toArray()[0]->getPrice());
            }

            $inlineKeyboardButton['inline_keyboard'][] = [
                [
                    'text' => $text,
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

    public function sendTrainings(
        int $chatId,
        ?int $currentCatalogId = null,
    ): void {
        $user = $this->userRepository->getCacheUser($chatId);
        $currentCatalog = $currentCatalogId ? $this->trainingCatalogRepository->findOneBy(['id' => $currentCatalogId]) : null;
        $catalogs = $this->trainingCatalogRepository->findBy(['subCatalog' => $currentCatalogId ?: null]);

        $backType = $currentCatalogId ? 'backCatalog' : 'backMenu';
        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => 'Назад',
                'callback_data' => json_encode(['type' => $backType]),
            ],
        ];

        $subscriptionType = $currentCatalog && $currentCatalog->getSubscriptionType() ? $currentCatalog->getSubscriptionType() : null;

        if ($subscriptionType && !$user->hasActiveSubscription($subscriptionType)) {
            $rates = $this->rateRepository->findBy(['subscriptionType' => $currentCatalog->getSubscriptionType()]);

            foreach ($rates as $rate) {
                $callbackData = [
                    'type' => 'rate',
                    'id' => $rate->getId(),
                    'currency' => Price::RUB_CURRENCY,
                ];

                $text = sprintf("%s тренировок - %s ₽", $rate->getName(), $rate->getPrices()->toArray()[0]->getPrice());

                $inlineKeyboardButton['inline_keyboard'][] = [
                    [
                        'text' => $text,
                        'callback_data' => json_encode($callbackData),
                    ],
                ];
            }
        } else {
            foreach ($catalogs as $catalog) {
                $inlineKeyboardButton['inline_keyboard'][] = [
                    [
                        'text' => $catalog->getName(),
                        'callback_data' => json_encode([
                            'type' => 'catalog',
                            'id' => $catalog->getId(),
//                        'currency' => Price::RUB_CURRENCY,
                        ]),
                    ],
                ];
            }
        }

        $response = Request::sendMessage([
            'chat_id' => $chatId,
            'text' => $this->getStartMessage(),
            'reply_markup' => json_encode($inlineKeyboardButton),
        ]);

        $this->telegramMessageHandler->addMessage($response);
    }

    private function getStartMessage(): string {
        return $this->settingService->getParameterValue('startMessage') ?? '';
    }

    public function messageActiveSubscription(
        User $user,
    ): string {
        $subscriptions = $user->getSubscriptions();

        $result = '';

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $result .= sprintf(PHP_EOL . PHP_EOL . "<b>Ваш доступ активен до</b> %s ⏱️ %s<b>Тип Подписки:</b> %s 📌",
                $user->getSubscriptionByType($subscription->getType())?->getLeftDateString(),
                PHP_EOL,
                SubscriptionType::getRUname($subscription->getType()),
            );
        }

        return $result;
    }

    public function sendMessageActiveSubscription(
        ?string $telegramId,
        ?Rate $rate,
    ): bool {
        if ($this->isSend) {
            return true;
        }

        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user || !$user->hasActiveSubscription($rate->getSubscriptionType())) {
            return false;
        }

        $response = Request::sendMessage([
            'chat_id' =>  $telegramId,
            'parse_mode' => 'HTML',
            'text' => $this->messageActiveSubscription($user),
            'reply_markup' => json_encode($this->telegramService->getButtonForChargersVideo()),
        ]);

        $this->telegramMessageHandler->addMessage($response);

        $this->isSend = true;

        return true;
    }
}