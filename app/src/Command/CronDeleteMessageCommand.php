<?php

namespace App\Command;

use App\Entity\Message;
use App\Repository\UserRepository;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
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
        private TelegramService $telegramService,
        string $name = null
    )
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $client = new Client();
        $users = $this->userRepository->findAll();

        $token = $_ENV['BOT_TOKEN'];
        $urlDelete = "https://api.telegram.org/bot$token/deleteMessage";
        $urlSend = "https://api.telegram.org/bot$token/sendMessage";

        foreach ($users as $user) {
            $messages = $user->getMessages();
            $isSend = false;

            if ($messages->isEmpty()) {
                continue;
            }
            $io->progressStart($messages->count());

            /** @var Message $message */
            foreach ($messages->toArray() as $message) {
                $createDate = $message->getCreatedAt();
                $currentDateTime = new \DateTime();
                $timeDifference = $currentDateTime->diff($createDate);
                $hoursDifference = $timeDifference->h + ($timeDifference->days * 24);

                if ($hoursDifference <= 46) {
                    continue;
                }

                $deleteParams = [
                    'chat_id' => $user->getTelegramId(),
                    'message_id' => $message->getMessageId(),
                ];

                try {
                    $client->post($urlDelete, [
                        'form_params' => $deleteParams,
                    ]);

                    $isSend = true;

                    $this->entityManager->remove($message);
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    $this->entityManager->remove($message);
                    $this->entityManager->flush();
                }

                $io->progressAdvance();
            }

            $io->progressFinish();

            if ($isSend) {
                $sendParams = [
                    'chat_id' => $user->getTelegramId(),
                    'message_id' => $message->getMessageId(),
                    'parse_mode' => 'HTML',
                    'text' => "
<b>🎥 Видео было доступно 48 часов! 🎥</b>

Вы можете получить доступ к видео, нажав на кнопки ниже❗️",
                    'reply_markup' => json_encode($this->telegramService->startMenuButtons()),
                ];

                $client->post($urlSend, [
                    'form_params' => $sendParams,
                ]);
            }
        }

        $this->entityManager->flush();

        $io->success('SUCCESS');

        return Command::SUCCESS;
    }
}
