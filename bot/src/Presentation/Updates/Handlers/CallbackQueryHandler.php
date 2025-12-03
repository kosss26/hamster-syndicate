<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Updates\Handlers;

use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use Symfony\Contracts\Cache\CacheInterface;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\GameSessionService;
use QuizBot\Application\Services\StoryService;
use QuizBot\Application\Services\AdminService;
use QuizBot\Application\Services\HintService;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\GameSession;
use QuizBot\Domain\Model\StoryStep;
use QuizBot\Domain\Model\StoryChapter;
use QuizBot\Domain\Model\StoryProgress;
use QuizBot\Domain\Model\StoryQuestion;
use QuizBot\Domain\Model\StoryQuestionAnswer;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelRound;
use QuizBot\Presentation\Updates\Handlers\Concerns\SendsDuelMessages;
use QuizBot\Domain\Model\TrueFalseFact;

final class CallbackQueryHandler
{
    use SendsDuelMessages;

    private ClientInterface $telegramClient;

    private Logger $logger;

    private CacheInterface $cache;

    private UserService $userService;

    private DuelService $duelService;

    private GameSessionService $gameSessionService;

    private StoryService $storyService;

    private \QuizBot\Application\Services\MessageFormatter $messageFormatter;

    private AdminService $adminService;

    private HintService $hintService;

    private TrueFalseService $trueFalseService;

    private ProfileFormatter $profileFormatter;

    private StatisticsService $statisticsService;

    private string $basePath;

    public function __construct(
        ClientInterface $telegramClient,
        Logger $logger,
        CacheInterface $cache,
        UserService $userService,
        DuelService $duelService,
        GameSessionService $gameSessionService,
        StoryService $storyService,
        \QuizBot\Application\Services\MessageFormatter $messageFormatter,
        AdminService $adminService,
        HintService $hintService,
        TrueFalseService $trueFalseService,
        ProfileFormatter $profileFormatter,
        StatisticsService $statisticsService
    ) {
        $this->telegramClient = $telegramClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->userService = $userService;
        $this->duelService = $duelService;
        $this->gameSessionService = $gameSessionService;
        $this->storyService = $storyService;
        $this->messageFormatter = $messageFormatter;
        $this->adminService = $adminService;
        $this->hintService = $hintService;
        $this->trueFalseService = $trueFalseService;
        $this->profileFormatter = $profileFormatter;
        $this->statisticsService = $statisticsService;
        $this->basePath = dirname(__DIR__, 4);
    }

    protected function getMessageFormatter(): \QuizBot\Application\Services\MessageFormatter
    {
        return $this->messageFormatter;
    }

    private function handleMatchmakingSearch($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы искать соперников.');

            return;
        }

        try {
            $existingTicket = $this->duelService->findUserMatchmakingTicket($user);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка поиска существующего тикета матчмейкинга', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);
            $existingTicket = null;
        }

        if ($existingTicket !== null) {
            $this->sendText($chatId, '🎲 Поиск соперника уже идёт. Подождём до 30 секунд.');

            return;
        }

        try {
            $opponentTicket = $this->duelService->findAvailableMatchmakingTicket($user);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка выбора тикета соперника', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);
            $opponentTicket = null;
        }

        if ($opponentTicket instanceof Duel) {
            try {
                $duel = $this->duelService->acceptDuel($opponentTicket, $user);
                $duel = $this->duelService->startDuel($duel);

                $this->broadcastDuelText($duel, '⚔️ Случайный соперник найден! Раунд начинается.');

                if ($currentRound = $this->duelService->getCurrentRound($duel)) {
                    $this->sendDuelQuestion($duel, $currentRound);
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Не удалось запустить дуэль матчмейкинга', [
                    'error' => $exception->getMessage(),
                    'user_id' => $user->getKey(),
                    'duel_id' => $opponentTicket->getKey(),
                ]);

                $this->sendText($chatId, '⚠️ Не удалось стартовать дуэль. Попробуй поиск ещё раз чуть позже.');
            }

            return;
        }

        try {
            $ticket = $this->duelService->createMatchmakingTicket($user);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка создания матчмейкинга', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Не удалось запустить поиск. Попробуй позже.');

            return;
        }

        $messageId = $this->sendMatchmakingMessage($chatId, 30);

        if ($messageId === null) {
            $this->logger->warning('Не удалось получить message_id для матчмейкинга', [
                'chat_id' => $chatId,
            ]);
        }

        $this->scheduleMatchmakingTimeout($ticket, 30, (int) $chatId, $messageId);
    }

    private function sendMatchmakingMessage($chatId, int $seconds): ?int
    {
        $text = sprintf("🎲 Ищу случайного соперника...\n⏱ Осталось: %d с", $seconds);

        try {
            $response = $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ],
            ]);

            $body = (string) $response->getBody();
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $payload['result']['message_id'] ?? null;
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось отправить сообщение матчмейкинга', [
                'error' => $exception->getMessage(),
                'chat_id' => $chatId,
            ]);
        }

        return null;
    }

    private function scheduleMatchmakingTimeout(Duel $duel, int $seconds, int $chatId, ?int $messageId): void
    {
        $script = $this->basePath . '/bin/matchmaking_timeout.php';

        if (!is_file($script)) {
            $this->logger->warning('Скрипт контроля матчмейкинга не найден', [
                'path' => $script,
            ]);

            return;
        }

        $command = sprintf(
            '%s %s %d %d %d %d > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            $duel->getKey(),
            $seconds,
            $chatId,
            $messageId ?? 0
        );

        $descriptorSpec = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $process = @proc_open($command, $descriptorSpec, $pipes);

        if (is_resource($process)) {
            proc_close($process);
        } else {
            // fallback to exec
            @exec($command);
        }
    }

    protected function getTelegramClient(): ClientInterface
    {
        return $this->telegramClient;
    }

    protected function getLogger(): Logger
    {
        return $this->logger;
    }

    protected function getDuelService(): DuelService
    {
        return $this->duelService;
    }

    /**
     * @param array<string, mixed> $callback
     */
    public function handle(array $callback): void
    {
        $callbackId = $callback['id'] ?? null;
        $data = $callback['data'] ?? null;
        $message = $callback['message'] ?? null;

        $this->logger->debug('Обработка callback query', [
            'callback_id' => $callbackId,
            'data' => $data,
            'has_message' => $message !== null,
        ]);

        if ($callbackId === null || $data === null || $message === null) {
            $this->logger->warning('Некорректный callback_query', $callback);

            return;
        }

        // Отвечаем на callback query сразу, чтобы убрать индикатор загрузки
        try {
            $this->telegramClient->request('POST', 'answerCallbackQuery', [
                'json' => [
                    'callback_query_id' => $callbackId,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Не удалось ответить на callback query', [
                'error' => $e->getMessage(),
                'callback_id' => $callbackId,
                'trace' => $e->getTraceAsString(),
            ]);
            // Продолжаем обработку даже если не удалось ответить на callback
        }

        $chatId = $message['chat']['id'] ?? null;

        if ($chatId === null) {
            $this->logger->warning('Callback без chat_id', $callback);

            return;
        }

        $from = $callback['from'] ?? null;
        $user = $this->resolveUser($from);

        try {
            if ($this->startsWith($data, 'admin:')) {
                $this->logger->debug('Обработка админ-действия', ['data' => $data]);
                $this->handleAdminAction($chatId, $data, $user);

                return;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при обработке админ-действия', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sendText($chatId, '❌ Произошла ошибка при обработке действия. Попробуйте позже.');
            return;
        }

        if ($this->startsWith($data, 'story-locked:')) {
            $chapterCode = substr($data, strlen('story-locked:'));
            $this->sendText($chatId, sprintf('🔒 Глава <b>%s</b> будет доступна после завершения предыдущей.', htmlspecialchars($chapterCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));

            return;
        }

        if ($this->startsWith($data, 'story:')) {
            $chapterCode = substr($data, strlen('story:'));
            $this->handleStoryOpen($chatId, $chapterCode, $user);

            return;
        }

        if ($this->startsWith($data, 'story-continue:')) {
            $this->handleStoryContinue($chatId, $data, $user);

            return;
        }

        if ($this->startsWith($data, 'story-choice:')) {
            $this->handleStoryChoice($chatId, $data, $user);

            return;
        }

        if ($this->startsWith($data, 'story-answer:')) {
            $this->handleStoryAnswer($chatId, $data, $user);

            return;
        }

        if ($this->startsWith($data, 'duel-accept:')) {
            $duelId = (int) substr($data, strlen('duel-accept:'));
            $this->handleDuelAccept($chatId, $duelId, $user);

            return;
        }

        if ($this->startsWith($data, 'duel-reject:')) {
            $duelId = (int) substr($data, strlen('duel-reject:'));
            $this->handleDuelReject($chatId, $duelId, $user);

            return;
        }

        if ($this->startsWith($data, 'duel-answer:')) {
            $this->handleDuelAnswer($chatId, $data, $user);

            return;
        }

        if ($this->startsWith($data, 'duel:')) {
            $duelAction = substr($data, strlen('duel:'));

            $this->handleDuelAction($chatId, $duelAction, $user);

            return;
        }

        if ($this->startsWith($data, 'play:')) {
            $categoryCode = substr($data, strlen('play:'));
            $this->startCategoryRound($chatId, $categoryCode, $user);

            return;
        }

        if ($this->startsWith($data, 'answer:')) {
            $payload = explode(':', $data);

            if (count($payload) === 3) {
                $sessionId = (int) $payload[1];
                $answerId = (int) $payload[2];
                $this->handleAnswerAction($chatId, $sessionId, $answerId, $user);
            } else {
                $this->sendText($chatId, 'Не удалось обработать ответ. Попробуйте снова.');
            }

            return;
        }

        if ($this->startsWith($data, 'hint:')) {
            $this->handleHintAction($chatId, $data, $user);

            return;
        }

        if ($this->startsWith($data, 'story-hint:')) {
            $this->handleStoryHintAction($chatId, $data, $user);

            return;
        }

        if ($data === 'tf:start') {
            $this->handleTrueFalseStart($chatId, $user);

            return;
        }

        if ($this->startsWith($data, 'tf:answer:')) {
            $this->handleTrueFalseAnswer($chatId, $data, $user);

            return;
        }

        if ($data === 'rating:duel') {
            $this->handleDuelLeaderboard($chatId, $user);

            return;
        }

        if ($data === 'rating:tf') {
            $this->handleTrueFalseLeaderboard($chatId, $user);

            return;
        }

        if ($data === 'stats:full') {
            $this->handleFullStatistics($chatId, $user);

            return;
        }
    }

    /**
     * Обработка запроса полной статистики
     */
    private function handleFullStatistics($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось загрузить статистику. Попробуйте /start.');
            return;
        }

        try {
            $stats = $this->statisticsService->getFullStatistics($user);
            $text = $this->formatStatisticsText($stats);
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось загрузить статистику', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);

            $this->sendText($chatId, "📊 <b>Статистика</b>\n\nНедостаточно данных. Сыграй несколько дуэлей, чтобы собрать статистику!");
            return;
        }

        $this->sendText($chatId, $text);
    }

    /**
     * Форматирование текста статистики
     */
    private function formatStatisticsText(array $stats): string
    {
        $overview = $stats['overview'] ?? [];
        $strengths = $stats['strengths'] ?? [];
        $weaknesses = $stats['weaknesses'] ?? [];
        $bestDay = $stats['best_day'] ?? null;

        $lines = [
            '📊 <b>ТВОЯ СТАТИСТИКА</b>',
            '',
        ];

        // Общие показатели
        $lines[] = '🎯 <b>Общие показатели</b>';
        $accuracy = $overview['accuracy'] ?? 0;
        $avgTime = $overview['average_time'] ?? 0;
        $lines[] = sprintf('├ Точность: <b>%s%%</b>', $accuracy);
        $lines[] = sprintf('├ Среднее время: <b>%sс</b>', $avgTime);
        $lines[] = sprintf('├ Всего вопросов: <b>%d</b>', $overview['total_questions'] ?? 0);
        $lines[] = sprintf('├ Правильных: <b>%d</b>', $overview['correct_answers'] ?? 0);
        $lines[] = sprintf('└ Лучшая серия: <b>%d</b>', $overview['best_streak'] ?? 0);
        $lines[] = '';

        // Сильные стороны
        if (!empty($strengths)) {
            $lines[] = '💪 <b>Сильные стороны</b>';
            foreach ($strengths as $cat) {
                $icon = $cat['category_icon'] ?? '📚';
                $name = $cat['category_name'] ?? 'Неизвестно';
                $catAccuracy = $cat['accuracy'] ?? 0;
                $lines[] = sprintf('├ %s %s: <b>%s%%</b>', $icon, $name, $catAccuracy);
            }
            $lines[] = '';
        }

        // Слабые стороны
        if (!empty($weaknesses)) {
            $lines[] = '📚 <b>Нужно подтянуть</b>';
            foreach ($weaknesses as $cat) {
                $icon = $cat['category_icon'] ?? '📚';
                $name = $cat['category_name'] ?? 'Неизвестно';
                $catAccuracy = $cat['accuracy'] ?? 0;
                $lines[] = sprintf('├ %s %s: <b>%s%%</b>', $icon, $name, $catAccuracy);
            }
            $lines[] = '';
        }

        // Лучший день
        if ($bestDay !== null) {
            $dayName = $bestDay['day_name'] ?? $bestDay['day'] ?? '';
            $dayAccuracy = $bestDay['accuracy'] ?? 0;
            $baseAccuracy = $overview['accuracy'] ?? 0;
            $diff = round($dayAccuracy - $baseAccuracy);
            $diffStr = $diff > 0 ? "+{$diff}%" : "{$diff}%";
            
            $lines[] = '⏰ <b>Лучшее время для игры</b>';
            $lines[] = sprintf('└ 📅 %s (%s к точности)', $dayName, $diffStr);
            $lines[] = '';
        }

        // Серия побед в дуэлях
        $duelStreak = $overview['best_duel_win_streak'] ?? 0;
        if ($duelStreak > 0) {
            $lines[] = sprintf('🔥 <b>Лучшая серия побед в дуэлях: %d</b>', $duelStreak);
        }

        return implode("\n", $lines);
    }

    private function handleDuelAccept($chatId, int $duelId, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы участвовать в дуэлях.');

            return;
        }

        $duel = $this->duelService->findById($duelId);

        if (!$duel instanceof Duel) {
            $this->sendText($chatId, '⚠️ Дуэль не найдена или уже завершена.');

            return;
        }

        if ($duel->initiator_user_id === $user->getKey()) {
            $this->sendText($chatId, '👀 Это твоё приглашение. Отправь ник соперника, чтобы он принял дуэль.');

            return;
        }

        if ($duel->status !== 'waiting') {
            $this->sendText($chatId, '⚠️ Дуэль уже началась или завершена.');

            return;
        }

        $settings = $duel->settings ?? [];
        $expectedId = isset($settings['target_user_id']) ? (int) $settings['target_user_id'] : null;
        $expectedUsername = isset($settings['target_username']) ? strtolower((string) $settings['target_username']) : null;

        if ($expectedId !== null && $expectedId !== $user->getKey()) {
            $this->sendText($chatId, 'Это приглашение предназначено для другого игрока.');

            return;
        }

        if ($expectedId === null && $expectedUsername !== null) {
            $actualUsername = $user->username !== null ? strtolower($user->username) : null;

            if ($actualUsername === null || $actualUsername !== $expectedUsername) {
                $this->sendText($chatId, sprintf(
                    'Это приглашение предназначено для @%s.',
                    htmlspecialchars((string) $settings['target_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                ));

                return;
            }
        }

        try {
            $duel = $this->duelService->acceptDuel($duel, $user);
            $duel = $this->duelService->startDuel($duel);
            $duel->loadMissing('initiator', 'opponent');
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка принятия дуэли', [
                'error' => $exception->getMessage(),
                'duel_id' => $duelId,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Не получилось присоединиться. Попробуй позже.');

            return;
        }

        $this->sendText($chatId, '⚔️ Дуэль принята! Готовься к вопросам.', true);

        $this->broadcastDuelText($duel, sprintf(
            '⚔️ Дуэль <b>%s</b> началась! Отвечайте на вопросы максимально быстро.',
            htmlspecialchars($duel->code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        ));

        // Пауза 5 секунд перед отправкой первого вопроса
        sleep(5);

        if ($currentRound = $this->duelService->getCurrentRound($duel)) {
            $this->sendDuelQuestion($duel, $currentRound);
        }
    }

    private function handleDuelReject($chatId, int $duelId, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы участвовать в дуэлях.');

            return;
        }

        $duel = $this->duelService->findById($duelId);

        if (!$duel instanceof Duel) {
            $this->sendText($chatId, 'Дуэль не найдена. Возможно, она уже завершена.');

            return;
        }

        if ($duel->status !== 'waiting') {
            $this->sendText($chatId, '⚠️ Дуэль уже в процессе. Отказаться поздно.');

            return;
        }

        $settings = $duel->settings ?? [];
        $expectedId = isset($settings['target_user_id']) ? (int) $settings['target_user_id'] : null;
        $expectedUsername = isset($settings['target_username']) ? strtolower((string) $settings['target_username']) : null;

        if ($expectedId !== null && $expectedId !== $user->getKey()) {
            $this->sendText($chatId, 'Отказаться может только приглашённый игрок.');

            return;
        }

        if ($expectedId === null && $expectedUsername !== null) {
            $actualUsername = $user->username !== null ? strtolower($user->username) : null;

            if ($actualUsername === null || $actualUsername !== $expectedUsername) {
                $this->sendText($chatId, sprintf(
                    'Отказаться может только @%s.',
                    htmlspecialchars((string) $settings['target_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                ));

                return;
            }
        }

        $duel = $this->duelService->cancelWaitingDuel($duel, $user);

        $this->sendText($chatId, '❌ Ты отказался от дуэли.', true);

        $this->broadcastDuelText($duel, sprintf(
            '❌ Дуэль отменена. %s отказался от участия.',
            $this->formatUserName($user)
        ));
    }

    private function handleStoryOpen($chatId, string $chapterCode, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы продолжить сюжет.');

            return;
        }

        try {
            $state = $this->storyService->startChapter($user, $chapterCode);
        } catch (\DomainException $exception) {
            $this->sendText($chatId, '🔒 Эта глава ещё закрыта. Заверши предыдущую, чтобы продолжить.');

            return;
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при открытии сюжетной главы', [
                'error' => $exception->getMessage(),
                'chapter_code' => $chapterCode,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Глава временно недоступна. Попробуй позже.');

            return;
        }

        // Первое сообщение главы - показываем заголовок
        $this->presentStoryState($chatId, $state, true);
    }

    private function handleStoryContinue($chatId, string $data, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы продолжить сюжет.');

            return;
        }

        $parts = explode(':', $data);

        if (count($parts) !== 3) {
            $this->sendText($chatId, 'Не удалось продолжить главу. Попробуй ещё раз через /story.');

            return;
        }

        [$prefix, $chapterCode, $stepCode] = $parts;

        try {
            $state = $this->storyService->continueStep($user, $chapterCode, $stepCode);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка продолжения сюжетного шага', [
                'error' => $exception->getMessage(),
                'chapter_code' => $chapterCode,
                'step_code' => $stepCode,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Не удалось продолжить главу. Попробуй ещё раз через /story.');

            return;
        }

        // Не первое сообщение - не показываем заголовок
        $this->presentStoryState($chatId, $state, false);
    }

    private function handleStoryAnswer($chatId, string $data, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы продолжить сюжет.');

            return;
        }

        $parts = explode(':', $data);

        if (count($parts) !== 4) {
            $this->sendText($chatId, 'Не удалось обработать ответ. Попробуй ещё раз через /story.');

            return;
        }

        [, $chapterCode, $stepCode, $answerIdRaw] = $parts;
        $answerId = (int) $answerIdRaw;

        // Получаем шаг для определения его ID
        $chapter = \QuizBot\Domain\Model\StoryChapter::query()->where('code', $chapterCode)->first();
        $step = $chapter ? \QuizBot\Domain\Model\StoryStep::query()
            ->where('chapter_id', $chapter->getKey())
            ->where('code', $stepCode)
            ->first() : null;

        // Получаем время начала вопроса из кеша
        $questionStartTime = null;
        if ($step !== null) {
            $cacheKey = sprintf('story_question_start_%d_%d', $user->getKey(), $step->getKey());
            try {
                $questionStartTime = $this->cache->get($cacheKey, function () {
                    return null;
                });
                // Удаляем из кеша после использования
                $this->cache->delete($cacheKey);
            } catch (\Throwable $e) {
                // Если не удалось получить время, продолжаем без него
            }
        }

        try {
            $state = $this->storyService->submitAnswer($user, $chapterCode, $stepCode, $answerId, $questionStartTime);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка ответа на сюжетный вопрос', [
                'error' => $exception->getMessage(),
                'chapter_code' => $chapterCode,
                'step_code' => $stepCode,
                'answer_id' => $answerId,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Не удалось обработать ответ. Попробуй ещё раз через /story.');

            return;
        }

        if (isset($state['answer_feedback'])) {
            $this->sendStoryAnswerFeedback($chatId, $state['answer_feedback']);
            unset($state['answer_feedback']);
        }

        // Не первое сообщение - не показываем заголовок
        $this->presentStoryState($chatId, $state, false);
    }

    private function handleStoryChoice($chatId, string $data, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start, чтобы продолжить сюжет.');

            return;
        }

        $parts = explode(':', $data);

        if (count($parts) !== 4) {
            $this->sendText($chatId, 'Не удалось обработать выбор. Попробуй ещё раз через /story.');

            return;
        }

        [, $chapterCode, $stepCode, $choiceKey] = $parts;

        try {
            $state = $this->storyService->continueStep($user, $chapterCode, $stepCode, $choiceKey);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка обработки выбора в сюжете', [
                'error' => $exception->getMessage(),
                'chapter_code' => $chapterCode,
                'step_code' => $stepCode,
                'choice_key' => $choiceKey,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Не удалось обработать выбор. Попробуй ещё раз через /story.');

            return;
        }

        // Не первое сообщение - не показываем заголовок
        $this->presentStoryState($chatId, $state, false);
    }

    private function handleDuelAnswer($chatId, string $data, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Попробуйте /start.');

            return;
        }

        if (!preg_match('/^duel-answer:(\d+):(\d+):(\d+)$/', $data, $matches)) {
            $this->sendText($chatId, 'Не удалось обработать ответ дуэли. Попробуйте снова.');

            return;
        }

        [, $duelIdRaw, $roundIdRaw, $answerIdRaw] = $matches;
        $duelId = (int) $duelIdRaw;
        $roundId = (int) $roundIdRaw;
        $answerId = (int) $answerIdRaw;

        try {
            $duel = $this->duelService->findById($duelId);

            if (!$duel instanceof Duel) {
                $this->sendText($chatId, 'Дуэль не найдена. Возможно, она уже завершена.');

                return;
            }

            if ($duel->initiator_user_id !== $user->getKey() && $duel->opponent_user_id !== $user->getKey()) {
                $this->sendText($chatId, 'Ты не участвуешь в этой дуэли.');

                return;
            }

            $round = $duel->rounds()->where('id', $roundId)->first();

            if (!$round instanceof DuelRound) {
                $this->sendText($chatId, 'Раунд не найден. Попробуй снова.');

                return;
            }

            try {
                $round = $this->duelService->submitAnswer($round, $user, $answerId);
            } catch (\Throwable $exception) {
                $this->logger->error('Ошибка обработки ответа дуэли', [
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                    'duel_id' => $duelId,
                    'round_id' => $roundId,
                    'answer_id' => $answerId,
                    'user_id' => $user->getKey(),
                ]);
                $this->sendText($chatId, '⚠️ Не удалось засчитать ответ. Попробуй ещё раз.');

                return;
            }

            $payload = $duel->initiator_user_id === $user->getKey() ? ($round->initiator_payload ?? []) : ($round->opponent_payload ?? []);
            
            $ack = 'Ответ засчитан.';
            
            if (($payload['reason'] ?? null) === 'timeout') {
                $ack = '⏰ Время истекло. Ответ не засчитан.';
            } elseif (($payload['is_correct'] ?? false) === true) {
                $ack = $this->messageFormatter->correctAnswer('Верно!');
            } else {
                $round->loadMissing('question.answers');
                $correctAnswer = $round->question?->answers->firstWhere('is_correct', true);
                $correctText = $correctAnswer ? htmlspecialchars($correctAnswer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Правильный ответ';
                $ack = $this->messageFormatter->incorrectAnswer($correctText);
            }
            
            // Убеждаемся, что отправляем результат только тому, кто ответил
            // Используем telegram_id пользователя, а не chatId из сообщения
            $userChatId = $user->telegram_id;
            if ($userChatId === null) {
                $this->logger->warning('Не удалось отправить результат ответа: отсутствует telegram_id', [
                    'user_id' => $user->getKey(),
                    'duel_id' => $duel->getKey(),
                    'round_id' => $round->getKey(),
                ]);
            } else {
                $this->sendText($userChatId, $ack, true);
            }

            $duel = $duel->refresh(['rounds.question.answers', 'initiator', 'opponent', 'result']);
            $round = $duel->rounds->firstWhere('id', $roundId);

            if ($round instanceof DuelRound && $round->closed_at !== null) {
                $this->sendDuelRoundResult($duel, $round);
                
                // Пауза 3 секунды после отправки результатов
                sleep(3);

                if ($duel->status === 'finished' && $duel->result !== null) {
                    $this->sendDuelFinalResult($duel, $duel->result);

                    return;
                }

                $nextRound = $this->duelService->getCurrentRound($duel);

                if ($nextRound instanceof DuelRound) {
                    $this->sendDuelQuestion($duel, $nextRound);
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Критическая ошибка в handleDuelAnswer', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'data' => $data,
                'user_id' => $user?->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ Произошла ошибка при обработке ответа. Попробуй ещё раз.');
        }
    }

    private function presentStoryState($chatId, array $state, bool $isFirstMessage = false): void
    {
        /** @var StoryChapter $chapter */
        $chapter = $state['chapter'];
        /** @var StoryProgress $progress */
        $progress = $state['progress'];

        if ($state['completed'] === true || $state['step'] === null) {
            $lines = [
                '🏁 <b>Глава завершена!</b>',
                '',
                htmlspecialchars($chapter->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                '',
                sprintf('🏆 Очки главы: <b>%d</b>', (int) $progress->score),
                sprintf('❌ Ошибок: %d', (int) $progress->mistakes),
                '',
                'Следующая глава разблокирована — открой /story, чтобы продолжить!',
            ];

            $this->sendText($chatId, implode("\n", $lines));

            return;
        }

        /** @var StoryStep $step */
        $step = $state['step'];
        /** @var StoryQuestion|null $question */
        $question = $state['question'] ?? null;

        $lines = [];

        // Показываем заголовок главы только при первом сообщении
        if ($isFirstMessage) {
            $lines[] = $this->messageFormatter->header($chapter->title, '📖');
            $lines[] = '';
            
            if (!empty($chapter->description)) {
                $lines[] = htmlspecialchars($chapter->description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines[] = '';
            }
        }

        // Красивая визуализация здоровья
        $lives = (int) $progress->lives_remaining;
        $maxLives = 3;
        $healthDisplay = str_repeat('❤️', $lives) . str_repeat('🤍', $maxLives - $lives);
        $lines[] = sprintf('💚 Жизни: %s', $healthDisplay);
        $lines[] = '';

        if (!empty($step->narrative_text)) {
            $lines[] = htmlspecialchars($step->narrative_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lines[] = '';
        }

        $keyboard = [];

        // Обработка шага с вопросом истории
        if ($question instanceof StoryQuestion) {
            // Сохраняем время начала вопроса в кеше для расчета очков
            $cacheKey = sprintf('story_question_start_%d_%d', $progress->user_id, $step->getKey());
            try {
                $this->cache->delete($cacheKey);
                $this->cache->get($cacheKey, static function () {
                    return time();
                });
            } catch (\Throwable $e) {
                // Если не удалось сохранить время, продолжаем без кеша
            }

            // Показываем контекст, если есть
            if (!empty($question->context_text)) {
                $lines[] = '<i>' . htmlspecialchars($question->context_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</i>';
                $lines[] = '';
            }

            // Показываем вопрос в красивом формате
            $lines[] = $this->messageFormatter->questionBox(
                htmlspecialchars($question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
            $lines[] = '';
            $lines[] = sprintf('⏱ У тебя %d секунд. Чем быстрее ответишь, тем больше очков получишь!', 30);
            $lines[] = '';

            $answerButtons = [];
            $row = [];

            $question->load('answers');
            // Перемешиваем ответы в случайном порядке
            $answers = $question->answers->shuffle();

            foreach ($answers as $index => $answer) {
                $row[] = [
                    'text' => htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'callback_data' => sprintf('story-answer:%s:%s:%d', $chapter->code, $step->code, $answer->getKey()),
                ];

                if (count($row) === 2 || $index === count($answers) - 1) {
                    $answerButtons[] = $row;
                    $row = [];
                }
            }

            // Добавляем кнопки подсказок для сюжета
            $hintButtons = $this->getStoryHintButtons($user, $chapter, $step);
            if (!empty($hintButtons)) {
                $answerButtons[] = $hintButtons;
            }

            $keyboard = $answerButtons;
        } elseif ($step->step_type === StoryStep::TYPE_CHOICE && !empty($step->choice_options)) {
            // Обработка шага с выбором (интерактивные ветки)
            $choiceOptions = $step->choice_options;
            $choiceButtons = [];

            foreach ($choiceOptions as $key => $label) {
                $choiceButtons[] = [
                    [
                        'text' => htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        'callback_data' => sprintf('story-choice:%s:%s:%s', $chapter->code, $step->code, $key),
                    ],
                ];
            }

            $keyboard = $choiceButtons;
        } else {
            // Обычный повествовательный шаг
            $nextCode = $state['continue_code'] ?? null;

            if ($nextCode !== null) {
                $keyboard[] = [
                    [
                        'text' => '➡️ Продолжить',
                        'callback_data' => sprintf('story-continue:%s:%s', $chapter->code, $step->code),
                    ],
                ];
            }
        }

        $text = implode("\n", $lines);
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        $replyMarkup = null;
        if (!empty($keyboard)) {
            $replyMarkup = [
                'inline_keyboard' => $keyboard,
            ];
            $payload['reply_markup'] = $replyMarkup;
        }

        // Если это вопрос истории с изображением, отправляем через sendPhoto
        if ($question instanceof StoryQuestion && !empty($question->image_url)) {
            $photoPayload = [
                'chat_id' => $chatId,
                'photo' => $question->image_url,
                'caption' => $text,
                'parse_mode' => 'HTML',
            ];
            
            if (isset($payload['reply_markup'])) {
                $photoPayload['reply_markup'] = $payload['reply_markup'];
            }
            
            $response = $this->telegramClient->request('POST', 'sendPhoto', [
                'json' => $photoPayload,
            ]);
        } else {
            $response = $this->telegramClient->request('POST', 'sendMessage', [
                'json' => $payload,
            ]);
        }

        // Если это вопрос истории, запускаем фоновый скрипт для обновления таймера
        if ($question instanceof StoryQuestion && $step !== null) {
            try {
                $responseBody = (string) $response->getBody();
                $responseData = json_decode($responseBody, true);
                $messageId = isset($responseData['result']['message_id']) ? (int) $responseData['result']['message_id'] : 0;

                if ($messageId > 0) {
                    $scriptPath = $this->basePath . '/bin/story_question_timer.php';
                    $startTime = time();
                    $replyMarkupJson = json_encode($replyMarkup ?: []);

                    // Запускаем скрипт в фоне
                    $command = sprintf(
                        'php %s %d %d %d %d %d %s %s > /dev/null 2>&1 &',
                        escapeshellarg($scriptPath),
                        $chatId,
                        $messageId,
                        $progress->getKey(),
                        $step->getKey(),
                        $startTime,
                        escapeshellarg($text),
                        escapeshellarg($replyMarkupJson)
                    );

                    exec($command);
                }
            } catch (\Throwable $e) {
                $this->logger->debug('Не удалось запустить таймер вопроса истории', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                ]);
            }
        }
    }

    private function sendStoryAnswerFeedback($chatId, array $feedback): void
    {
        /** @var StoryQuestion $question */
        $question = $feedback['question'];
        $isCorrect = (bool) $feedback['is_correct'];
        $explanation = $feedback['explanation'] ?? null;
        $pointsEarned = $feedback['points_earned'] ?? 0;

        $lines = [];

        if ($isCorrect) {
            $pointsText = $pointsEarned > 0 
                ? sprintf('+%d очков', $pointsEarned)
                : '+1 очко';
            $lines[] = $this->messageFormatter->animatedCorrectAnswer($pointsText);
        } else {
            $correctAnswers = $feedback['correct_answers'] ?? [];
            $correctText = !empty($correctAnswers) 
                ? htmlspecialchars($correctAnswers[0]->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : 'Правильный ответ';
            $lines[] = $this->messageFormatter->animatedIncorrectAnswer($correctText);
        }

        $lines[] = '';
        $lines[] = $this->messageFormatter->separator();

        // Показываем объяснение, если есть
        if (!empty($explanation)) {
            $lines[] = '💡 <b>Объяснение:</b>';
            $lines[] = '';
            $lines[] = htmlspecialchars($explanation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lines[] = '';
            $lines[] = $this->messageFormatter->separator();
        }

        $this->sendText($chatId, implode("\n", $lines));
    }

    /**
     * @param int|string $chatId
     */
    private function startCategoryRound($chatId, string $categoryCode, ?User $user): void
    {
        if (in_array($categoryCode, ['science', 'tech', 'myth'], true)) {
            $categoryCode = 'science_tech';
        }

        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить пользователя. Попробуйте команду /play.');

            return;
        }

        try {
            $result = $this->gameSessionService->startCategoryRound($user, $categoryCode);
            $this->sendQuestion($chatId, $result['session'], $result['question']);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка запуска раунда', [
                'error' => $exception->getMessage(),
                'category' => $categoryCode,
                'user_id' => $user->getKey(),
            ]);

            $this->sendText($chatId, '⚠️ Не удалось начать раунд. Попробуйте другую категорию или позже.');
        }
    }

    /**
     * @param int|string $chatId
     */
    /**
     * @param int|string $chatId
     */
    private function handleDuelAction($chatId, string $action, ?User $user): void
    {
        if ($user === null) {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => 'Не удалось обработать действие дуэли. Попробуйте /duel.',
                ],
            ]);

            return;
        }

        $currentDuel = $this->duelService->findActiveDuelForUser($user);

        if ($action === 'invite') {
            if ($currentDuel !== null && $currentDuel->status !== 'waiting') {
                $this->sendText($chatId, '⚠️ Дуэль уже в процессе. Заверши текущую или дождись результата.');

                return;
            }

            if ($currentDuel === null) {
                $currentDuel = $this->duelService->createDuel($user);
            }

            $currentDuel = $this->duelService->markAwaitingTarget($currentDuel);

            $textLines = [
                '👥 Приглашение почти готово!',
                'Отправь мне ник соперника в формате <b>@username</b> — я отправлю ему запрос на дуэль.',
            ];

            $this->sendText($chatId, implode("\n", $textLines));

            return;
        }

        if ($action === 'matchmaking') {
            $this->handleMatchmakingSearch($chatId, $user);

            return;
        }

        if ($action === 'history') {
            $this->sendDuelHistory($chatId, $user);

            return;
        }

        $this->sendText($chatId, '⚔️ Неизвестное действие. Попробуйте /duel для синхронизации.');
    }

    private function sendDuelHistory($chatId, User $user): void
    {
        try {
            $duels = $this->duelService->getRecentDuels($user, 5);
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось получить историю дуэлей', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
            ]);

            $this->sendText($chatId, '⚠️ История дуэлей временно недоступна. Попробуйте позже.');

            return;
        }

        if ($duels->isEmpty()) {
            $this->sendText($chatId, "📜 Ещё нет сыгранных дуэлей.\nНажми «👥 Пригласить друга» или «🎲 Случайный соперник», чтобы начать.");

            return;
        }

        $lines = [
            '📜 <b>Последние дуэли</b>',
            sprintf('Показаны %d матча(ей).', $duels->count()),
            '',
        ];

        foreach ($duels as $index => $duel) {
            $lines[] = sprintf('%d) %s', $index + 1, $this->formatDuelHistoryEntry($duel, $user));
        }

        $lines[] = '';
        $lines[] = 'Создай новую дуэль через «👥 Пригласить друга» или найди противника случайным поиском.';

        $this->sendText($chatId, implode("\n", $lines));
    }

    private function formatDuelHistoryEntry(Duel $duel, User $user): string
    {
        $timestamp = $duel->finished_at ?? $duel->updated_at ?? $duel->created_at;
        $whenText = $timestamp instanceof \DateTimeInterface ? $timestamp->format('d.m H:i') : '—';

        $opponent = $duel->initiator_user_id === $user->getKey() ? $duel->opponent : $duel->initiator;
        $opponentName = $this->formatUserName($opponent);
        $status = $this->formatDuelHistoryStatus($duel, $user);

        return sprintf(
            '%s • против %s • %s',
            htmlspecialchars($whenText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $opponentName,
            $status
        );
    }

    private function formatDuelHistoryStatus(Duel $duel, User $user): string
    {
        if ($duel->status === 'finished' && $duel->result !== null) {
            $result = $duel->result;
            $isInitiator = $duel->initiator_user_id === $user->getKey();
            $userScore = $isInitiator ? (int) $result->initiator_total_score : (int) $result->opponent_total_score;
            $opponentScore = $isInitiator ? (int) $result->opponent_total_score : (int) $result->initiator_total_score;
            $scoreText = sprintf('%d:%d', $userScore, $opponentScore);

            if ($result->winner_user_id === null) {
                return sprintf('🤝 Ничья (%s)', $scoreText);
            }

            if ($result->winner_user_id === $user->getKey()) {
                return sprintf('🏆 Победа (%s)', $scoreText);
            }

            return sprintf('💔 Поражение (%s)', $scoreText);
        }

        switch ($duel->status) {
            case 'waiting':
                return '⏳ Ждёт соперника';
            case 'matched':
                return '⏳ Соперник найден, стартуем';
            case 'in_progress':
                return '⚔️ Дуэль в процессе';
            case 'cancelled':
                return '❌ Дуэль отменена';
            default:
                return sprintf(
                    'Статус: %s',
                    htmlspecialchars((string) $duel->status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                );
        }
    }

    private function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    private function resolveUser($from): ?User
    {
        if (!is_array($from)) {
            return null;
        }

        try {
            return $this->userService->syncFromTelegram($from);
        } catch (\Throwable $exception) {
            $this->logger->error('Не удалось синхронизировать пользователя по callback', [
                'error' => $exception->getMessage(),
                'from' => $from,
            ]);

            return null;
        }
    }

    /**
     * @param int|string $chatId
     * @param array<string, mixed>|null $options
     */
    private function sendText($chatId, string $text, bool $disablePreview = false, ?array $options = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($disablePreview) {
            $payload['disable_web_page_preview'] = true;
        }

        if ($options !== null) {
            $payload = array_merge($payload, $options);
        }

        $this->telegramClient->request('POST', 'sendMessage', [
            'json' => $payload,
        ]);
    }

    private function sendQuestion($chatId, GameSession $session, Question $question): void
    {
        $answers = $question->answers;

        $categoryTitle = 'Категория';

        if ($question->relationLoaded('category') && $question->category !== null) {
            $categoryTitle = $question->category->title;
        } elseif (method_exists($question, 'category')) {
            $category = $question->category()->first();
            if ($category !== null) {
                $categoryTitle = $category->title;
            }
        }

        $textLines = [
            sprintf("🎯 <b>%s</b>\n", htmlspecialchars((string) $categoryTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            htmlspecialchars((string) $question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];

        if (!empty($question->explanation)) {
            $textLines[] = '';
            $textLines[] = '<i>Подсказка появится после ответа.</i>';
        }

        $buttons = [];
        $row = [];

        // Перемешиваем ответы в случайном порядке
        $answers = $answers->shuffle();

        foreach ($answers as $index => $answer) {
            $row[] = [
                'text' => htmlspecialchars((string) ($answer->answer_text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'callback_data' => sprintf('answer:%d:%d', $session->getKey(), $answer->getKey()),
            ];

            if (count($row) === 2 || $index === count($answers) - 1) {
                $buttons[] = $row;
                $row = [];
            }
        }

        // Добавляем кнопки подсказок, если они еще не использованы
        $user = $session->user ?: $session->user()->first();
        if ($user !== null) {
            $hintButtons = $this->getHintButtons($session, $user);
            if (!empty($hintButtons)) {
                $buttons[] = $hintButtons;
            }
        }

        // Если есть изображение, отправляем через sendPhoto
        if (!empty($question->image_url)) {
            $imagePath = $question->image_url;
            $isLocalFile = $this->isLocalFile($imagePath);
            
            if ($isLocalFile) {
                // Локальный файл - используем multipart/form-data
                $absolutePath = $this->resolveLocalPath($imagePath);
                
                if (!file_exists($absolutePath)) {
                    $this->getLogger()->error('Локальный файл изображения не найден', [
                        'path' => $absolutePath,
                        'chat_id' => $chatId,
                    ]);
                    // Отправляем как обычное текстовое сообщение
                    $this->telegramClient->request('POST', 'sendMessage', [
                        'json' => [
                            'chat_id' => $chatId,
                            'text' => implode("\n", $textLines),
                            'parse_mode' => 'HTML',
                            'reply_markup' => [
                                'inline_keyboard' => $buttons,
                            ],
                        ],
                    ]);
                } else {
                    $multipart = [
                        ['name' => 'chat_id', 'contents' => (string) $chatId],
                        ['name' => 'photo', 'contents' => fopen($absolutePath, 'r')],
                        ['name' => 'caption', 'contents' => implode("\n", $textLines)],
                        ['name' => 'parse_mode', 'contents' => 'HTML'],
                        ['name' => 'reply_markup', 'contents' => json_encode(['inline_keyboard' => $buttons])],
                    ];
                    
                    $this->telegramClient->request('POST', 'sendPhoto', [
                        'multipart' => $multipart,
                    ]);
                }
            } else {
                // URL - используем JSON
                $this->telegramClient->request('POST', 'sendPhoto', [
                    'json' => [
                        'chat_id' => $chatId,
                        'photo' => $imagePath,
                        'caption' => implode("\n", $textLines),
                        'parse_mode' => 'HTML',
                        'reply_markup' => [
                            'inline_keyboard' => $buttons,
                        ],
                    ],
                ]);
            }
        } else {
            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $textLines),
                    'parse_mode' => 'HTML',
                    'reply_markup' => [
                        'inline_keyboard' => $buttons,
                    ],
                ],
            ]);
        }
    }

    /**
     * Проверяет, является ли путь локальным файлом (не URL)
     */
    private function isLocalFile(string $path): bool
    {
        // Если путь начинается с http:// или https://, это URL
        if (preg_match('/^https?:\/\//', $path)) {
            return false;
        }
        
        // Если путь начинается с /, это абсолютный путь к локальному файлу
        if (strpos($path, '/') === 0) {
            return true;
        }
        
        // Если путь не содержит ://, это может быть относительный путь
        return strpos($path, '://') === false;
    }
    
    /**
     * Преобразует путь к локальному файлу в абсолютный путь
     */
    private function resolveLocalPath(string $path): string
    {
        // Если путь уже абсолютный, возвращаем как есть
        if (strpos($path, '/') === 0) {
            return $path;
        }
        
        // Определяем basePath через рефлексию
        $reflection = new \ReflectionClass($this);
        $basePath = dirname($reflection->getFileName(), 4); // Поднимаемся на 4 уровня до bot/
        
        // Если путь начинается с storage/ или public/, используем их
        if (strpos($path, 'storage/') === 0 || strpos($path, 'public/') === 0) {
            return $basePath . '/' . $path;
        }
        
        // По умолчанию ищем в storage/images/
        return $basePath . '/storage/images/' . ltrim($path, '/');
    }

    /**
     * Получает кнопки подсказок для вопроса
     */
    private function getHintButtons(GameSession $session, User $user): array
    {
        $check = $this->hintService->canUseHint($session, $user);
        if (!$check['can_use']) {
            return [];
        }

        $hintCost = HintService::getHintCost();
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof \QuizBot\Domain\Model\UserProfile) {
            return [];
        }

        return [
            [
                'text' => sprintf('💡 50/50 (%d💰)', $hintCost),
                'callback_data' => sprintf('hint:%d:fifty_fifty', $session->getKey()),
            ],
            [
                'text' => sprintf('⏭ Пропуск (%d💰)', $hintCost),
                'callback_data' => sprintf('hint:%d:skip', $session->getKey()),
            ],
        ];
    }

    /**
     * Обрабатывает использование подсказки
     */
    private function handleHintAction($chatId, string $data, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Попробуйте /start.');

            return;
        }

        if (!preg_match('/^hint:(\d+):(\w+)$/', $data, $matches)) {
            $this->sendText($chatId, 'Не удалось обработать подсказку. Попробуйте снова.');

            return;
        }

        [, $sessionIdRaw, $hintType] = $matches;
        $sessionId = (int) $sessionIdRaw;

        $session = $this->gameSessionService->findSessionForUser($user, $sessionId);

        if ($session === null) {
            $this->sendText($chatId, 'Сессия не найдена. Запустите новый раунд /play.');

            return;
        }

        try {
            switch ($hintType) {
                case 'fifty_fifty':
                    $result = $this->hintService->useFiftyFifty($session, $user);
                    $this->handleFiftyFiftyHint($chatId, $session, $result);
                    break;

                case 'skip':
                    $result = $this->hintService->useSkip($session, $user);
                    $this->handleSkipHint($chatId, $session, $result);
                    break;

                case 'time_boost':
                    $result = $this->hintService->useTimeBoost($session, $user);
                    $this->handleTimeBoostHint($chatId, $session, $result);
                    break;

                default:
                    $this->sendText($chatId, 'Неизвестный тип подсказки.');
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка использования подсказки', [
                'error' => $exception->getMessage(),
                'session_id' => $sessionId,
                'hint_type' => $hintType,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ ' . $exception->getMessage());
        }
    }

    /**
     * Обрабатывает подсказку 50/50
     */
    private function handleFiftyFiftyHint($chatId, GameSession $session, array $result): void
    {
        $question = $session->currentQuestion ?: $session->currentQuestion()->first();
        if ($question === null) {
            $this->sendText($chatId, 'Вопрос не найден.');

            return;
        }

        $question->loadMissing(['answers', 'category']);
        $remainingAnswers = $result['remaining_answers'] ?? [];
        $removedCount = $result['removed_count'] ?? 0;

        // Обновляем сообщение с вопросом, убрав неправильные ответы
        $categoryTitle = 'Категория';
        if ($question->category !== null) {
            $categoryTitle = $question->category->title;
        }

        $textLines = [
            sprintf("🎯 <b>%s</b>\n", htmlspecialchars((string) $categoryTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            htmlspecialchars((string) $question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '',
            '💡 <i>Использована подсказка 50/50. Убрано неправильных ответов: ' . $removedCount . '</i>',
        ];

        $buttons = [];
        $row = [];

        foreach ($remainingAnswers as $index => $answer) {
            // $answer - массив с ключами id, answer_text, is_correct
            $answerId = $answer['id'] ?? null;
            $answerText = $answer['answer_text'] ?? '';

            if ($answerId === null) {
                continue;
            }

            $row[] = [
                'text' => htmlspecialchars((string) $answerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'callback_data' => sprintf('answer:%d:%d', $session->getKey(), $answerId),
            ];

            if (count($row) === 2 || $index === count($remainingAnswers) - 1) {
                $buttons[] = $row;
                $row = [];
            }
        }

        $this->sendText($chatId, implode("\n", $textLines));
        $this->sendText($chatId, 'Выберите ответ:', false, [
            'reply_markup' => [
                'inline_keyboard' => $buttons,
            ],
        ]);
    }

    /**
     * Обрабатывает подсказку "Пропуск"
     */
    private function handleSkipHint($chatId, GameSession $session, array $result): void
    {
        $nextQuestion = $result['next_question'] ?? null;
        $skippedQuestion = $result['skipped_question'] ?? null;

        if ($skippedQuestion !== null) {
            $this->sendText($chatId, sprintf('⏭ Вопрос пропущен: <b>%s</b>', htmlspecialchars((string) $skippedQuestion->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        }

        if ($nextQuestion !== null) {
            $this->sendQuestion($chatId, $session, $nextQuestion);
        } else {
            // Сессия завершена
            $this->sendText($chatId, '🎉 Раунд завершён! Все вопросы пройдены.');
        }
    }

    /**
     * Обрабатывает подсказку "+15 секунд"
     */
    private function handleTimeBoostHint($chatId, GameSession $session, array $result): void
    {
        $addedSeconds = $result['added_seconds'] ?? 15;
        $this->sendText($chatId, sprintf('⏱ Добавлено %d секунд времени!', $addedSeconds));
    }

    /**
     * Получает кнопки подсказок для вопроса сюжета
     */
    private function getStoryHintButtons(User $user, StoryChapter $chapter, StoryStep $step): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof \QuizBot\Domain\Model\UserProfile) {
            return [];
        }

        // Проверяем, использована ли уже подсказка в этом шаге
        $cacheKey = sprintf('story_hint_used_%d_%d', $user->getKey(), $step->getKey());
        try {
            $hintUsed = $this->cache->get($cacheKey, function () {
                return false;
            });
            if ($hintUsed) {
                return [];
            }
        } catch (\Throwable $e) {
            // Если не удалось проверить, продолжаем
        }

        // Проверяем наличие монет
        $hintCost = HintService::getHintCost();
        if ($profile->coins < $hintCost) {
            return [];
        }

        return [
            [
                'text' => sprintf('💡 50/50 (%d💰)', $hintCost),
                'callback_data' => sprintf('story-hint:%s:%s:fifty_fifty', $chapter->code, $step->code),
            ],
            [
                'text' => sprintf('⏱ +15 сек (%d💰)', $hintCost),
                'callback_data' => sprintf('story-hint:%s:%s:time_boost', $chapter->code, $step->code),
            ],
        ];
    }

    /**
     * Обрабатывает использование подсказки в сюжете
     */
    private function handleStoryHintAction($chatId, string $data, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль. Попробуйте /start.');

            return;
        }

        if (!preg_match('/^story-hint:([^:]+):([^:]+):(\w+)$/', $data, $matches)) {
            $this->sendText($chatId, 'Не удалось обработать подсказку. Попробуйте снова.');

            return;
        }

        [, $chapterCode, $stepCode, $hintType] = $matches;

        $chapter = \QuizBot\Domain\Model\StoryChapter::query()->where('code', $chapterCode)->first();
        $step = $chapter ? \QuizBot\Domain\Model\StoryStep::query()
            ->where('chapter_id', $chapter->getKey())
            ->where('code', $stepCode)
            ->first() : null;

        if ($step === null) {
            $this->sendText($chatId, 'Шаг сюжета не найден.');

            return;
        }

        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof \QuizBot\Domain\Model\UserProfile) {
            $this->sendText($chatId, 'Профиль не найден.');

            return;
        }

        // Проверяем, использована ли уже подсказка
        $cacheKey = sprintf('story_hint_used_%d_%d', $user->getKey(), $step->getKey());
        try {
            $hintUsed = $this->cache->get($cacheKey, function () {
                return false;
            });
            if ($hintUsed) {
                $this->sendText($chatId, 'Подсказка уже использована в этом вопросе.');

                return;
            }
        } catch (\Throwable $e) {
            // Продолжаем
        }

        $hintCost = HintService::getHintCost();
        if ($profile->coins < $hintCost) {
            $this->sendText($chatId, sprintf('Недостаточно монет. Нужно: %d', $hintCost));

            return;
        }

        try {
            switch ($hintType) {
                case 'fifty_fifty':
                    $this->handleStoryFiftyFifty($chatId, $user, $chapter, $step, $profile);
                    break;

                case 'time_boost':
                    $profile->coins = max(0, $profile->coins - $hintCost);
                    $profile->save();
                    try {
                        $this->cache->delete($cacheKey);
                        $this->cache->get($cacheKey, static function () {
                            return true;
                        });
                    } catch (\Throwable $e) {
                        // Игнорируем ошибки кеша
                    }
                    // Увеличиваем время в кеше
                    $timeKey = sprintf('story_question_start_%d_%d', $user->getKey(), $step->getKey());
                    try {
                        $currentTime = $this->cache->get($timeKey, static function () {
                            return time();
                        });
                        $newTime = $currentTime - 15;
                        $this->cache->delete($timeKey);
                        $this->cache->get($timeKey, static function () use ($newTime) {
                            return $newTime;
                        });
                    } catch (\Throwable $e) {
                        // Продолжаем
                    }
                    $this->sendText($chatId, '⏱ Добавлено 15 секунд времени!');
                    break;

                default:
                    $this->sendText($chatId, 'Неизвестный тип подсказки.');
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка использования подсказки в сюжете', [
                'error' => $exception->getMessage(),
                'chapter_code' => $chapterCode,
                'step_code' => $stepCode,
                'hint_type' => $hintType,
                'user_id' => $user->getKey(),
            ]);
            $this->sendText($chatId, '⚠️ ' . $exception->getMessage());
        }
    }

    private function handleTrueFalseStart($chatId, ?User $user): void
    {
        if (!$user instanceof User) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start и попробуй снова.');

            return;
        }

        $fact = $this->trueFalseService->startSession($user);

        if (!$fact instanceof TrueFalseFact) {
            $this->sendText($chatId, '⚠️ Не удалось загрузить факты. Попробуйте позже.');

            return;
        }

        $this->sendTrueFalseFactMessage($chatId, $fact, 0, $user);
    }

    private function handleTrueFalseAnswer($chatId, string $data, ?User $user): void
    {
        if (!$user instanceof User) {
            $this->sendText($chatId, 'Не удалось определить профиль. Нажми /start и попробуй снова.');

            return;
        }

        if (!preg_match('/^tf:answer:(\d+):([01])$/', $data, $matches)) {
            $this->sendText($chatId, 'Не удалось обработать ответ. Попробуйте снова.');

            return;
        }

        $factId = (int) $matches[1];
        $answer = $matches[2] === '1';

        // Проверяем таймаут (15 секунд)
        $timeoutSeconds = 15;
        $cacheKey = sprintf('tf_question_start:%d', $user->getKey());
        $startTime = $this->cache->get($cacheKey, static fn () => null);
        
        // Удаляем кэш времени начала, чтобы остановить таймер
        $this->cache->delete($cacheKey);
        
        $timedOut = false;
        if ($startTime !== null) {
            $elapsed = time() - $startTime;
            if ($elapsed > $timeoutSeconds) {
                $timedOut = true;
            }
        }

        if ($timedOut) {
            // Время истекло - засчитываем как неверный ответ
            $result = $this->trueFalseService->handleAnswer($user, $factId, !$this->trueFalseService->getCurrentFact($user)?->is_true);
            $result['is_correct'] = false;
            $result['timed_out'] = true;
        } else {
            $result = $this->trueFalseService->handleAnswer($user, $factId, $answer);
            $result['timed_out'] = false;
        }

        if (!$result['fact'] instanceof TrueFalseFact) {
            $this->sendText($chatId, '⚠️ Факт не найден. Нажми /truth, чтобы начать заново.');

            return;
        }

        $this->sendTrueFalseResultMessage($chatId, $result);

        // Если ответ правильный - продолжаем игру, если нет - конец игры
        if ($result['is_correct']) {
            // Задержка 3 секунды перед следующим вопросом
            sleep(3);

            if ($result['next_fact'] instanceof TrueFalseFact) {
                $this->sendTrueFalseFactMessage($chatId, $result['next_fact'], $result['streak'], $user);
            } else {
                $this->sendText($chatId, '🎉 Поздравляем! Ты ответил на все вопросы! Нажми /truth, чтобы сыграть снова.');
            }
        }
        // Если ответ неверный или время истекло - игра закончена, итоги уже показаны в sendTrueFalseResultMessage
    }

    private function sendTrueFalseFactMessage($chatId, TrueFalseFact $fact, int $streak, ?User $user = null): void
    {
        $timeoutSeconds = 15;
        
        // Сохраняем время начала вопроса для проверки таймаута
        if ($user instanceof User) {
            $cacheKey = sprintf('tf_question_start:%d', $user->getKey());
            $this->cache->delete($cacheKey);
            $startTime = time();
            $this->cache->get($cacheKey, static fn () => $startTime);
        }

        $lines = [
            '🧠 <b>Правда или ложь</b>',
            sprintf('⏱ <b>%d сек.</b>', $timeoutSeconds),
        ];

        if ($streak > 0) {
            $lines[] = sprintf('🔥 Серия: %d', $streak);
        }

        $lines[] = '';
        $lines[] = htmlspecialchars($fact->statement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines[] = '';
        $lines[] = 'Выбери ответ:';

        $keyboard = [
            [
                [
                    'text' => '✅ Правда',
                    'callback_data' => sprintf('tf:answer:%d:1', $fact->getKey()),
                ],
                [
                    'text' => '❌ Ложь',
                    'callback_data' => sprintf('tf:answer:%d:0', $fact->getKey()),
                ],
            ],
        ];

        $response = $this->telegramClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => $keyboard,
                ],
            ],
        ]);

        // Запускаем скрипт для динамического обновления таймера
        if ($user instanceof User) {
            try {
                $responseBody = json_decode($response->getBody()->getContents(), true);
                $messageId = $responseBody['result']['message_id'] ?? null;

                if ($messageId !== null) {
                    $this->launchTrueFalseTimer(
                        $chatId,
                        $messageId,
                        $user->getKey(),
                        $fact->getKey(),
                        implode("\n", $lines),
                        json_encode(['inline_keyboard' => $keyboard]),
                        $timeoutSeconds,
                        $streak
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->error('Ошибка запуска таймера Правда/Ложь', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function launchTrueFalseTimer(
        $chatId,
        int $messageId,
        int $userId,
        int $factId,
        string $originalText,
        string $replyMarkupJson,
        int $timeoutSeconds,
        int $streak
    ): void {
        $reflection = new \ReflectionClass($this);
        $basePath = dirname($reflection->getFileName(), 5);
        $scriptPath = $basePath . '/bin/true_false_timer.php';

        if (!file_exists($scriptPath)) {
            $this->logger->warning('Скрипт таймера Правда/Ложь не найден', ['script_path' => $scriptPath]);
            return;
        }

        $phpPath = PHP_BINARY;
        if (strpos($phpPath, 'fpm') !== false) {
            $possiblePaths = ['/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php8.2', '/usr/bin/php8.1'];
            foreach ($possiblePaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $phpPath = $path;
                    break;
                }
            }
        }

        $this->logger->info('Запуск таймера Правда/Ложь', [
            'script_path' => $scriptPath,
            'php_path' => $phpPath,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'user_id' => $userId,
            'fact_id' => $factId,
        ]);

        $logFile = $basePath . '/storage/logs/tf_timer.log';
        
        $command = sprintf(
            'cd %s && nohup %s %s %s %d %d %d %s %s %d %d >> %s 2>&1 &',
            escapeshellarg($basePath),
            escapeshellarg($phpPath),
            escapeshellarg($scriptPath),
            escapeshellarg((string) $chatId),
            $messageId,
            $userId,
            $factId,
            escapeshellarg($originalText),
            escapeshellarg($replyMarkupJson),
            $timeoutSeconds,
            $streak,
            escapeshellarg($logFile)
        );

        $this->logger->info('Команда запуска таймера', ['command' => $command]);
        exec($command);
    }

    /**
     * @param array{
     *  fact: TrueFalseFact|null,
     *  is_correct: bool,
     *  explanation: string|null,
     *  correct_answer: bool,
     *  streak: int,
     *  record: int,
     *  record_updated: bool,
     *  timed_out?: bool
     * } $result
     */
    private function sendTrueFalseResultMessage($chatId, array $result): void
    {
        /** @var TrueFalseFact|null $fact */
        $fact = $result['fact'];

        if (!$fact instanceof TrueFalseFact) {
            return;
        }

        $lines = [];
        $timedOut = $result['timed_out'] ?? false;
        $isCorrect = $result['is_correct'] ?? false;
        
        if ($isCorrect) {
            // Правильный ответ - продолжаем игру
            $lines[] = '✅ <b>Правильно!</b>';
            $lines[] = '';
            $lines[] = '<b>Утверждение:</b>';
            $lines[] = htmlspecialchars($fact->statement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lines[] = '';
            $lines[] = sprintf('Правильный ответ: <b>%s</b>', $result['correct_answer'] ? 'Правда' : 'Ложь');

            if (!empty($result['explanation'])) {
                $lines[] = '';
                $lines[] = htmlspecialchars((string) $result['explanation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $lines[] = '';
            $lines[] = sprintf('🔥 Серия: <b>%d</b>', (int) $result['streak']);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $lines),
                    'parse_mode' => 'HTML',
                ],
            ]);
        } else {
            // Неверный ответ или время истекло - КОНЕЦ ИГРЫ
            if ($timedOut) {
                $lines[] = '⏱ <b>Время истекло!</b>';
            } else {
                $lines[] = '❌ <b>Неверно!</b>';
            }
            
            $lines[] = '';
            $lines[] = '━━━━━━━━━━━━━━━━';
            $lines[] = '🏁 <b>ИГРА ОКОНЧЕНА</b>';
            $lines[] = '━━━━━━━━━━━━━━━━';
            $lines[] = '';
            $lines[] = '<b>Утверждение:</b>';
            $lines[] = htmlspecialchars($fact->statement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lines[] = '';
            $lines[] = sprintf('Правильный ответ: <b>%s</b>', $result['correct_answer'] ? 'Правда' : 'Ложь');

            if (!empty($result['explanation'])) {
                $lines[] = '';
                $lines[] = htmlspecialchars((string) $result['explanation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $lines[] = '';
            $lines[] = '━━━━━━━━━━━━━━━━';
            $lines[] = sprintf('📊 Твоя серия: <b>%d</b>', (int) $result['streak']);
            $lines[] = sprintf('🏆 Лучший результат: <b>%d</b>', (int) $result['record']);

            if ($result['record_updated'] ?? false) {
                $lines[] = '';
                $lines[] = '🎉 <b>Новый рекорд!</b>';
            }

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $lines),
                    'parse_mode' => 'HTML',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 Играть снова', 'callback_data' => 'tf:start'],
                            ],
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * Обрабатывает подсказку 50/50 для сюжета
     */
    private function handleStoryFiftyFifty($chatId, User $user, StoryChapter $chapter, StoryStep $step, \QuizBot\Domain\Model\UserProfile $profile): void
    {
        $step->loadMissing('question.answers');
        $question = $step->question;

        if ($question === null) {
            $this->sendText($chatId, 'Вопрос не найден.');

            return;
        }

        $answers = $question->answers;
        $correctAnswer = $answers->firstWhere('is_correct', true);

        if ($correctAnswer === null) {
            $this->sendText($chatId, 'Правильный ответ не найден.');

            return;
        }

        $incorrectAnswers = $answers->where('is_correct', false)->values();
        $toRemove = $incorrectAnswers->shuffle()->take(2);
        $toRemoveIds = $toRemove->pluck('id')->toArray();
        $remainingAnswers = $answers->reject(function ($answer) use ($toRemoveIds) {
            return in_array($answer->id, $toRemoveIds, true);
        });

        // Списываем монеты
        $hintCost = HintService::getHintCost();
        $profile->coins = max(0, $profile->coins - $hintCost);
        $profile->save();

        // Отмечаем подсказку как использованную
        $cacheKey = sprintf('story_hint_used_%d_%d', $user->getKey(), $step->getKey());
        try {
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, static function () {
                return true;
            });
        } catch (\Throwable $e) {
            // Игнорируем ошибки кеша
        }

        // Отправляем обновленное сообщение
        $textLines = [
            $this->messageFormatter->questionBox(
                htmlspecialchars($question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            ),
            '',
            '💡 <i>Использована подсказка 50/50. Убрано неправильных ответов: 2</i>',
        ];

        $buttons = [];
        $row = [];

        foreach ($remainingAnswers as $index => $answer) {
            $row[] = [
                'text' => htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'callback_data' => sprintf('story-answer:%s:%s:%d', $chapter->code, $step->code, $answer->getKey()),
            ];

            if (count($row) === 2 || $index === count($remainingAnswers) - 1) {
                $buttons[] = $row;
                $row = [];
            }
        }

        $this->sendText($chatId, implode("\n", $textLines));
        $this->sendText($chatId, 'Выберите ответ:', false, [
            'reply_markup' => [
                'inline_keyboard' => $buttons,
            ],
        ]);
    }

    private function handleAnswerAction($chatId, int $sessionId, int $answerId, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, 'Не удалось определить профиль для проверки ответа.');

            return;
        }

        $session = $this->gameSessionService->findSessionForUser($user, $sessionId);

        if ($session === null) {
            $this->sendText($chatId, 'Сессия не найдена. Запустите новый раунд /play.');

            return;
        }

        try {
            $result = $this->gameSessionService->submitAnswer($session, $answerId);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка обработки ответа', [
                'error' => $exception->getMessage(),
                'session_id' => $sessionId,
                'user_id' => $user->getKey(),
            ]);

            $this->sendText($chatId, '⚠️ Ответ не обработан. Попробуйте начать новую игру.');

            return;
        }

        $session = $result['session'];
        $isCorrect = $result['is_correct'];
        $question = $session->currentQuestion;
        $correctAnswers = $result['correct_answers'];
        $isLastQuestion = $result['is_last_question'];
        $rewards = $result['rewards'] ?? null;
        $payload = $session->payload ?? [];
        $totalQuestions = (int) ($payload['total'] ?? 1);
        $answeredCount = count($payload['answers'] ?? []);

        $textLines = [];

        if ($isCorrect) {
            $textLines[] = '✅ <b>Верно!</b>';
            $textLines[] = '🟢 +10 очков за ответ.';
        } else {
            $textLines[] = '❌ <b>Неверно.</b>';
            $textLines[] = '🔴 0 очков за этот вопрос.';
        }

        $textLines[] = '';
        $textLines[] = htmlspecialchars($question->question_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (!$isCorrect) {
            $correctTexts = array_map(
                fn ($answer) => '• ' . htmlspecialchars($answer->answer_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $correctAnswers
            );

            if ($correctTexts) {
                $textLines[] = '';
                $textLines[] = 'Правильные ответы:';
                $textLines = array_merge($textLines, $correctTexts);
            }
        }

        if (!empty($question->explanation)) {
            $textLines[] = '';
            $textLines[] = '💡 ' . htmlspecialchars($question->explanation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $textLines[] = '';
        $textLines[] = sprintf('📊 Прогресс: %d из %d вопросов.', $answeredCount, $totalQuestions);

        $textLines[] = '';
        $textLines[] = $isLastQuestion
            ? 'Раунд завершён! Нажми /play, чтобы начать новый.'
            : 'Готов? Следующий вопрос уже ждёт!';

        $this->sendText($chatId, implode("\n", $textLines));

        if ($isLastQuestion) {
            $summaryLines = [
                '🏁 <b>Итоги раунда</b>',
                sprintf('🟢 Очки: +%d', $session->score),
                sprintf('🟢 Правильных ответов: %d', $session->correct_count),
                sprintf('🔴 Ошибок: %d', $session->incorrect_count),
                sprintf('🟢 Макс. серия: %d', $session->streak),
            ];

            if ($rewards !== null) {
                $summaryLines[] = sprintf('🟢 Опыт: +%d', $rewards['experience']);
                $summaryLines[] = sprintf('🟢 Монеты: +%d', $rewards['coins']);
            }

            $summaryLines[] = '';
            $summaryLines[] = 'Спасибо за игру! Попробуй другую категорию через /play.';

            $this->sendText($chatId, implode("\n", $summaryLines));

            return;
        }

        $nextQuestion = $this->gameSessionService->advanceSession($session);

        if ($nextQuestion !== null) {
            $this->sendQuestion($chatId, $session, $nextQuestion);

            return;
        }

        $this->sendText($chatId, 'Следующий вопрос пока недоступен. Попробуй начать новый раунд /play.');
    }

    private function handleAdminAction($chatId, string $data, ?User $user): void
    {
        $this->logger->debug('handleAdminAction вызван', [
            'data' => $data,
            'user_id' => $user?->getKey(),
            'chat_id' => $chatId,
        ]);

        if ($user === null) {
            $this->logger->warning('Админ-действие без пользователя', ['data' => $data]);
            $this->sendText($chatId, '❌ Ошибка: не удалось определить пользователя.');

            return;
        }

        if (!$this->adminService->isAdmin($user)) {
            $this->logger->warning('Попытка админ-действия без прав', [
                'user_id' => $user->getKey(),
                'data' => $data,
            ]);
            $this->sendText($chatId, '❌ У вас нет прав администратора.');

            return;
        }

        try {
            if ($data === 'admin:finish_all_duels') {
                $this->logger->debug('Завершение всех дуэлей');
                $this->handleFinishAllDuels($chatId);

                return;
            }

            if ($data === 'admin:finish_duel_by_username') {
                $this->logger->debug('Запрос завершения дуэли по нику');
                $this->handleFinishDuelByUsernameRequest($chatId, $user);

                return;
            }

            if ($data === 'admin:reset_ratings') {
                $this->logger->debug('Сброс рейтинга');
                $this->handleResetRatings($chatId);

                return;
            }

            if ($data === 'admin:stats') {
                $this->logger->debug('Получение статистики');
                $this->handleAdminStats($chatId);

                return;
            }

            if ($this->startsWith($data, 'admin:reply:')) {
                $targetUserId = (int) substr($data, strlen('admin:reply:'));
                $this->handleAdminReply($chatId, $user, $targetUserId);

                return;
            }

            if ($this->startsWith($data, 'admin:reply_to_user:')) {
                $targetUserId = (int) substr($data, strlen('admin:reply_to_user:'));
                $this->handleAdminReply($chatId, $user, $targetUserId);

                return;
            }

            $this->logger->warning('Неизвестное админ-действие', ['data' => $data]);
            $this->sendText($chatId, '❌ Неизвестное админ-действие.');
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка в handleAdminAction', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sendText($chatId, '❌ Произошла ошибка: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    }

    /**
     * Обрабатывает запрос админа на ответ пользователю
     */
    private function handleAdminReply($chatId, User $adminUser, int $targetUserId): void
    {
        $this->logger->debug('Админ хочет ответить пользователю', [
            'admin_id' => $adminUser->getKey(),
            'target_user_id' => $targetUserId,
        ]);

        $targetUser = User::find($targetUserId);
        if (!$targetUser instanceof User) {
            $this->sendText($chatId, '❌ Не удалось найти пользователя для ответа.');
            return;
        }

        // Устанавливаем флаг в кеше, что админ хочет ответить пользователю
        $cacheKey = sprintf('admin:reply_to_user:%d:%d', $adminUser->getKey(), $targetUserId);
        try {
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, static function () {
                return true;
            });
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при установке флага ответа админа', [
                'error' => $e->getMessage(),
                'cache_key' => $cacheKey,
            ]);
        }

        $targetUserName = $this->formatUserName($targetUser);
        $text = sprintf(
            "💬 <b>Ответ пользователю</b>\n\n" .
            "Пользователь: %s\n" .
            "Напишите ответ, и он будет отправлен пользователю.",
            $targetUserName
        );

        $this->sendText($chatId, $text);
    }

    private function formatUserName(User $user): string
    {
        if (!empty($user->first_name) && !empty($user->last_name)) {
            return htmlspecialchars($user->first_name . ' ' . $user->last_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->first_name)) {
            return htmlspecialchars($user->first_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->username)) {
            return '@' . htmlspecialchars($user->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return 'Пользователь #' . $user->getKey();
    }

    private function handleFinishAllDuels($chatId): void
    {
        try {
            $result = $this->adminService->finishAllActiveDuels();

            $text = sprintf(
                "✅ <b>Завершение дуэлей</b>\n\n" .
                "Завершено: %d\n" .
                "Отменено: %d\n",
                $result['completed'],
                $result['cancelled']
            );

            if (!empty($result['errors'])) {
                $text .= "\n⚠️ Ошибки:\n" . implode("\n", array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $text .= sprintf("\n... и ещё %d ошибок", count($result['errors']) - 5);
                }
            }

            $this->sendText($chatId, $text);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при завершении всех дуэлей', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->sendText($chatId, '❌ Ошибка при завершении дуэлей: ' . $e->getMessage());
        }
    }

    private function handleResetRatings($chatId): void
    {
        try {
            $updated = $this->adminService->resetAllRatings();

            $text = sprintf(
                "✅ <b>Сброс рейтинга</b>\n\n" .
                "Рейтинг сброшен до 0 у <b>%d</b> пользователей.",
                $updated
            );

            $this->sendText($chatId, $text);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при сбросе рейтинга', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->sendText($chatId, '❌ Ошибка при сбросе рейтинга: ' . $e->getMessage());
        }
    }

    private function handleFinishDuelByUsernameRequest($chatId, ?User $user): void
    {
        if ($user === null) {
            $this->sendText($chatId, '❌ Ошибка: не удалось определить пользователя.');
            return;
        }

        $text = "🎯 <b>Завершение дуэли по нику</b>\n\n" .
                "Отправь мне юзернейм игрока в формате <b>@username</b>.\n" .
                "Будет найдена и завершена активная дуэль этого игрока.\n\n" .
                "Результаты будут отправлены обоим участникам дуэли.";

        $this->sendText($chatId, $text);
    }

    private function handleAdminStats($chatId): void
    {
        try {
            $activeDuels = \QuizBot\Domain\Model\Duel::query()
                ->whereIn('status', ['waiting', 'matched', 'in_progress'])
                ->count();

            $totalUsers = \QuizBot\Domain\Model\User::query()->count();
            $totalDuels = \QuizBot\Domain\Model\Duel::query()->count();

            $text = sprintf(
                "📊 <b>Статистика</b>\n\n" .
                "Активных дуэлей: %d\n" .
                "Всего пользователей: %d\n" .
                "Всего дуэлей: %d",
                $activeDuels,
                $totalUsers,
                $totalDuels
            );

            $this->sendText($chatId, $text);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при получении статистики', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->sendText($chatId, '❌ Ошибка при получении статистики: ' . $e->getMessage());
        }
    }

    /**
     * Показывает рейтинг дуэлей
     */
    private function handleDuelLeaderboard($chatId, ?User $user): void
    {
        try {
            $topPlayers = $this->userService->getTopPlayersByRating(10);
            
            // Фильтруем игроков с 0 рейтингом
            $topPlayers = array_values(array_filter($topPlayers, fn($entry) => $entry['rating'] > 0));
            
            if (empty($topPlayers)) {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => '📊 Рейтинг дуэлей пока пуст. Сыграй в дуэль, чтобы попасть в топ!',
                        'parse_mode' => 'HTML',
                        'reply_markup' => $this->getMainKeyboard(),
                    ],
                ]);
                return;
            }

            $lines = [
                '⚔️ <b>РЕЙТИНГ ДУЭЛЕЙ</b>',
                '',
            ];

            $medals = ['🥇', '🥈', '🥉'];
            $position = 0;

            foreach ($topPlayers as $entry) {
                $position++;
                $playerUser = $entry['user'];
                $rating = $entry['rating'];
                $rank = $this->profileFormatter->getRankByRating($rating);

                $userName = $this->formatUserName($playerUser);

                if ($position <= 3) {
                    $positionDisplay = $medals[$position - 1];
                } else {
                    $positionDisplay = sprintf('%d.', $position);
                }

                $lines[] = sprintf(
                    '%s <b>%s</b>',
                    $positionDisplay,
                    $userName
                );
                
                $lines[] = $rank['name'];
                $lines[] = sprintf('   ⭐ Рейтинг: <b>%d</b>', $rating);
                $lines[] = '';
            }

            // Показываем позицию текущего пользователя, если он не в топе
            if ($user !== null) {
                $userPosition = $this->userService->getUserRatingPosition($user);
                
                if ($userPosition !== null) {
                    $user = $this->userService->ensureProfile($user);
                    $userProfile = $user->profile;
                    
                    if ($userProfile instanceof \QuizBot\Domain\Model\UserProfile) {
                        $userRating = (int) $userProfile->rating;
                        
                        if ($userRating > 0) {
                            $userRank = $this->profileFormatter->getRankByRating($userRating);
                            
                            $inTop = false;
                            foreach ($topPlayers as $entry) {
                                if ($entry['user']->getKey() === $user->getKey()) {
                                    $inTop = true;
                                    break;
                                }
                            }
                            
                            if (!$inTop) {
                                $lines[] = '━━━━━━━━━━━━━━━━';
                                $lines[] = sprintf('📍 <b>Твоя позиция: %d</b>', $userPosition);
                                $lines[] = sprintf('%s | ⭐ <b>%d</b>', $userRank['name'], $userRating);
                            }
                        }
                    }
                }
            }

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $lines),
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при отправке рейтинга дуэлей', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⚠️ Не удалось загрузить рейтинг. Попробуй позже.',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
        }
    }

    /**
     * Показывает рейтинг "Правда или ложь" по лучшей серии
     */
    private function handleTrueFalseLeaderboard($chatId, ?User $user): void
    {
        try {
            $topPlayers = $this->userService->getTopPlayersByTrueFalseRecord(10);
            
            if (empty($topPlayers)) {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $chatId,
                        'text' => '🧠 Рейтинг «Правда или ложь» пока пуст. Сыграй, чтобы попасть в топ!',
                        'parse_mode' => 'HTML',
                        'reply_markup' => $this->getMainKeyboard(),
                    ],
                ]);
                return;
            }

            $lines = [
                '🧠 <b>РЕЙТИНГ «ПРАВДА ИЛИ ЛОЖЬ»</b>',
                '<i>Лучшие серии правильных ответов</i>',
                '',
            ];

            $medals = ['🥇', '🥈', '🥉'];

            foreach ($topPlayers as $entry) {
                $position = $entry['position'];
                $playerUser = $entry['user'];
                $record = $entry['record'];

                $userName = $this->formatUserName($playerUser);

                if ($position <= 3) {
                    $positionDisplay = $medals[$position - 1];
                } else {
                    $positionDisplay = sprintf('%d.', $position);
                }

                $lines[] = sprintf(
                    '%s <b>%s</b>',
                    $positionDisplay,
                    $userName
                );
                $lines[] = sprintf('   🔥 Серия: <b>%d</b>', $record);
                $lines[] = '';
            }

            // Показываем позицию текущего пользователя, если он не в топе
            if ($user !== null) {
                $userPosition = $this->userService->getUserTrueFalsePosition($user);
                
                if ($userPosition !== null) {
                    $user = $this->userService->ensureProfile($user);
                    $userProfile = $user->profile;
                    
                    if ($userProfile instanceof \QuizBot\Domain\Model\UserProfile) {
                        $userRecord = (int) ($userProfile->true_false_record ?? 0);
                        
                        if ($userRecord > 0) {
                            $inTop = false;
                            foreach ($topPlayers as $entry) {
                                if ($entry['user']->getKey() === $user->getKey()) {
                                    $inTop = true;
                                    break;
                                }
                            }
                            
                            if (!$inTop) {
                                $lines[] = '━━━━━━━━━━━━━━━━';
                                $lines[] = sprintf('📍 <b>Твоя позиция: %d</b>', $userPosition);
                                $lines[] = sprintf('🔥 Серия: <b>%d</b>', $userRecord);
                            }
                        }
                    }
                }
            }

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => implode("\n", $lines),
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при отправке рейтинга Правда/Ложь', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => '⚠️ Не удалось загрузить рейтинг. Попробуй позже.',
                    'reply_markup' => $this->getMainKeyboard(),
                ],
            ]);
        }
    }
}

