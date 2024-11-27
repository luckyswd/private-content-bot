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

    public function sendDeniedReceiptMessage(SubscriptionType $subscriptionType): void {
        $response = Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getCallbackQuery()->getMessage()->getChat()->getId(),
            'text' => $this->telegramService->getMessageForNextVideo($subscriptionType),
        ]);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function sendStartMessageForTrainingAfterPay(): void {
        $response = Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId(),
            'text' => "
                Краткая инструкция по выполнению упражнений:

💪 тренировки сочетают в себе упражнения на все тело с акцентом на ягодицы (количество упражнений на ягодицы больше, чем на остальные части тела)
🕐 тренировки можно выполнять в любое время, но лучше заканчивать не позднее, чем за 2 часа до сна
⏱️ каждая тренировка расчитана на 1-1,5 часа занятий в зависимости от вашего темпа и физических возможностей
🥩🍟 обязательно поешь за 1-1,5 ч до тренировки, не выполняй тренировки на голодный желудок! Так же стоит перекусить в течение 0,5 - 1 часа после тренировки
🔥 обязательно разомнись перед выполнением тренировки - аккуратно повращай каждый сустав своего тела в течение 1-2 минут, первый подход в каждом упражнении лучше выполнить без веса, чтобы вспомнить технику
🩸в период менструации тренироваться можно, главное - следи за самочувствием и не нагружай организм слишком сильно
🤰для выполнения силовых тренировок во время беременности лучше проконсультироваться с врачом
🏋️‍♂️ не гонись за весами
Если нет опыта занятий, то рекомендую начать с минимальной нагрузки, освоить технику. Чем больше вес на штанге, тем меньше повторений ты сможешь выполнить, поэтому в тренировках я указала разбег - 10-15 повторов для зала и 15-20 повторов для дома, последние 2-3 повтора должны даваться тяжело.

Повышать вес можно по такой схеме:
1 тренировка: 10 кг на 12 раз 
2: 10 кг на 15 раз
3: 12,5 кг на 10 раз
4: 12,5 кг на 12 раз
И т.д.
                ",
            'parse_mode' => 'HTML',
        ]);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function sendStartMenu(int $chatId): void {
        $user = $this->userRepository->getCacheUser($chatId);

        $response = Request::sendMessage(
            [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'text' => !$user->getSubscriptions()->isEmpty() ? $this->messageActiveSubscription($user) : $this->getStartMessage(),
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
                    'text' => !$user->getSubscriptions()->isEmpty() ? $this->messageActiveSubscription($user) : $this->getStartMessage(),
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
                'text' => !$user->getSubscriptions()->isEmpty() ? $this->messageActiveSubscription($user) : $this->getStartMessage(),
                'reply_markup' => json_encode($inlineKeyboardButton),
                'parse_mode' => 'HTML',
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

            $defaultTextForCatalog = $this->defaultMessageForCategory($subscriptionType);
        } else {
            if (!empty($catalogs)) {
                foreach ($catalogs as $catalog) {
                    $inlineKeyboardButton['inline_keyboard'][] = [
                        [
                            'text' => $catalog->getName(),
                            'callback_data' => json_encode([
                                'type' => 'catalog',
                                'id' => $catalog->getId(),
                            ]),
                        ],
                    ];
                }
            } else {
                $subscription = $user->getSubscriptionByType($currentCatalog->getSubCatalog()->getSubscriptionType());
                $trainingCatalogSubscription = $subscription->getTrainingCatalogSubscriptionByCatalog($currentCatalog);
                $inlineKeyboardButton = [];

                if ($trainingCatalogSubscription->getStep() === 1) {
                    $this->sendStartMessageForTrainingAfterPay();
                }

                $this->telegramService->forwardMessageTraining($trainingCatalogSubscription->getStep(), $currentCatalog,  $chatId);
            }
        }

        $defaultText = !$user->getSubscriptions()->isEmpty() ? $this->messageActiveSubscription($user) : $this->getStartMessage();

        $response = Request::sendMessage([
            'chat_id' => $chatId,
            'text' => !empty($defaultTextForCatalog) ? $defaultTextForCatalog : $defaultText,
            'reply_markup' => json_encode($inlineKeyboardButton),
            'parse_mode' => 'HTML',
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
            if (!$user->hasActiveSubscription($subscription->getType())) {
                continue;
            }

            $result .= sprintf(PHP_EOL . PHP_EOL . "<b>Ваш доступ активен до</b> %s ⏱️ %s<b>Тип Подписки:</b> Программа тренировок '%s' 📌",
                $user->getSubscriptionByType($subscription->getType())?->getLeftDateString(),
                PHP_EOL,
                SubscriptionType::getRUname($subscription->getType()),
            );
        }

        return empty($result) ? $this->getStartMessage() : $result;
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

    private function defaultMessageForCategory(SubscriptionType $subscriptionType): string {
        $result = '';

        switch ($subscriptionType->value) {
            case 2:
                $result = "
                Программа для дома с бутылками/гантелями

Для выполнения упражнений понадобится несколько разных бутылок с водой (1, 1,5, 2, 5л), либо набор гантелей, коврик, шерстяные носки (для скольжения по полу).

🔥 можно выбрать формат занятий на все тело 3 дня в неделю через день (например, понедельник, среда, пятница)
🔥 можно с разделением на верх и низ тела
На 2 дня:
Понедельник - верх
Пятница - низ
На 4 дня:
Понедельник - верх
Среда - низ
Четверг - верх
Суббота - низ";
                break;
            case 3:
                $result = "
                Программа для дома с резинками

Для выполнения упражнений понадобится только набор фитнес-резинок и коврик (не обязательно).
Подойдет для новичков, так как с резинками идеально отрабатывать технику упражнений.

🔥 можно выбрать формат занятий на все тело 3 дня в неделю через день (например, понедельник, среда, пятница)
🔥 можно с разделением на верх и низ тела 
На 2 дня:
Понедельник - верх
Пятница - низ
На 4 дня:
Понедельник - верх
Среда - низ
Четверг - верх
Суббота - низ";
                break;

            case 4:
                $result = "
                Программа для зала 

Программа с использованием свободных весов (гантели и штанга) и распространненных тренажеров в зале. Если нет опыта занятий, рекомендую начать с домашних программ и уже после освоения техники переходить в программе в зале.

🔥 можно выбрать формат занятий на все тело 3 дня в неделю через день (например, понедельник, среда, пятница)
🔥 можно с разделением на верх и низ тела
На 2 дня:
Понедельник - верх
Пятница - низ
На 4 дня:
Понедельник - верх
Среда - низ
Четверг - верх
Суббота - низ
                ";
                break;
        }

        return $result;
    }
}