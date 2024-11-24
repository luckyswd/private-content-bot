<?php

namespace App\Command;

use App\Repository\PostTrainingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:create-post-training',
    description: 'Add a short description for your command',
)]
class CreatePostTrainingCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    public function __construct(
        private PostTrainingRepository $postTrainingRepository,
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
        string $name = null,
    )
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dirs = [
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для зала/На верх тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для зала/На все тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для зала/На низ тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для дома без инвентаря или с гантелями/На верх тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для дома без инвентаря или с гантелями/На все тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для дома без инвентаря или с гантелями/На низ тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для дома с резинками/На верх тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для дома с резинками/На все тела',
            $this->kernel->getProjectDir() . '/' . 'post_training_content' . '/Для дома с резинками/На низ тела',
        ];

        $postsTraining = $this->postTrainingRepository->findAll();

        foreach ($postsTraining as $post) {
            $this->entityManager->remove($post);
        }

        $this->entityManager->flush();

        foreach ($dirs as $dir) {
            $this->handle($dir);

            $io->info('end ' . $dir);
        }


        return Command::SUCCESS;
    }

    private function handle(string $dir): void {
        $token = $_ENV['BOT_TOKEN'];
        $chatId = $_ENV['ADMIN_TRAINING_GROUP_FOR_FORWARD_ID'];
        $url = "https://api.telegram.org/bot$token/sendVideo";

        $finder = new Finder();
        $finder->files()->in($dir);

        if (!$finder->hasResults()) {
            return;
        }

        $finder->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
            $pattern = '/\|(\d+)\|(\d+)\.(mp4|txt)$/';

            preg_match($pattern, $a->getFilename(), $matchesA);
            preg_match($pattern, $b->getFilename(), $matchesB);

            $groupA = $matchesA[1] ?? 0;
            $numberA = $matchesA[2] ?? 0;
            $extensionA = $matchesA[3] ?? '';

            $groupB = $matchesB[1] ?? 0;
            $numberB = $matchesB[2] ?? 0;

            if ($groupA !== $groupB) {
                return $groupA <=> $groupB;
            }

            if ($numberA !== $numberB) {
                return $numberA <=> $numberB;
            }

            return $extensionA === 'mp4' ? -1 : 1;
        });

        $results = [];

        $i = 0;

        foreach ($finder as $file) {
            $ext = $file->getExtension();

            if ($ext === 'mp4') {
                $results[$i]['videoPath'] = $file->getRealPath();
            }

            if ($ext === 'txt') {
                $results[$i]['text'] = $file->getContents();
                $i++;
            }
        }

        foreach ($results as $result) {
            $videoPath = $result['videoPath'];

            $data = [
                'chat_id' => $chatId,
                'caption' => $result['text'],
                'parse_mode' => 'HTML',
            ];

            if (function_exists('curl_file_create')) {
                $data['video'] = curl_file_create($videoPath);
            } else {
                $data['video'] = '@' . realpath($videoPath);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            curl_close($ch);
        }
    }
}
