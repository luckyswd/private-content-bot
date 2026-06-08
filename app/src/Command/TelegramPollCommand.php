<?php

namespace App\Command;

use App\Service\TelegramService;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Получение апдейтов Telegram через long-polling.
 *
 * Зачем: РКН блокирует вебхук (входящее соединение Telegram → сервер виснет в
 * таймаут). При поллинге сервер сам забирает апдейты у Telegram (исходящее, по
 * IPv6 уже работает), поэтому входящие коннекты не нужны.
 *
 * Каждый апдейт переотправляется на собственный /webhook/handle (HOOK_URL), то
 * есть проходит ровно тот же путь, что и обычный вебхук — со свежим ядром и
 * репозиториями. Это исключает проблемы с кэшем сервисов внутри цикла.
 *
 * Запускать по cron раз в минуту (см. README/комментарий ниже).
 */
#[AsCommand(
    name: 'app:telegram:poll',
    description: 'Забирает апдейты Telegram через long-polling (обход блокировки вебхука РКН).',
)]
class TelegramPollCommand extends Command
{
    public function __construct(
        private readonly TelegramService $telegramService,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('duration', null, InputOption::VALUE_REQUIRED, 'Сколько секунд крутить цикл за один запуск (чуть меньше интервала cron)', '290')
            ->addOption('poll-timeout', null, InputOption::VALUE_REQUIRED, 'Таймаут long-polling в секундах (должен быть меньше HTTP-таймаута клиента)', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Весь код бота читает конфиг через getenv(), но Symfony Dotenv в CLI
        // кладёт значения только в $_ENV/$_SERVER. Пробрасываем их в getenv(),
        // чтобы TelegramService::getTelegram() (BOT_TOKEN/BOT_USERNAME) увидел их.
        $this->syncEnv();

        // Не даём двум cron-запускам работать одновременно.
        $lock = fopen($this->projectDir . '/var/telegram-poll.lock', 'c');

        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            $output->writeln('Поллер уже запущен — выходим.');

            return Command::SUCCESS;
        }

        // Инициализирует Telegram + Guzzle-клиент с force_ip_resolve=v6.
        $this->telegramService->getTelegram();

        // Переключаемся в режим polling. Без этого getUpdates вернёт 409 Conflict.
        // Вебхук удаляется БЕЗ drop_pending_updates — накопленные апдейты сохраняются.
        Request::deleteWebhook([]);

        $webhookUrl = getenv('HOOK_URL');
        $offsetFile = $this->projectDir . '/var/telegram-poll.offset';
        $offset = is_file($offsetFile) ? (int) file_get_contents($offsetFile) : null;

        $pollTimeout = (int) $input->getOption('poll-timeout');
        $deadline = time() + (int) $input->getOption('duration');
        $processed = 0;

        do {
            // Обрезаем таймаут опроса по остатку времени, чтобы последний
            // long-poll не вылез за дедлайн и процесс успел завершиться до
            // следующего запуска cron (иначе flock пропустит тик → пауза в боте).
            $remaining = $deadline - time();

            if ($remaining <= 0) {
                break;
            }

            $response = Request::getUpdates(array_filter([
                'offset' => $offset,
                'limit' => 100,
                'timeout' => min($pollTimeout, $remaining),
            ], static fn ($value) => $value !== null));

            if (!$response->isOk()) {
                $output->writeln('getUpdates error: ' . $response->getDescription());
                break;
            }

            /** @var Update[] $updates */
            $updates = $response->getResult();

            foreach ($updates as $update) {
                $this->dispatchToWebhook($webhookUrl, $update->getRawData());

                // offset = последний update_id + 1: подтверждает обработку этого
                // апдейта (Telegram больше не отдаст его) и пишется в файл, чтобы
                // следующий запуск не обработал повторно.
                $offset = $update->getUpdateId() + 1;
                file_put_contents($offsetFile, (string) $offset);
                $processed++;
            }
        } while (time() < $deadline);

        flock($lock, LOCK_UN);
        fclose($lock);

        $output->writeln(sprintf('Обработано апдейтов: %d', $processed));

        return Command::SUCCESS;
    }

    /**
     * Делает переменные из $_ENV/$_SERVER доступными через getenv().
     */
    private function syncEnv(): void
    {
        foreach ($_ENV + $_SERVER as $name => $value) {
            if (is_string($value) && getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }

    /**
     * Переотправляет сырой апдейт на собственный вебхук-эндпоинт по HTTP,
     * чтобы каждый апдейт обрабатывался в изолированном запросе.
     */
    private function dispatchToWebhook(string $url, array $rawUpdate): void
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($rawUpdate, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
