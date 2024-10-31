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

class TelegramBotTrainingHandler
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

    public function handelMenuButtonsTrainings(): void
    {
        $update = TelegramService::getUpdate();

        if (!$update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $data = $update->getCallbackQuery()->getData();
        $data = json_decode($data);
        $chatId = $update->getCallbackQuery()->getFrom()->getId();

        match ($data->type) {
            'backMenu' => $this->telegramMessageService->sendStartMenu($chatId),
            'backCatalog', 'training_programs' => $this->telegramMessageService->sendTrainings($chatId),
            'catalog' => $this->telegramMessageService->sendTrainings($chatId, $data->id ?? null, $data->parentId ?? null),
            default => 'Неизвестная опция.',
        };
    }

    public function handlePaymentMethod(): void {
        $callbackData = json_decode($this->getCallbackData());

        if (!$callbackData) {
            return;
        }

        if (!property_exists($callbackData, 'type')) {
            return;
        }

        match ($callbackData->type) {
            'rate' =>  $this->paymentSubscriptionHandler->handleSubscription($callbackData),
            default => ''
        };
    }
}