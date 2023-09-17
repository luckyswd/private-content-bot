<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cron-notification',
    description: 'Add a short description for your command',
)]
class CronNotificationCommand extends Command
{

    public function __construct(
        private UserRepository  $userRepository,
        string                  $name = null,
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $chatIds = [];
        $users = $this->userRepository->findAll();

        foreach ($users as $user) {
            if ($user->hasActiveSubscription()) {
                $chatIds[] = $user->getTelegramId();
            }
        }

        foreach ($chatIds as $chatId) {
            $token = $_ENV['BOT_TOKEN'];
            $text = "
Составишь мне компанию?😉
Жду тебя с новой зарядкой❤️
            ";

            $url = "https://api.telegram.org/bot$token/sendMessage";

            $data = [
                'chat_id' => $chatId,
                'text' => $text,
            ];

            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query($data),
                ],
            ];

            $context = stream_context_create($options);
            file_get_contents($url, false, $context);
        }

        $io->success('SUCCESS');

        return Command::SUCCESS;
    }
}
