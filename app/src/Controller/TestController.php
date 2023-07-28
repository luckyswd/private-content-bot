<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test/{id}', name: 'app_test')]
    public function index(User $user, SubscriptionRepository $subscriptionRepository): JsonResponse
    {

        $sub = $user->getSubscription();
        dd($sub->getAllowedCountPost());



        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }
}
