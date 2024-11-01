<?php

namespace App\Controller;

use App\Handler\TelegramBotHandler;
use App\Handler\TelegramBotTrainingHandler;
use App\Service\TelegramService;
use Longman\TelegramBot\Exception\TelegramException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/webhook')]
class WebhookController extends AbstractController
{
    #[Route('/set', name: 'set_webhook')]
    public function set(
        TelegramService $telegramService,
    ): JsonResponse {
        return $this->json([
            'message' => $telegramService->setWebhook(),
        ]);
    }

    #[Route('/delete', name: 'delete_webhook')]
    public function delete(
        TelegramService $telegramService,
    ): JsonResponse {
        return $this->json([
            'message' => $telegramService->deleteWebhook(),
        ]);
    }

    #[Route('/handle', name: 'handle_webhook')]
    public function handle(
        TelegramBotHandler         $telegramBotHandler,
        TelegramBotTrainingHandler $telegramBotTrainingHandler,
        TelegramService            $telegramService,
        LoggerInterface            $logger,
    ): JsonResponse {
        try {
            $telegramService->getTelegram()->handle();

            //START MENU
            $telegramBotHandler->handelStartMessage();

            // START ЗАРЯДКИ
            //обработка меню
//            $telegramBotHandler->handelMenuButtons();

            //платежи
            $telegramBotHandler->handlePaymentCard();
            $telegramBotHandler->PaymentProcessor();
            $telegramBotHandler->handelSuccessfulPayment();

            //Добавление постов в базу
            $telegramBotHandler->handelMassageId();
            // END ЗАРЯДКИ


            // START ТРЕНИРОВКИ
            //обработка меню
            $telegramBotTrainingHandler->handelMenuButtonsTrainings();

            //Формирование вывода подписок
//            $telegramBotTrainingHandler->handlePaymentMethod();

            // END ТРЕНИРОВКИ

            $result = 'ok';
        } catch (TelegramException|\Error $e) {
            $result = sprintf('MESSAGE: %s', $e->getMessage());
            $result .= sprintf('FILE: %s', $e->getFile());
            $result .= sprintf('LINE: %s', $e->getLine());

            $logger->critical(sprintf('MESSAGE: %s', $e->getMessage()));
            $logger->critical(sprintf('FILE: %s', $e->getFile()));
            $logger->critical(sprintf('LINE: %s', $e->getLine()));
        }

        return $this->json([
            'message' => $result,
        ]);
    }
}
