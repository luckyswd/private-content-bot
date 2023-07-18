<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TelegramService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test/{id}', name: 'app_test')]
    public function index(User $user, TelegramService $telegramService): JsonResponse
    {

        dd($telegramService->getAllPosts());

//        dump(sprintf('получаем у пользователя с id: [%s] активные подписки',  $user->getId() ));
//        dump('активные- значит что текущая дата находится между датой начала и датой окончания подписки');
//        dump($user->getActiveSubscriptions());
//        dump('всегда будет возвращать ' . ArrayCollection::class);
//
//        dump('Получаем количество дней с момента начала подписки на текущий день');
//        dump('сколько дней, столько можно отправить постов пользователю');
//        dd(sprintf('Текущему пользователю можно отправить [%s] поста/ов', $user->getActiveSubscriptions()->first()->getAllowedCountPost()));


        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }
}
