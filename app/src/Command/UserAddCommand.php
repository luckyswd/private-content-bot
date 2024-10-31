<?php

namespace App\Command;

use App\Entity\User;
use App\Handler\TelegramBotChargersHandler;
use App\Repository\RateRepository;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user-add',
    description: 'Add a short description for your command',
)]
class UserAddCommand extends Command
{

    public function __construct(
        private RateRepository $rateRepository,
        private EntityManagerInterface $entityManager,
        string $name = null,
    )
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rate = $this->rateRepository->findOneBy(['id' => 2]);

        $user = new User();
        $user->setTelegramId(6632041688);
        $user->addSubscription($rate);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('SUCCESS');

        return Command::SUCCESS;
    }
}
