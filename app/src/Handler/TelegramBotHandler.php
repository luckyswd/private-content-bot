<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Post;
use App\Entity\Rate;
use App\Entity\User;
use App\Repository\MethodRepository;
use App\Repository\PostRepository;
use App\Repository\PriceRepository;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use App\Service\TelegramMessageService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\Payments\SuccessfulPayment;
use Longman\TelegramBot\Request;

class TelegramBotHandler
{
    public function __construct(
        private RateRepository   $rateRepository,
        private MethodRepository $methodRepository,
        private PriceRepository  $priceRepository,
        private TelegramService  $telegramService,
        private EntityManagerInterface  $entityManager,
        private UserRepository $userRepository,
        private PostRepository $postRepository,
        private TelegramMessageService $telegramMessageService,
    )
    {
    }

    public function handelStartMessage(): void
    {
        $update = TelegramService::getUpdate();

        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $message = $update->getMessage()?->getText() ?? '';

        if ($message !== '/start') {
            return;
        }

        if ($this->telegramMessageService->sendMessageActiveSubscription($update->getMessage()?->getChat()?->getId())) {
            return;
        }

        $this->telegramMessageService->sendPaymentsMessageAndOptions();
    }

    private function getCallbackData(): string
    {
        return TelegramService::getUpdate()?->getCallbackQuery()?->getData() ?? '';
    }

    private function getChatId(): string
    {
        return TelegramService::getUpdate()?->getCallbackQuery()?->getFrom()?->getId() ?? '';
    }

    public function handlePaymentCard(): void {
        $telegramId = TelegramService::getUpdate()?->getCallbackQuery()?->getRawData()['from']['id'] ?? null;

        if ($this->telegramService->isMenuButtonsClick()) {
            return;
        }

        if ($this->telegramMessageService->sendMessageActiveSubscription($telegramId)) {
            return;
        }

        $callbackData = json_decode($this->getCallbackData());
        $rate = $this->rateRepository->findOneBy(['id' => $callbackData->rate ?? null]);
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
                'label' => 'Подписка на ' . $rate?->getName(),
                'amount' => $price?->getPrice() * 100,
            ]
        ];

        $postfields = [
            'chat_id' => $this->getChatId(),
            'provider_token' => $method->getToken(),
            'title' => sprintf('Подписка на %s', $rate?->getName()),
            'description' => sprintf('Подписка на %s', $rate?->getName()),
            'payload' => [
                'unique_id' => date('y-m-d-H-i-S'),
                'provider_token' => $method->getToken(),
                'rate' => $rate->getId(),
            ],
            'currency' => $currency,
            'prices' => json_encode($prices),
        ];

        Request::sendInvoice($postfields);
    }

    public function PaymentProcessor(): void {
        $preCheckoutQuery = TelegramService::getUpdate()->getPreCheckoutQuery();

        if (!$preCheckoutQuery) {
            return;
        }

        $telegramId = $preCheckoutQuery->getFrom()->getId() ?? null;

        if ($this->telegramMessageService->sendMessageActiveSubscription($telegramId)) {
            $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

            $preCheckoutQuery->answer(false, [
                'error_message' => $this->telegramMessageService->getSubscriptionErrorMessage($user),
            ]);

            return;
        }

        $preCheckoutQuery->answer(true);
    }

    private function getSuccessfulPayment(): ?SuccessfulPayment {
        return TelegramService::getUpdate()?->getMessage()?->getSuccessfulPayment() ?? null;
    }

    public function handelSuccessfulPayment(): void
    {
        if (!$this->getSuccessfulPayment()) {
            return;
        }

        $this->addUser();
        Request::sendMessage([
            'chat_id' =>  TelegramService::getUpdate()->getMessage()->getChat()->getId(),
            'text' => 'Привет, моя дорогая!Хочу выразить тебе огромную благодарность за доверие и покупку моего сборника зарядок. Я сама долгое время страдала от отеков вследствие плохой осанки и неграмотно построенного питания, худела, садилась на очередную диету, но ничего не помогало и мои проблемные места всегда оставались со мной. Тогда я была далека от спорта и многие упражнения для меня были сложны и недоступны. Поэтому в своих зарядках я собрала несложные, но самые эффективные упражнения, которые возможно повторить с любым уровнем подготовки. Впереди у нас с тобой 30 продуктивных дней разнообразных зарядок не более, чем на 5 минут. Я надеюсь, что они не только помогут улучшить осанку, избавиться от отеков и укрепить мышцы тазового дна, но и зарядят тебя мотивацией и привьют полезную привычку уделять время своему телу во имя любви к нему❤️ Ну что? Вперед!',
        ]);

        Request::sendMessage([
            'chat_id' =>  TelegramService::getUpdate()->getMessage()->getChat()->getId(),
            'text' => 'Краткая инструкция по выполнению упражнений:
💧 зарядки сочетают в себе упражнения на осанку, противоотечные, тазовое дно, мышцы живота + общее укрепление тела
🕐 зарядки можно выполнять в любое время, не обязательно с утра, но с утра выполнять классно - моментально уходят утренние отеки, появляется заряд бодрости и настрой на активный день
⏱ каждая зарядка длится 3-5 минут, достаточно выполнить по 1 подходу каждого упражнения
🔢 каждое упражнение выполняется по 12-15 раз, если упражнения отдельно на каждую руку и ногу, то выполняются на каждую сторону по 12-15 раз, если стороны чередуются, тогда в общем 12-15 раз
🩸в период менструации делать зарядки можно, ты можешь убрать упражнения в которых не слишком комфортно себя чувствуешь
🤰зарядку можно выполнять беременным с разрешения врача-гинеколога и при отсутствии противопоказаний на физическую активность. Упражнения на животе можно заменить на аналогичные упражнения, сидя на коленях или на четвереньках
🥗 не забывай про питание (по КБЖУ) и уход за кожей - в совокупности с зарядками будет наилучший эффект',
        ]);
        $this->telegramService->forwardMessage(1, getenv('ADMIN_GROUP_ID'), TelegramService::getUpdate()->getMessage()->getChat()->getId());
    }

    public function handelMassageId(): void
    {
        $update = TelegramService::getUpdate();
        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $message = $update->getMessage();
        if ($message && $message->getChat()->getId() == getenv('ADMIN_GROUP_ID')) {
            $postId = $message->getMessageId();
            $post = new Post();
            $post->setMessageId($postId);
            $post->setBotName(TelegramService::getUpdate()->getBotUsername());
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }
    }

    private function addUser(): void {
        $telegramId = TelegramService::getUpdate()->getMessage()->getChat()->getId();
        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);
        $invoicePayload = json_decode($this->getSuccessfulPayment()?->getInvoicePayload());
        $rate = $this->rateRepository->findOneBy(['id' => $invoicePayload->rate]);

        if ($user) {
            if ($user->hasActiveSubscription()) {
                return;
            }
            $this->updateSubscription($user, $rate);

            return;
        }

        $user = new User();
        $user->setTelegramId($telegramId);
        $user->setSubscription($rate);
        $this->entityManager->persist($user);

        $this->entityManager->flush();
    }

    private function updateSubscription(
        User $user,
        Rate $rate,
    ): void {
        $user->setSubscription($rate);
        $this->entityManager->flush();
    }

    public function handelMenuButtons(): void
    {
        $update = TelegramService::getUpdate();

        if (!$update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $data = $update->getCallbackQuery()->getData();

        match ($data) {
            'get_all_video' => $this->handleGetAllVideo(),
            'get_next_video' => $this->handleGetNextVideo(),
            default => 'Неизвестная опция.',
        };
    }

    private function handleGetAllVideo(): void
    {
        $telegramId = TelegramService::getUpdate()?->getCallbackQuery()?->getRawData()['from']['id'] ?? null;
        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user || !$user->hasActiveSubscription()) {
            $this->telegramMessageService->sendPaymentsMessageAndOptions();

            return;
        }

        $subscription = $user->getSubscription();
        $step = $subscription->getStep();
        $allowedCountPost = $subscription->getAllowedCountPost();

        $posts = $this->postRepository->getAllPostsByBotName(
            TelegramService::getUpdate()->getBotUsername(),
            min($step, $allowedCountPost),
        );

        foreach ($posts as $key => $post) {
            $this->telegramService->forwardMessage(
                $key + 1,
                getenv('ADMIN_GROUP_ID'),
                TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId(),
                $key === array_key_last($posts),
            );
        }
    }

    private function handleGetNextVideo(): void {
        $callBackQuery = TelegramService::getUpdate()->getCallbackQuery();

        if (!$callBackQuery) {
            return;
        }

        $telegramId = $callBackQuery->getFrom()->getId();
        $botUsername = $callBackQuery->getBotUsername();

        if (!$telegramId) {
            return;
        }

        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);
        $subscription = $user->getSubscription();
        $allowedCountPost = $subscription->getAllowedCountPost();
        $step = $subscription->getStep();

        if ($step >= $this->telegramService->getCountAllPostByBotName($botUsername)) {
            $this->telegramMessageService->sendEndMessage();

            return;
        }

        if ($allowedCountPost <= $step) {
            $this->telegramMessageService->sendDeniedReceiptMessage();

            return;
        }

        $subscription->setStep($step + 1);

        $this->telegramService->forwardMessage(
            $step + 1,
            getenv('ADMIN_GROUP_ID'),
            TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId(),
        );

        $this->entityManager->flush();
    }
}