<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Post;
use App\Entity\Rate;
use App\Repository\MethodRepository;
use App\Repository\PriceRepository;
use App\Repository\RateRepository;
use App\Service\SettingService;
use App\Service\TelegramService;
use App\Telegram\Commands\StartCommand;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

class TelegramBotHandler
{
    private const PREFIX_RATE = 'rate-';

    public function __construct(
        private RateRepository   $rateRepository,
        private SettingService   $settingService,
        private MethodRepository $methodRepository,
        private PriceRepository  $priceRepository,
        private TelegramService  $telegramService,
        private EntityManagerInterface  $entityManager,
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
        $rates = $this->rateRepository->findAll();
        $inlineKeyboardButton = [];

        foreach ($rates as $rate) {
            $inlineKeyboardButton[] = new InlineKeyboardButton(
                [
                    'text' => $rate->getButtonName(),
                    'callback_data' => sprintf('%s%s', self::PREFIX_RATE, $rate->getId()),
                ]
            );
        }

        $data = [
            'chat_id' => $update->getMessage()->getChat()->getId(),
            'text' => $this->settingService->getParameterValue('startMessage') ?? '',
            'reply_markup' => new InlineKeyboard($inlineKeyboardButton),
        ];

        Request::sendMessage($data);
    }

    public function handleRateButtons(): void
    {
        $update = TelegramService::getUpdate();

        if (!($update->getCallbackQuery() instanceof CallbackQuery)) {
            return;
        }

        $rates = $this->rateRepository->findAll();
        foreach ($rates as $rate) {
            if ($this->getCallbackData() === sprintf('%s%s', self::PREFIX_RATE, $rate->getId())) {
                $this->sendMethodsInlineKeyboard($rate);
            }
        }
    }

    private function sendMethodsInlineKeyboard(
        Rate $rate,
    ): void
    {

        $methods = $this->methodRepository->findAll();

        $inlineKeyboardButton = [];

        foreach ($methods as $method) {
            $callbackData = [
                'rate' => $rate->getId(),
                'method' => $method->getId(),
            ];

            $inlineKeyboardButton[] = new InlineKeyboardButton(
                [
                    'text' => $method->getName(),
                    'callback_data' => json_encode($callbackData),
                ]
            );
        }

        $data = [
            'chat_id' => $this->getChatId(),
            'text' => $this->settingService->getParameterValue('methodMessage') ?? '',
            'reply_markup' => new InlineKeyboard($inlineKeyboardButton),
        ];

        Request::sendMessage($data);
    }

    private function getCallbackData(): string
    {
        return TelegramService::getUpdate()?->getCallbackQuery()?->getData() ?? '';
    }

    private function getChatId(): string
    {
        return TelegramService::getUpdate()?->getCallbackQuery()?->getFrom()?->getId() ?? '';
    }

    public function handlePaymentsMethods(): void
    {
        $callbackData = json_decode($this->getCallbackData());
        $rate = $this->rateRepository->findOneBy(['id' => $callbackData->rate ?? null]);
        $method = $this->methodRepository->findOneBy(['id' => $callbackData->method ?? null]);

        if (!$rate instanceof Rate && !$method instanceof Method) {
            return;
        }

        $price = $this->priceRepository->findOneBy(
            [
                'rate' => $rate,
                'currency' => $method->getCurrency(),
            ]
        );

        $prices = [
            [
                'label' => 'Подписка на ' . $rate->getName(),
                'amount' => $price->getPrice() * 100,
            ]
        ];

        $postfields = [
            'chat_id' => $this->getChatId(),
            'provider_token' => $method->getToken(),
            'title' => sprintf('Подписка на %s', $rate->getName()),
            'description' => sprintf('Подписка на %s', $rate->getName()),
            'payload' => [
                'unique_id' => $method->getId() . $rate->getId() . date('y-m-d-H-i-S'),
                'provider_token' => $method->getToken(),
            ],
            'currency' => $method->getCurrency(),
            'prices' => json_encode($prices),
        ];

        Request::sendInvoice($postfields);
    }

    public function handelPayments(): void
    {
        $preCheckoutQuery = TelegramService::getUpdate()->getPreCheckoutQuery();

        if (!$preCheckoutQuery) {
            return;
        }

        $preCheckoutQuery->answer(true);
    }

    public function handelSuccessfulPayment(): void
    {
        $isSuccessfulPayment = TelegramService::getUpdate()?->getMessage()?->getSuccessfulPayment() ?? null;

        if ($isSuccessfulPayment) {
            $this->telegramService->forwardMessage(12, getenv('ADMIN_GROUP_ID'), TelegramService::getUpdate()->getMessage()->getChat()->getId());
        }
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
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }
    }
}