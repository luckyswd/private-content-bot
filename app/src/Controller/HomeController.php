<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Repository\RateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home_index')]
    public function index(Request $request, RateRepository $rateRepository): Response
    {
        $providedUser = $request->server->get('PHP_AUTH_USER');
        $providedPass = $request->server->get('PHP_AUTH_PW');

        if ($providedUser !== getenv('HOME_USER') || $providedPass !== getenv('HOME_PASSWORD')) {
            return new Response(
                'Доступ запрещён',
                401,
                ['WWW-Authenticate' => 'Basic realm="Доступ только по паролю"']
            );
        }

        $resultRates = [];
        $rates = $rateRepository->findAll();

        foreach ($rates as $rate) {
            $resultRates[$rate->getId()] = SubscriptionType::getRUname($rate->getSubscriptionType()) . '. ' . $rate->getName();
        }

        return $this->render('home.html.twig', [
            'rates' => $resultRates,
            'token' => $this->generateToken(),
        ]);
    }

    #[Route('/api/home/data', name: 'app_home_user_data', methods: ['GET'])]
    public function userData(
        Request $request,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository
    ): JsonResponse {
        $token = $request->headers->get('token');

        if (!$this->isTokenValid($token)) {
            return new JsonResponse(['error' => 'Недействительный токен'], 401);
        }

        $telegramId = (int) $request->query->get('telegram_id') ?? null;

        if (!$telegramId) {
            return new JsonResponse(['error' => 'Не передан telegram_id'], 400);
        }

        $user = $userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user) {
            return new JsonResponse(['error' => 'Пользователь не найден'], 404);
        }

        $subscriptions = $subscriptionRepository->findBy(
            ['user' => $user],
            ['date' => 'DESC']
        );

        if (empty($subscriptions)) {
            return new JsonResponse(['error' => 'Пользователь не найден'], 404);
        }

        $activeSubscriptions = [];
        $latestEndDate = null;

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $endDate = $subscription->getDate()
                ->add($subscription->getRate()->getDuration());

            if ($user->hasActiveSubscription($subscription->getType())) {
                $activeSubscriptions[] = [
                    'type' => SubscriptionType::getRUname($subscription->getType()) . '. ' . $subscription->getRate()->getName(),
                    'endDate' => $endDate->format('d.m.Y H:i'),
                ];
            }

            if ($latestEndDate === null || $endDate > $latestEndDate) {
                $latestEndDate = $endDate;
            }
        }

        return new JsonResponse([
            'telegramId' => $telegramId,
            'activeSubscription' => count($activeSubscriptions) > 0,
            'countSubscription' => count($subscriptions),
            'currentSubscriptions' => $activeSubscriptions,
            'endSubscriptionDate' => $latestEndDate?->format('d.m.Y H:i'),
        ]);
    }

    #[Route('/api/home/data/add', name: 'app_home_user_data_add', methods: ['POST'])]
    public function userDataAdd(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, RateRepository $rateRepository): JsonResponse
    {
        $token = $request->headers->get('token');

        if (!$this->isTokenValid($token)) {
            return new JsonResponse(['error' => 'Недействительный токен'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $telegramId = $data['telegram_id'] ?? null;
        $rateId = $data['rate'] ?? 1;

        $user = $userRepository->findOneBy(['telegramId' => $telegramId]);

        if (!$user) {
            $user = new User();
        }

        $rate = $rateRepository->findOneBy(['id' => $rateId]);

        $user->setTelegramId($telegramId);
        $user->addSubscription($rate);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse();
    }

    private function generateToken(): string
    {
        return hash('sha256', getenv('HOME_USER') . getenv('HOME_PASSWORD'));
    }

    private function isTokenValid(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        return $token === $this->generateToken();
    }
}