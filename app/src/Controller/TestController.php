<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Repository\SubscriptionRepository;
use App\Repository\TrainingCatalogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findOneBy(['id' => 4149]);
        $subscriptions = $user->getSubscriptions();

        $result = '';

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $result .= sprintf(PHP_EOL . 'У вас есть активная подписка до %s для %s',
                $user->getSubscriptionByType($subscription->getType())?->getLeftDateString(),
                $subscription->getType()->value,
            );
        }
        dd($result);

        dd($user->hasActiveSubscription());
        dd(SubscriptionType::CHARGERS->value);
        $catalog = $catalogRepository->findOneBy(['id' => 19]);

        dd($catalog->getSubCatalog()->getId());
        dd($catalogRepository->getNextCategories($catalog));
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);

        $jsonString = '{"provider_data":{"receipt":{"items":[{"description":"Название товара","quantity":"1","amount":{"value":"100.00","currency":"RUB"},"vat_code":1}],"customer":{"email":"mail@mail.ru"}}}}';

        $data = json_decode($jsonString, true);
        dd($data);
        $sub = $user->getSubscriptionByType();
        dd($sub->getNextDate());



        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }
}
