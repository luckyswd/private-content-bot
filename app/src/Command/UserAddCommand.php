<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\RateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user-add',
    description: 'Adds a new user with a specified telegramId and rate ID',
)]
class UserAddCommand extends Command
{
    public function __construct(
        private RateRepository $rateRepository,
        private EntityManagerInterface $entityManager,
        string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('telegramId', null, InputOption::VALUE_REQUIRED, 'Telegram ID of the user')
            ->addOption('rate', null, InputOption::VALUE_REQUIRED, 'Rate ID to subscribe the user to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $telegramId = $input->getOption('telegramId');
        $rateId = $input->getOption('rate');

        if (!$telegramId || !$rateId) {
            $io->error('Both --telegramId and --rate options are required.');
            return Command::FAILURE;
        }

        $rate = $this->rateRepository->findOneBy(['id' => $rateId]);

        if (!$rate) {
            $io->error("Rate with ID $rateId not found.");
            return Command::FAILURE;
        }

        $user = new User();
        $user->setTelegramId($telegramId);
        $user->addSubscription($rate);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success("User with Telegram ID $telegramId successfully added with rate ID $rateId.");

        return Command::SUCCESS;
    }
}
