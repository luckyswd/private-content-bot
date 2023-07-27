<?php

namespace App\Handler;

use App\Entity\Method;
use App\Entity\Price;
use App\Entity\Post;
use App\Entity\Rate;
use App\Entity\Subscription;
use App\Entity\User;
use App\Exception\SubscriptionExistException;
use App\Repository\MethodRepository;
use App\Repository\PostRepository;
use App\Repository\PriceRepository;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use App\Service\SettingService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Payments\SuccessfulPayment;
use Longman\TelegramBot\Request;

class TelegramBotHandler
{
    public function __construct(
        private RateRepository   $rateRepository,
        private SettingService   $settingService,
        private MethodRepository $methodRepository,
        private PriceRepository  $priceRepository,
        private TelegramService  $telegramService,
        private EntityManagerInterface  $entityManager,
        private UserRepository $userRepository,
        private PostRepository $postRepository,
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

    private function isSubscription() {
        $telegramId = TelegramService::getUpdate()?->getMessage()?->getChat()?->getId();
        $botName = TelegramService::getUpdate()->getBotUsername();

        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if ($user->hasActiveSubscription()) {
            return Request::sendMessage(
                [
                    'chat_id' => $telegramId,
                    'text' => 'Опалате ещё не истекла',
                ]
            );
        }
    }

    public function handlePaymentCard(): void {
        $telegramId = TelegramService::getUpdate()?->getCallbackQuery()?->getRawData()['from']['id'] ?? null;
        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if ($user && $user->hasActiveSubscription()) {
            $subscription = $user->getSubscription();
            $data = [
                'chat_id' =>  $telegramId,
                'text' => sprintf('У вас есть активная подписка до %s', $subscription->getLeftDateString()),
            ];

            Request::sendMessage($data);

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
            'get_next_video' => 'Вы выбрали Опцию 2',
            default => 'Неизвестная опция.',
        };
    }

    private function handleGetAllVideo(): void
    {
        $posts = $this->postRepository->findBy(['botName' => TelegramService::getUpdate()->getBotUsername()]);

        foreach ($posts as $key => $post) {
            $this->telegramService->forwardMessage($key + 1, getenv('ADMIN_GROUP_ID'), TelegramService::getUpdate()->getMessage()->getChat()->getId());
        }
    }

    private function handleGetNextVideo()
    {
        $telegramId = TelegramService::getUpdate()?->getMessage()?->getChat()?->getId();

        if (!$telegramId) {
            return;
        }

        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);




    }
}