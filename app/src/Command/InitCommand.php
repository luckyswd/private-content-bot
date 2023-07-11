<?php

namespace App\Command;

use App\Entity\Rate;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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

        return Command::SUCCESS;
    }

    private function initRates():void {
        $ratesArray = [
            ['name' => 'Месяц', 'price' => '100', 'duration' => 'P1M'],
            ['name' => 'Год', 'price' => '1000', 'duration' => 'P1Y'],
        ];

        foreach ($ratesArray as $item) {
            $rate = new Rate();
            $rate->setName($item['name']);
            $rate->setPrice($item['price']);

            $duration = new DateInterval($item['duration']);
            $rate->setDuration($duration);

            $this->em->persist($rate);
        }

        $this->em->flush();
    }

    private function initUser():void {
        $user = new User();

        $user->setTelegramId(123456);

        $this->em->persist($user);
        $this->em->flush();
    }

    private function initSubscription():void
    {
        $user = $this->userRepository->findAll()[0];
        $rate = $this->rateRepository->findAll()[0];


        $subscription = new Subscription();
        $this->em->persist($subscription);
        $subscription->setRate($rate);
        $subscription->setDate(new DateTimeImmutable());
        $user->addSubscription($subscription);

        $this->em->flush();
    }
}
