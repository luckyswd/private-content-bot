<?php

namespace App\Controller;

use App\Handler\TelegramBotHandler;
use App\Service\TelegramService;
use Longman\TelegramBot\Exception\TelegramException;
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
        TelegramBotHandler $telegramBotHandler,
        TelegramService $telegramService,
    ): JsonResponse {
        try {
            $telegramService->getTelegram()->handle();
            $telegramBotHandler->handelStartMessage();
            $telegramBotHandler->handleRateButtons();
            $telegramBotHandler->handlePaymentsMethods();

            $result = 'ok';
        } catch (TelegramException|\Error $e) {
            $result = $e->getMessage();
        }

        return $this->json([
            'message' => $result,
        ]);
    }
}
