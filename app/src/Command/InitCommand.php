<?php

namespace App\Command;

use App\Entity\Course;
use App\Entity\Method;
use App\Entity\Price;
use App\Entity\Rate;
use App\Entity\Setting;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\RateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
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
        private CourseRepository $courseRepository,
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
        $this->initCourse();
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
            ['name' => '1 Месяц', 'duration' => 'P1M'],
            ['name' => 'Год', 'duration' => 'P1Y'],
            ['name' => 'Навсегда', 'duration' => 'P20Y'],
        ];

        foreach ($ratesArray as $item) {
            $rate = new Rate();
            $rate->setName($item['name']);

            $duration = new DateInterval($item['duration']);
            $rate->setDuration($duration);
            $this->addPrice($rate);
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


        $subscription = new Subscription();
        $this->em->persist($subscription);
        $subscription->setRate($rate);
        $subscription->setDate(new DateTimeImmutable());
        $user->addSubscription($subscription);

        $this->em->flush();
    }

    private function initCourse():void {
        $cources = $this->courseRepository->findAll();

        if (count($cources) >= 1) {
            return;
        }
        $course = new Course('-', 1234567);

        $this->em->persist($course);
        $this->em->flush();
    }

    private function initSettings():void {
        try {
            $firstMessage = new Setting('startMessage', 'Добрый день, для покупки курса следуйте инструкциям бота!');
            $this->em->persist($firstMessage);
            $this->em->flush();
        } catch (Exception) {}

        try {
            $methodMessage = new Setting('methodMessage', 'Выберите удобный метод оплаты: ');
            $this->em->persist($methodMessage);
            $this->em->flush();
        } catch (Exception) {}

    }

    private function addPrice(Rate $rate):void {
        $priceUsd = new Price();
        $priceUsd->setPrice(1000);
        $priceUsd->setCurrency(Price::USD_CURRENCY);

        $priceRub = new Price();
        $priceRub->setPrice(20000);
        $priceRub->setCurrency(Price::RUB_CURRENCY);

        $rate->addPrice($priceRub);
        $rate->addPrice($priceUsd);
    }

    private function addMethods():void {
        $method1 = new Method();
        $method1->setName('Сбербанк');
        $method1->setCurrency(Price::RUB_CURRENCY);
        $method1->setToken('123');
        $this->em->persist($method1);
        $this->em->flush();


        $method2 = new Method();
        $method2->setName('Stripe');
        $method2->setCurrency(Price::USD_CURRENCY);
        $method2->setToken('1234555');
        $this->em->persist($method2);
        $this->em->flush();
    }
}
