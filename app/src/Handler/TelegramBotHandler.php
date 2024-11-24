<?php

namespace App\Handler;

use App\Entity\Post;
use App\Entity\PostTraining;
use App\Entity\TrainingCatalog;
use App\Enum\SubscriptionType;
use App\Repository\PostRepository;
use App\Repository\TrainingCatalogRepository;
use App\Repository\UserRepository;
use App\Service\SettingService;
use App\Service\TelegramMessageService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\Payments\SuccessfulPayment;

class TelegramBotHandler
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
        private TrainingCatalogRepository $trainingCatalogRepository,
    ){}

    public function handelStartMessage(): void {
        $update = TelegramService::getUpdate();

        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $chatId = $update->getMessage()?->getChat()?->getId();

        $message = $update->getMessage()?->getText() ?? '';

        if ($message === '/warmup') {
            $this->telegramMessageService->sendCharges($chatId);

            return;
        }

        if ($message === '/training') {
            $this->telegramMessageService->sendTrainings($chatId);

            return;
        }

        if ($message !== '/start') {
            return;
        }

        $this->telegramMessageService->sendStartMenu($chatId);
    }

    public function PaymentProcessor(): void {
        $preCheckoutQuery = TelegramService::getUpdate()->getPreCheckoutQuery();

        if (!$preCheckoutQuery) {
            return;
        }

        $invoicePayload = json_decode($preCheckoutQuery->getInvoicePayload());

        match ($invoicePayload->type) {
            'rate' =>  $this->paymentSubscriptionHandler->paymentProcessor($preCheckoutQuery),
            'presentation' =>  $this->paymentPresentationHandler->paymentProcessor($preCheckoutQuery),
        };
    }

    public function handelSuccessfulPayment(): void {
        if (!self::getSuccessfulPayment()) {
            return;
        }

        $invoicePayload = json_decode(self::getSuccessfulPayment()->getInvoicePayload());

        match ($invoicePayload->type) {
            'rate' =>  $this->paymentSubscriptionHandler->handelSuccessfulPaymentBySubscriptionType(),
            'presentation' =>  $this->paymentPresentationHandler->handelSuccessfulPayment(),
        };
    }

    public static function getSuccessfulPayment(): ?SuccessfulPayment {
        return TelegramService::getUpdate()?->getMessage()?->getSuccessfulPayment() ?? null;
    }

    public function handelMassageId(): void {
        $update = TelegramService::getUpdate();

        if ($update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $message = $update->getMessage();

        if (!$message)  {
            return;
        }

        if ($message->getChat()->getId() == getenv('ADMIN_GROUP_ID')) {
            $postId = $message->getMessageId();
            $post = new Post();
            $post->setMessageId($postId);
            $post->setBotName(TelegramService::getUpdate()->getBotUsername());
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }

        if ($message->getChat()->getId() == getenv('ADMIN_TRAINING_GROUP_ID')) {
            $video = $message->getVideo();

            if (!$video) {
                return;
            }

            $parseFileName = explode(' ', $video->getFileName());
            $mainCategory = $parseFileName[0] ?? null;
            $subCategory = $parseFileName[1] ?? null;
            $algorithmNumber = $parseFileName[2] ?? null;
            $order = $parseFileName[3] ?? null;

            if (!$mainCategory || !$subCategory || !$algorithmNumber || !$order) {
                return;
            }

            $order = str_replace('.mp4', '', $order);
            $postId = $message->getMessageId();
            $mainCategory = $this->trainingCatalogRepository->findOneBy(['name' => SubscriptionType::getRUnameByStringType($mainCategory)]);
            $subCategory = $this->trainingCatalogRepository->findOneBy(
                [
                    'name' => TrainingCatalog::MAPPING[$subCategory],
                    'subCatalog' => $mainCategory,
                ]
            );

            $postTraining = (new PostTraining())
                ->setMessageId($postId)
                ->setBotName(TelegramService::getUpdate()->getBotUsername())
                ->setAlgorithmNumber((int)$algorithmNumber)
                ->setCatalog($subCategory)
                ->setOrder((int)$order)
                ->setCreatedAt(new \DateTime());

            $this->entityManager->persist($postTraining);
            $this->entityManager->flush();
        }
    }

    public function handleAllActionButtons(): void {
        $update = TelegramService::getUpdate();

        if (!$update->getCallbackQuery() instanceof CallbackQuery) {
            return;
        }

        $data = $update->getCallbackQuery()->getData();
        $data = json_decode($data);

        if (!property_exists($data, 'type')) {
            return;
        }

        $chatId = $update->getCallbackQuery()->getFrom()->getId();

        match ($data->type) {
            //Обработка выборка подписки для зарядок
            'rate' =>  $this->paymentSubscriptionHandler->handleSubscription($data),

            //Обработка презентаций
            'presentationInfo' =>  $this->paymentPresentationHandler->handlePresentationInfo($data),
            'presentation' =>  $this->paymentPresentationHandler->handlePresentation($data),

            //Получение след. и пред. видео для зарядок
            'get_all_video' => $this->handleGetAllVideo(),
            'get_next_video' => $this->handleGetNextVideo(),

            //Получение цикл тренировок или прошлый
            'nextCycle' => $this->handleNextCycleVideo(),
            'prevCycle' => $this->handlePrevCycleVideo(),

            //Список зарядок
            'chargers' => $this->telegramMessageService->sendCharges($chatId),

            //Обработка меню каталога для тренировок
            'backMenu' => $this->telegramMessageService->sendStartMenu($chatId),
            'backCatalog', 'training_programs' => $this->telegramMessageService->sendTrainings($chatId),
            'catalog' => $this->telegramMessageService->sendTrainings($chatId, $data->id ?? null),

            default => ''
        };
    }

    private function handleGetAllVideo(): void
    {
        $telegramId = TelegramService::getUpdate()?->getCallbackQuery()?->getRawData()['from']['id'] ?? null;
        $user = $this->userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user || !$user->hasActiveSubscription()) {
            $this->telegramMessageService->sendStartMenu($telegramId);

            return;
        }

        $subscription = $user->getSubscriptionByType();
        $step = $subscription->getStep();
        $allowedCountPost = $subscription->getAllowedCountPost();

        $posts = $this->postRepository->getAllPostsByBotName(
            TelegramService::getUpdate()->getBotUsername(),
            min($step, $allowedCountPost),
        );

        foreach ($posts as $key => $post) {
            $this->telegramService->forwardMessage(
                $key + 1,
                getenv('ADMIN_GROUP_ID'),
                TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId(),
                $key === array_key_last($posts),
            );
        }
    }

    private function handleGetNextVideo(): void {
        $callBackQuery = TelegramService::getUpdate()->getCallbackQuery();

        if (!$callBackQuery) {
            return;
        }

        $telegramId = $callBackQuery->getFrom()->getId();
        $botUsername = $callBackQuery->getBotUsername();

        if (!$telegramId) {
            return;
        }

        $user = $this->userRepository->getCacheUser($telegramId);
        $subscription = $user->getSubscriptionByType();
        $allowedCountPost = $subscription->getAllowedCountPost();
        $step = $subscription->getStep();

        if (!$user->hasActiveSubscription()) {
            $this->telegramMessageService->sendStartMenu($telegramId);

            return;
        }

        if ($step >= $this->telegramService->getCountAllPostByBotName($botUsername)) {
            $this->telegramMessageService->sendEndMessage($this->settingService->getParameterValue('endMessage'));

            return;
        }

        if ($allowedCountPost <= $step) {
            $this->telegramMessageService->sendDeniedReceiptMessage(SubscriptionType::CHARGERS);

            return;
        }

        $subscription->setStep($step + 1);

        $this->telegramService->forwardMessage(
            $step + 1,
            getenv('ADMIN_GROUP_ID'),
            TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId(),
        );

        $this->entityManager->flush();
    }

    private function handleNextCycleVideo(): void {
        $callBackQuery = TelegramService::getUpdate()->getCallbackQuery();
        $data = $callBackQuery->getData();
        $data = json_decode($data);

        $telegramId = $callBackQuery->getFrom()->getId();

        $subscriptionType = SubscriptionType::getSubscriptionTypeByValue($data->subscription_id);
        $catalog = $this->trainingCatalogRepository->findOneBy(['id' => $data->cat_id]);

        $user = $this->userRepository->getCacheUser($telegramId);
        $subscription = $user->getSubscriptionByType($subscriptionType);

        $allowedCountPost = $subscription->getAllowedCountPost();
        $step = $subscription->getStep();

        if (!$user->hasActiveSubscription($subscriptionType)) {
            $this->telegramMessageService->sendStartMenu($telegramId);

            return;
        }

        $maxAlgorithmCount = $catalog->getMaxAlgorithmCount();

        if ($step >= $maxAlgorithmCount) {
            $algorithmNumber = $step % $maxAlgorithmCount + 1;
        } elseif ($step !== 1) {
            $algorithmNumber = $step;
            $algorithmNumber++;
        } else {
            $algorithmNumber = $step;
        }

        if ($allowedCountPost <= $step && $step !== 1) {
            $this->telegramMessageService->sendDeniedReceiptMessage($subscriptionType);

            return;
        }

        $subscription->setStep($step + 1);

        $this->telegramService->forwardMessageTraining(
            algorithmNumber: $algorithmNumber,
            catalog: $catalog,
            chatIdTo: TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId()
        );

        $this->entityManager->flush();
    }

    private function handlePrevCycleVideo(): void {
        $callBackQuery = TelegramService::getUpdate()->getCallbackQuery();
        $data = $callBackQuery->getData();
        $data = json_decode($data);

        $telegramId = $callBackQuery->getFrom()->getId();

        $subscriptionType = SubscriptionType::getSubscriptionTypeByValue($data->subscription_id);
        $catalog = $this->trainingCatalogRepository->findOneBy(['id' => $data->cat_id]);

        $user = $this->userRepository->getCacheUser($telegramId);
        $subscription = $user->getSubscriptionByType($subscriptionType);

        $step = $subscription->getStep();

        if (!$user->hasActiveSubscription($subscriptionType)) {
            $this->telegramMessageService->sendStartMenu($telegramId);

            return;
        }

        $maxAlgorithmCount = $catalog->getMaxAlgorithmCount();

        if ($step > $maxAlgorithmCount) {
            $algorithmNumber = $step % $maxAlgorithmCount;

            if ($algorithmNumber === 1) {
                $algorithmNumber = $maxAlgorithmCount;
            } else {
                $algorithmNumber--;
            }
        } else {
            $algorithmNumber = $step;
            $algorithmNumber--;
        }

        $this->telegramService->forwardMessageTraining(
            algorithmNumber: $algorithmNumber,
            catalog: $catalog,
            chatIdTo: TelegramService::getUpdate()->getCallbackQuery()->getFrom()->getId()
        );
    }
}