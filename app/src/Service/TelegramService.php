<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\Price;
use App\Entity\TrainingCatalog;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Handler\TelegramMessageHandler;
use App\Repository\PostRepository;
use App\Repository\PostTrainingRepository;
use App\Repository\PresentationRepository;
use App\Repository\UserRepository;
use DateTime;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramService
{
    private ?Telegram $telegram = null;
    public function __construct(
        private PostRepository $postRepository,
        private TelegramMessageHandler $telegramMessageHandler,
        private PresentationRepository $presentationRepository,
        private PostTrainingRepository $postTrainingRepository,
        private UserRepository $userRepository,
    )
    {}
    public function setWebhook(): string|null {
        try {
            $result = $this->getTelegram()->setWebhook(getenv('HOOK_URL'));

            if ($result->isOk()) {
                return $result->getDescription();
            }

            return null;
        } catch (TelegramException $e) {
            return $e->getMessage();
        }
    }

    public function deleteWebhook(): string {
        try {
            $result = $this->getTelegram()->deleteWebhook();

            return $result->getDescription();
        } catch (TelegramException $e) {
            return $e->getMessage();
        }
    }

    public function getTelegram(): Telegram {
        if (!$this->telegram) {
            $this->telegram = new Telegram(getenv('BOT_TOKEN'), getenv('BOT_USERNAME'));
        }

        return $this->telegram;
    }

    public static function getUpdate(): Update {
        $input = Request::getInput();
        $post = json_decode($input, true);

        return new Update($post, getenv('BOT_USERNAME'));
    }

    public function getPostById(
        int $orderNumber,
    ): ?Post {
        $posts = $this->postRepository->findBy(['botName' => TelegramService::getUpdate()->getBotUsername()]);

        foreach ($posts as $key => $post) {
            if (($key + 1) === $orderNumber) {
                return $post;
            }
        }

        return null;
    }

    public function forwardMessageTraining(
        int $algorithmNumber,
        TrainingCatalog $catalog,
        string $chatIdTo,
        bool $isLast = false,
    ): void {
        if (!$chatIdTo) {
            return;
        }

        $posts = $this->postTrainingRepository->findBy(['catalog' => $catalog, 'algorithmNumber' => $algorithmNumber]);

        foreach ($posts as $post) {
            $response = Request::copyMessage([
                'chat_id' => $chatIdTo,
                'from_chat_id' => getenv('ADMIN_TRAINING_GROUP_ID'),
                'message_id' => $post->getMessageId() ?? '',
                'protect_content' => true,
            ]);

            $this->telegramMessageHandler->addMessage($response, $chatIdTo);
        }

        $subscriptionType = !$catalog->getSubscriptionType()
            ? $catalog->getSubCatalog()->getSubscriptionType()
            : $catalog->getSubscriptionType();

        Request::sendMessage(
            [
                'chat_id' => $chatIdTo,
                'text' => $this->getMessageForNextVideo($subscriptionType),
                'reply_markup' => json_encode($this->getButtonForTrainingVideo($catalog, $subscriptionType, $chatIdTo)),
                'parse_mode' => 'HTML',
            ]
        );
    }

    public function forwardMessage(
        int $orderNumber,
        int $groupIdFrom,
        string $chatIdTo,
        bool $isLast = true,
    ): void {
        $post = $this->getPostById($orderNumber);

        if (!$post || !$groupIdFrom || !$chatIdTo) {
            return;
        }

        $response = Request::copyMessage([
            'chat_id' => $chatIdTo,
            'from_chat_id' => $groupIdFrom,
            'message_id' => $post->getMessageId() ?? '',
            'protect_content' => true,
            'reply_markup' => $isLast ? json_encode($this->getButtonForChargersVideo()) : '',
        ]);

        $this->telegramMessageHandler->addMessage($response, $chatIdTo);
    }

    public function getCountAllPostByBotName(
        string $botName,
    ): int {
        $posts = $this->postRepository->findBy(['botName' => $botName]);

        return count($posts);
    }

    public function getCountAllPostTrainingByCatalog(
        string $botName,
        TrainingCatalog $catalog,
        int $algorithmNumber,
    ): int {
        $posts = $this->postTrainingRepository->findBy([
            'botName' => $botName,
            'algorithmNumber' => $algorithmNumber,
            'catalog' => $catalog
        ]);

        return count($posts);
    }

    public function startMenuButtons(): array {
        $presentations = $this->presentationRepository->findAll();

        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => 'Зарядки',
                'callback_data' => json_encode(['type' => 'chargers']),
            ],
        ];

        foreach ($presentations as $presentation) {
            $callbackData = [
                'type' => 'presentationInfo',
                'id' => $presentation->getId(),
                'currency' => Price::RUB_CURRENCY,
            ];

            if ($presentation->getId() === 5) {
                $text = sprintf("%s за %s ₽ вместо 2100₽", $presentation->getName(), $presentation->getPrice());
            } else {
                $text = sprintf("%s за %s ₽", $presentation->getName(), $presentation->getPrice());
            }

            $inlineKeyboardButton['inline_keyboard'][] = [
                [
                    'text' => $text,
                    'callback_data' => json_encode($callbackData),
                ],
            ];
        }

        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => 'Программы силовых тренировок',
                'callback_data' => json_encode(['type' => 'training_programs']),
            ],
        ];

        return $inlineKeyboardButton;
    }

    public function getButtonForTrainingVideo(
        TrainingCatalog $catalog,
        SubscriptionType $subscriptionType,
        string $chatIdTo,
    ): array {
        $user = $this->userRepository->getCacheUser($chatIdTo);
        $subscription = $user->getSubscriptionByType($subscriptionType);

        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => 'Получить следующий цикл тренировок',
                'callback_data' => json_encode([
                    'type' => 'nextCycle',
                    'subscription_id' => $subscriptionType->value,
                    'cat_id' => $catalog->getId(),
                ]),
            ]
        ];

        if ($subscription->getStep() > 1 || ($subscription->getDate() && (new DateTime())->diff($subscription->getDate())->days > 5)) {
            $inlineKeyboardButton['inline_keyboard'][] = [
                [
                    'text' => 'Получить предыдущий цикл тренировок',
                    'callback_data' => json_encode([
                        'type' => 'prevCycle',
                        'subscription_id' => $subscriptionType->value,
                        'cat_id' => $catalog->getId(),
                    ]),
                ]
            ];
        }

        return $inlineKeyboardButton;
    }

    public function getButtonForChargersVideo(): array {
        $inlineKeyboardButton = [];

        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => 'Получить следующее видео',
                'callback_data' => json_encode(['type' => 'get_next_video']),
            ]
        ];

        $inlineKeyboardButton['inline_keyboard'][] = [
            [
                'text' => 'Получить все предыдущие видео',
                'callback_data' => json_encode(['type' => 'get_all_video']),
            ]
        ];

        return $inlineKeyboardButton;
    }

    public function getMessageForNextVideo(SubscriptionType $subscriptionType): string {
        /** @var User $user */
        $user = $this->userRepository->getCacheUser(TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId());

        return sprintf('Следующее видео станет доступно после %s', $user->getSubscriptionByType($subscriptionType)->getNextDate());
    }
}