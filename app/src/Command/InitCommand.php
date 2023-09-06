<?php

namespace App\Command;

use App\Entity\Method;
use App\Entity\Price;
use App\Entity\Rate;
use App\Entity\Setting;
use App\Entity\User;
use App\Repository\RateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init',
    description: 'init application command',
)]
class InitCommand extends Command
{
    public function __construct(
        private RateRepository $rateRepository,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private SubscriptionRepository $subscriptionRepository,
        string $name = null,
    )
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->initRates();
        $this->initUser();
        $this->initSubscription();
        $this->initSettings();
        $this->addMethods();

        $io->info('success');
        return Command::SUCCESS;
    }

    private function initRates():void {
        $rates = $this->rateRepository->findAll();

        if (count($rates) >= 2) {
            return;
        }

        $ratesArray = [
            [
                'name' => 'Подписка на 1 Месяц',
                'duration' => 'P1M',
                'price' => '1000',
            ],
            [
                'name' => 'Подписка на 1 Год',
                'duration' => 'P1Y',
                'price' => '11000',
            ],
            [
                'name' => 'Пожизненная подписка',
                'duration' => 'P20Y',
                'price' => '25000',
            ],
        ];

        foreach ($ratesArray as $item) {
            $rate = new Rate();
            $rate->setName($item['name']);

            $duration = new DateInterval($item['duration']);
            $rate->setDuration($duration);
            $this->addPrice($rate, $item['price']);
            $this->em->persist($rate);
        }

        $this->em->flush();
    }

    private function initUser():void {
        $users = $this->userRepository->findAll();

        if (count($users) >= 1) {
            return;
        }


        $user = new User();

        $user->setTelegramId(123456);

        $this->em->persist($user);
        $this->em->flush();
    }

    private function initSubscription():void
    {
        $subscriptions = $this->subscriptionRepository->findAll();

        if (count($subscriptions) >= 1) {
            return;
        }

        $user = $this->userRepository->findAll()[0];
        $rate = $this->rateRepository->findAll()[0];


        $user->setSubscription($rate);

        $this->em->flush();
    }

    private function initSettings():void {
        try {
            $firstMessage = new Setting('startMessage', 'Добрый день! Для покупки видео-курса зарядок на каждый день следуйте инструкциям бота👇');
            $this->em->persist($firstMessage);
            $this->em->flush();
        } catch (Exception) {}

        try {
            $methodMessage = new Setting('methodMessage', 'Выберите удобный метод оплаты: ');
            $this->em->persist($methodMessage);
            $this->em->flush();
        } catch (Exception) {}

        try {
            $methodMessage = new Setting('endMessage', 'Больше нету новых видео');
            $this->em->persist($methodMessage);
            $this->em->flush();
        } catch (Exception) {}
    }

    private function addPrice(
        Rate $rate,
        string $price,
    ): void {
        $priceRub = new Price();
        $priceRub->setPrice($price);
        $priceRub->setCurrency(Price::RUB_CURRENCY);

        $rate->addPrice($priceRub);
    }

    private function addMethods():void {
        $method1 = new Method();
        $method1->setName('YКасса');
        $method1->setToken('123');
        $this->em->persist($method1);
        $this->em->flush();
    }
}
