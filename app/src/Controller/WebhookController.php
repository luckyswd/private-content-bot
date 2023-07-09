<?php

namespace App\Controller;

use App\Handler\TelegramBotHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/webhook')]
class WebhookController extends AbstractController
{
    #[Route('/set', name: 'set_webhook')]
    public function set(
        TelegramBotHandler $telegramBotHandler
    ): JsonResponse {
        return $this->json([
            'message' => $telegramBotHandler->setWebhook(),
        ]);
    }
    #[Route('/delete', name: 'delete_webhook')]
    public function delete(
        TelegramBotHandler $telegramBotHandler
    ): JsonResponse {
        return $this->json([
            'message' => $telegramBotHandler->deleteWebhook(),
        ]);
    }
    #[Route('/handle', name: 'handle_webhook')]
    public function handle(
        TelegramBotHandler $telegramBotHandler,
    ): JsonResponse {

        return $this->json([
            'message' => $telegramBotHandler->handleWebhook(),
        ]);
    }
}
