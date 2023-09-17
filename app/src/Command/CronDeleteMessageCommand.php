<?php

namespace App\Command;

use App\Entity\Message;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cron-delete-message',
    description: 'Add a short description for your command',
)]
class CronDeleteMessageCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        string $name = null
    )
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $users = $this->userRepository->findAll();

        $token = $_ENV['BOT_TOKEN'];
        $url = "https://api.telegram.org/bot$token/deleteMessage";

        foreach ($users as $user) {
            $messages = $user->getMessages();

            if ($messages->isEmpty()) {
                continue;
            }

            /** @var Message $message */
            foreach ($messages->toArray() as $message) {
                $createDate = $message->getCreatedAt();
                $currentDateTime = new \DateTime();
                $timeDifference = $currentDateTime->diff($createDate);
                $hoursDifference = $timeDifference->h + ($timeDifference->days * 24);

                if ($hoursDifference <= 1) {
                    continue;
                }

                $data = [
                    'chat_id' => $user->getTelegramId(),
                    'message_id' => $message->getMessageId(),
                ];

                $options = [
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/x-www-form-urlencoded',
                        'content' => http_build_query($data),
                    ],
                ];

                try {
                    $context = stream_context_create($options);
                    file_get_contents($url, false, $context);
                    $this->entityManager->remove($message);
                } catch (\Exception $e) {
                    $this->entityManager->flush();
                }
            }
        }

        $this->entityManager->flush();

        $io->success('SUCCESS');

        return Command::SUCCESS;
    }
}
