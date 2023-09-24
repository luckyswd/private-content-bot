<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Presentation;
use App\Entity\Price;
use App\Repository\MethodRepository;
use App\Repository\PresentationRepository;
use App\Service\TelegramService;
use Longman\TelegramBot\Entities\Payments\PreCheckoutQuery;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use stdClass;
use Symfony\Component\HttpKernel\KernelInterface;

class PaymentPresentationHandler
{
    public function __construct(
        private PresentationRepository $presentationRepository,
        private MethodRepository $methodRepository,
        private KernelInterface $kernel,
    ){}

    public function handlePresentationInfo(
        stdClass $callbackData,
    ): void {
        /** @var Presentation $presentation */
        $presentation = $this->presentationRepository->findOneBy(['id' => $callbackData->id ?? null]);

        $callbackData = [
            'type' => 'presentation',
            'id' => $presentation->getId(),
            'currency' => Price::RUB_CURRENCY,
        ];

        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => sprintf('Купить за %s₽', $presentation->getPrice()),
                'callback_data' => json_encode($callbackData),
            ],
        ];

        $imagePath = sprintf('%s%s', $this->kernel->getProjectDir(), $presentation->getImagePath());

        Request::sendPhoto([
            'chat_id' => TelegramService::getUpdate()?->getCallbackQuery()?->getFrom()?->getId() ?? '',
            'photo' => Request::encodeFile($imagePath),
            'parse_mode' => 'HTML',
            'caption' => $presentation->getDescription(),
            'reply_markup' => json_encode($inlineKeyboardButton),
        ]);

    }

    public function handlePresentation(
        stdClass $callbackData,
    ): void {
        $presentation = $this->presentationRepository->findOneBy(['id' => $callbackData->id ?? null]);
        $method = $this->methodRepository->findOneBy(['id' => Method::YKASSA_ID]);
        $currency = $callbackData->currency ?? null;

        if (!$presentation instanceof Presentation || !$method instanceof Method) {
            return;
        }

        $prices = [
            [
                'label' => $presentation?->getName(),
                'amount' => $presentation->getPrice() * 100,
            ]
        ];

        $postFields = [
            'chat_id' => TelegramService::getUpdate()?->getCallbackQuery()?->getFrom()?->getId() ?? '',
            'provider_token' => $method->getToken(),
            'title' => $presentation->getName(),
            'description' => $presentation->getName(),
            'payload' => [
                'unique_id' => date('y-m-d-H-i-S'),
                'provider_token' => $method->getToken(),
                'id' => $presentation->getId(),
                'type' => 'presentation',
            ],
            'currency' => $currency,
            'prices' => json_encode($prices),
        ];

        Request::sendInvoice($postFields);
    }

    public function paymentProcessor(
        PreCheckoutQuery $preCheckoutQuery,
    ): void {
        $preCheckoutQuery->answer(true);
    }

    /**
     * @throws TelegramException
     */
    public function handelSuccessfulPayment(): void {
        $invoicePayload = json_decode(TelegramBotHandler::getSuccessfulPayment()?->getInvoicePayload());
        /** @var Presentation $presentation */
        $presentation = $this->presentationRepository->findOneBy(['id' => $invoicePayload->id]);

        $filePath = sprintf('%s%s', getenv('BASE_URL'), $presentation->getFilePath());

        Request::sendMessage([
            'chat_id' => TelegramService::getUpdate()->getMessage()->getChat()->getId(),
            'parse_mode' => 'HTML',
            'text' => $filePath,
        ]);
    }
}