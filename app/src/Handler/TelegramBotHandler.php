<?php

namespace App\Handler;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\SettingService;
use App\Service\TelegramMessageService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\Payments\SuccessfulPayment;

class TelegramBotHandler
{
    public function __construct(
        private TelegramService  $telegramService,
        private EntityManagerInterface  $entityManager,
        private UserRepository $userRepository,
        private PostRepository $postRepository,
        private TelegramMessageService $telegramMessageService,
        private SettingService $settingService,
        private PaymentSubscriptionHandler $paymentSubscriptionHandler,
        private PaymentPresentationHandler $paymentPresentationHandler,
    ){}

    public function handelStartMessage(): void {
        $update = TelegramService::getUpdate();

        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $message = $update->getMessage()?->getText() ?? '';

        if ($message !== '/start') {
            return;
        }

        $this->telegramMessageService->sendStartMenu($update->getMessage()?->getChat()?->getId());
    }

    private function getCallbackData(): string
    {
        return TelegramService::getUpdate()?->getCallbackQuery()?->getData() ?? '';
    }

    public function PaymentProcessor(): void {
        $preCheckoutQuery = TelegramService::getUpdate()->getPreCheckoutQuery();

        if (!$preCheckoutQuery) {
            return;
        }

        $invoicePayload = json_decode($preCheckoutQuery->getInvoicePayload());

        match ($invoicePayload->type) {
            'rate' =>  $this->paymentSubscriptionHandler->paymentProcessor($preCheckoutQuery),
            'presentation' =>  $this->paymentPresentationHandler->paymentProcessor($preCheckoutQuery),
        };
    }

    public function handelSuccessfulPayment(): void {
        if (!self::getSuccessfulPayment()) {
            return;
        }

        $invoicePayload = json_decode(self::getSuccessfulPayment()->getInvoicePayload());

        match ($invoicePayload->type) {
            'rate' =>  $this->paymentSubscriptionHandler->handelSuccessfulPayment(),
            'presentation' =>  $this->paymentPresentationHandler->handelSuccessfulPayment(),
        };
    }

    public static function getSuccessfulPayment(): ?SuccessfulPayment {
        return TelegramService::getUpdate()?->getMessage()?->getSuccessfulPayment() ?? null;
    }

    public function handelMassageId(): void {
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

//    public function handelMenuButtons(): void
//    {
//        $update = TelegramService::getUpdate();
//
//        if (!$update->getCallbackQuery() instanceof CallbackQuery) {
//            return;
//        }
//
//        $data = $update->getCallbackQuery()->getData();
//        $data = json_decode($data);
//
//        match ($data->type) {
//
//            default => 'Неизвестная опция.',
//        };
//    }

    public function handlePaymentCard(): void {
        $callbackData = json_decode($this->getCallbackData());

        if ($this->telegramService->isMenuButtonsClick() || !$callbackData) {
            return;
        }

        if (!property_exists($callbackData, 'type')) {
            return;
        }

        match ($callbackData->type) {
            'rate' =>  $this->paymentSubscriptionHandler->handleSubscription($callbackData),
            'presentationInfo' =>  $this->paymentPresentationHandler->handlePresentationInfo($callbackData),
            'presentation' =>  $this->paymentPresentationHandler->handlePresentation($callbackData),
            'get_all_video' => $this->handleGetAllVideo(),
            'get_next_video' => $this->handleGetNextVideo(),
            'chargers' => $this->telegramMessageService->sendCharges(TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId()),
            default => ''
        };
    }

    private function handleGetAllVideo(): void
    {
        $telegramId = TelegramService::getUpdate()?->getCallbackQuery()?->getRawData()['from']['id'] ?? null;
        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user || !$user->hasActiveSubscription()) {
            $this->telegramMessageService->sendStartMenu($telegramId);

            return;
        }

        $subscription = $user->getSubscriptionByType();
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
        $subscription = $user->getSubscriptionByType();
        $allowedCountPost = $subscription->getAllowedCountPost();
        $step = $subscription->getStep();

        if (!$user->hasActiveSubscription()) {
            $this->telegramMessageService->sendStartMenu($telegramId);

            return;
        }

        if ($step >= $this->telegramService->getCountAllPostByBotName($botUsername)) {
            $this->telegramMessageService->sendEndMessage($this->settingService->getParameterValue('endMessage'));

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