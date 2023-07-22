<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Price;
use App\Entity\Post;
use App\Entity\Rate;
use App\Entity\Subscription;
use App\Entity\User;
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
            $callbackData = [
                'rate' => $rate->getId(),
                'currency' => Price::RUB_CURRENCY,
            ];

            $inlineKeyboardButton[] = new InlineKeyboardButton(
                [
                    'text' => $rate?->getName(),
                    'callback_data' => json_encode($callbackData),
                ]
            );
        }

        $data = [
            'chat_id' =>  $update->getMessage()->getChat()->getId(),
            'text' => $this->getStartMessage(),
            'reply_markup' => new InlineKeyboard($inlineKeyboardButton),
            'parse_mode' => 'Markdown',
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

    public function handlePaymentCard(): void {
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
                'unique_id' =>json_encode(['rate'=> $rate->getId(), 'date' => date('y-m-d-H-i-S')]),
                'provider_token' => $method->getToken(),
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

        $preCheckoutQuery->answer(true);
    }

    public function handelSuccessfulPayment(): void
    {
        $isSuccessfulPayment = TelegramService::getUpdate()?->getMessage()?->getSuccessfulPayment() ?? null;
        if (!$isSuccessfulPayment) {
            return;
        }
        var_dump($isSuccessfulPayment);
//        $this->addUser();
        $this->telegramService->forwardMessage(1, getenv('ADMIN_GROUP_ID'), TelegramService::getUpdate()->getMessage()->getChat()->getId());
    }

    public function getStartMessage(): string
    {
        $result = '';
        $startMessageText = $this->settingService->getParameterValue('startMessage') ?? '';
        $rates = $this->rateRepository->findAll();
        $seperator = 'или';

        foreach ($rates as $rate) {
            $prices = $rate?->getPrices();
            $result .= $rate?->getName() . ' -';
            $lastKeyPrices = array_key_last($prices->toArray());

            /** @var Price $price */
            foreach ($prices as $key => $price) {
                $result .= sprintf(' %s %s %s', $price?->getPrice(), $price->getCurrency(), $key !== $lastKeyPrices ? $seperator : '');
            }

            $result .= " \n";
        }

        return $result;
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

    private function addUser(
      Rate $rate,
    ): void
    {
        $user = new User();
        $user->setTelegramId(TelegramService::getUpdate()->getMessage()->getChat()->getId());
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setRate($rate);
        $this->entityManager->persist($user);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }
}