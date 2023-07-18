<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Rate;
use App\Repository\MethodRepository;
use App\Repository\PriceRepository;
use App\Repository\RateRepository;
use App\Service\SettingService;
use App\Service\TelegramService;
use App\Telegram\Commands\StartCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

class TelegramBotHandler
{
    private const PREFIX_RATE = 'rate-';
    private const PREFIX_METHOD = 'method-';
    private ?Rate $selectRate = null;

    public function __construct(
        private RateRepository $rateRepository,
        private SettingService $settingService,
        private MethodRepository $methodRepository,
        private PriceRepository $priceRepository,
    )
    {}

    public function handelStartMessage(): void {
        $update = TelegramService::getUpdate();

        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $message = $update->getMessage()->getText();

        if ($message !== '/start') {
            return;
        }

        $rates = $this->rateRepository->findAll();
        $inlineKeyboardButton = [];

        foreach ($rates as $rate) {
            $inlineKeyboardButton[] = new InlineKeyboardButton(
                [
                    'text' => $rate->getName(),
                    'callback_data' => sprintf('%s%s',self::PREFIX_RATE, $rate->getId()),
                ]
            );
        }

        $data = [
            'chat_id' =>  $update->getMessage()->getChat()->getId(),
            'text' => $this->settingService->getParameterValue('startMessage') ?? '',
            'reply_markup' => new InlineKeyboard($inlineKeyboardButton),
        ];

        Request::sendMessage($data);
    }

    public function handleRateButtons(): void {
        $update = TelegramService::getUpdate();

        if (!($update->getCallbackQuery() instanceof CallbackQuery)) {
            return;
        }

        $rates = $this->rateRepository->findAll();
        foreach ($rates as $rate) {
            if ($this->getCallbackData() === sprintf('%s%s',self::PREFIX_RATE, $rate->getId())) {
                $this->selectRate = $rate;
                $this->sendMethodsInlineKeyboard();
            }
        }
    }

    private function sendMethodsInlineKeyboard(): void {
        $methods = $this->methodRepository->findAll();

        $inlineKeyboardButton = [];

        foreach ($methods as $method) {
            $inlineKeyboardButton[] = new InlineKeyboardButton(
                [
                    'text' => $method->getName(),
                    'callback_data' => sprintf('%s%s',self::PREFIX_METHOD, $method->getId()),
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

    private function getCallbackData(): string {
        return TelegramService::getUpdate()->getCallbackQuery()->getData() ?? '';
    }

    private function getChatId(): string {
        return TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId() ?? '';
    }

    public function handlePaymentsMethods(): void {
        $rate = $this->rateRepository->findOneBy(['id' => 1]);

        if ($this->getCallbackData() === sprintf('%s%s', self::PREFIX_METHOD, Method::SBER_ID)) {
            $method = $this->methodRepository->findOneBy(['id' => Method::SBER_ID]);
            $price = $this->priceRepository->findOneBy(
                [
                    'rate' => $rate,
                    'currency' => 'RUB',
                ]
            );

            $prices = [
                [
                    'label' => 'Подписка',
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
                'currency' => 'RUB',
                'prices' => json_encode($prices),
            ];

            Request::sendInvoice($postfields);

            return;
        }

        if ($this->getCallbackData() === sprintf('%s%s', self::PREFIX_METHOD, Method::STRIPE_ID)) {
            $method = $this->methodRepository->findOneBy(['id' => Method::STRIPE_ID]);
            $price = $this->priceRepository->findOneBy(
                [
                    'rate' => $rate,
                    'currency' => 'USD',
                ]
            );

            $prices = [
                [
                    'label' => 'Подписка',
                    'amount' => $price->getPrice() * 100,
                ]
            ];
            var_dump($method->getToken());

            $postfields = [
                'chat_id' => $this->getChatId(),
                'provider_token' => $method->getToken(),
                'title' => sprintf('Подписка на %s', $rate->getName()),
                'description' => sprintf('Подписка на %s', $rate->getName()),
                'payload' => [
                    'unique_id' => $method->getId() . $rate->getId() . date('y-m-d-H-i-S'),
                    'provider_token' => $method->getToken(),
                ],
                'currency' => $price->getCurrency(),
                'prices' => json_encode($prices),
            ];

            Request::sendInvoice($postfields);

            return;
        }
    }
}