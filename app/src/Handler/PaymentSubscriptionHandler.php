<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Rate;
use App\Entity\User;
use App\Repository\MethodRepository;
use App\Repository\PriceRepository;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use App\Service\TelegramMessageService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\Payments\PreCheckoutQuery;
use Longman\TelegramBot\Request;
use stdClass;

class PaymentSubscriptionHandler
{
    public function __construct(
        private TelegramMessageService $telegramMessageService,
        private RateRepository $rateRepository,
        private MethodRepository $methodRepository,
        private PriceRepository $priceRepository,
        private TelegramMessageHandler $telegramMessageHandler,
        private UserRepository $userRepository,
        private TelegramService $telegramService,
        private EntityManagerInterface $entityManager,
    ){}

    public function handleSubscription(
        stdClass $callbackData,
    ): void {
        $telegramId = TelegramService::getUpdate()?->getCallbackQuery()?->getRawData()['from']['id'] ?? null;

        if ($this->telegramMessageService->sendMessageActiveSubscription($telegramId)) {
            return;
        }

        $rate = $this->rateRepository->findOneBy(['id' => $callbackData->id ?? null]);
        $method = $this->methodRepository->findOneBy(['id' => Method::YKASSA_ID]);
        $currency = $callbackData->currency ?? null;

        if (!$rate instanceof Rate || !$method instanceof Method) {
            return;
        }

        $price = $this->priceRepository->findOneBy(
            [
                'rate' => $rate,
                'currency' => $currency,
            ]
        );

        $prices = [
            [
                'label' => sprintf("%s зарядок", $rate->getName()),
                'amount' => $price?->getPrice() * 100,
            ]
        ];

        $postFields = [
            'chat_id' => TelegramService::getUpdate()?->getCallbackQuery()?->getFrom()?->getId() ?? '',
            'provider_token' => $method->getToken(),
            'title' => sprintf("%s зарядок", $rate->getName()),
            'description' => sprintf("%s зарядок", $rate->getName()),
            'need_email' => true,
            'provider_data' => [
                'receipt' => [
                    'items' => [
                        [
                            'description' => $rate->getName(),
                            'quantity' => 1.00,
                            'amount' => [
                                'value' => $price->getPrice(),
                                'currency' => $currency,
                            ],
                            'vat_code' => 1,
                        ]
                    ],
                    'customer' => [
                        'email' => 'ju_letta@mail.ru',
                    ]
                ]
            ],
            'payload' => [
                'unique_id' => date('y-m-d-H-i-S'),
                'provider_token' => $method->getToken(),
                'id' => $rate->getId(),
                'type' => 'rate',
            ],
            'currency' => $currency,
            'prices' => json_encode($prices),
        ];

        $response = Request::sendInvoice($postFields);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function paymentProcessor(
        PreCheckoutQuery $preCheckoutQuery
    ): void {
        $telegramId = $preCheckoutQuery->getFrom()->getId() ?? null;

        if ($this->telegramMessageService->sendMessageActiveSubscription($telegramId)) {
            $preCheckoutQuery->answer(false, [
                'error_message' => 'У вас есть активная подписка.',
            ]);

            return;
        }

        $response = $preCheckoutQuery->answer(true);

        $this->telegramMessageHandler->addMessage($response);
    }

    public function handelSuccessfulPayment(): void {
        $this->addUser();

        Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getMessage()->getChat()->getId(),
            'parse_mode' => 'HTML',
            'text' => "
            <b>Привет, моя дорогая!</b>

<i>Хочу выразить тебе огромную благодарность за доверие и покупку моего сборника зарядок. Я сама долгое время страдала от отеков вследствие плохой осанки и неграмотно построенного питания, худела, садилась на очередную диету, но ничего не помогало и мои проблемные места всегда оставались со мной. Тогда я была далека от спорта и многие упражнения для меня были сложны и недоступны. Поэтому в своих зарядках я собрала несложные, но самые эффективные упражнения, которые возможно повторить с любым уровнем подготовки. Впереди у нас с тобой 30 продуктивных дней разнообразных зарядок не более, чем на 5 минут. Я надеюсь, что они не только помогут улучшить осанку, избавиться от отеков и укрепить мышцы тазового дна, но и зарядят тебя мотивацией и привьют полезную привычку уделять время своему телу во имя любви к нему❤️

Ну что? Вперед!</i>",
        ]);
        Request::sendMessage([
            'chat_id' =>  TelegramService::getUpdate()->getMessage()->getChat()->getId(),
            'parse_mode' => 'HTML',
            'text' => "<b>Краткая инструкция по выполнению упражнений:</b>
            
💧 зарядки сочетают в себе упражнения на осанку, противоотечные, тазовое дно, мышцы живота + общее укрепление тела
🕐 зарядки можно выполнять в любое время, не обязательно с утра, но с утра выполнять классно - моментально уходят утренние отеки, появляется заряд бодрости и настрой на активный день
⏱ каждая зарядка длится 3-5 минут, достаточно выполнить по 1 подходу каждого упражнения
🔢 каждое упражнение выполняется по 12-15 раз, если упражнения отдельно на каждую руку и ногу, то выполняются на каждую сторону по 12-15 раз, если стороны чередуются, тогда в общем 12-15 раз
🩸в период менструации делать зарядки можно, ты можешь убрать упражнения в которых не слишком комфортно себя чувствуешь
🤰зарядку можно выполнять беременным с разрешения врача-гинеколога и при отсутствии противопоказаний на физическую активность. Упражнения на животе можно заменить на аналогичные упражнения, сидя на коленях или на четвереньках
🥗 не забывай про питание (по КБЖУ) и уход за кожей - в совокупности с зарядками будет наилучший эффект",
        ]);

        $this->telegramService->forwardMessage(1, getenv('ADMIN_GROUP_ID'), TelegramService::getUpdate()->getMessage()->getChat()->getId());
    }

    private function addUser(): void {
        $telegramId = TelegramService::getUpdate()->getMessage()->getChat()->getId();
        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);
        $invoicePayload = json_decode(TelegramBotChargersHandler::getSuccessfulPayment()?->getInvoicePayload());
        $rate = $this->rateRepository->findOneBy(['id' => $invoicePayload->id]);

        if ($user) {
            if ($user->hasActiveSubscription()) {
                return;
            }

            $this->updateSubscription($user, $rate);

            return;
        }

        $user = new User();
        $user->setTelegramId($telegramId);
        $user->addSubscription($rate);
        $this->entityManager->persist($user);

        $this->entityManager->flush();
    }

    private function updateSubscription(
        User $user,
        Rate $rate,
    ): void {
        $user->addSubscription($rate);
        $this->entityManager->flush();
    }
}