<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): JsonResponse
    {

        $jsonString = '{"provider_data":{"receipt":{"items":[{"description":"Название товара","quantity":"1","amount":{"value":"100.00","currency":"RUB"},"vat_code":1}],"customer":{"email":"mail@mail.ru"}}}}';

        $data = json_decode($jsonString, true);
        dd($data);
        $sub = $user->getSubscription();
        dd($sub->getNextDate());



        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }
}
