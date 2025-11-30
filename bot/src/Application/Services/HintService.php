<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\GameSession;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;

/**
 * Сервис для работы с подсказками
 */
class HintService
{
    private const HINT_COST = 10; // Стоимость подсказки в монетах
    private const HINT_TYPES = [
        'fifty_fifty' => '50/50',
        'skip' => 'Пропуск',
        'time_boost' => '+15 сек',
    ];

    private Logger $logger;
    private UserService $userService;
    private GameSessionService $gameSessionService;

    public function __construct(Logger $logger, UserService $userService, GameSessionService $gameSessionService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
        $this->gameSessionService = $gameSessionService;
    }

    /**
     * Проверяет, можно ли использовать подсказку
     */
    public function canUseHint(GameSession $session, User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            return ['can_use' => false, 'reason' => 'Профиль не найден'];
        }

        $payload = $session->payload ?? [];
        $hintsUsed = $payload['hints_used'] ?? [];

        // Проверяем, использована ли уже подсказка в этом раунде
        if (!empty($hintsUsed)) {
            return ['can_use' => false, 'reason' => 'Подсказка уже использована в этом раунде'];
        }

        // Проверяем наличие монет
        if ($profile->coins < self::HINT_COST) {
            return ['can_use' => false, 'reason' => sprintf('Недостаточно монет. Нужно: %d', self::HINT_COST)];
        }

        return ['can_use' => true, 'reason' => null];
    }

    /**
     * Использует подсказку 50/50 (убирает 2 неправильных ответа)
     */
    public function useFiftyFifty(GameSession $session, User $user): array
    {
        $check = $this->canUseHint($session, $user);
        if (!$check['can_use']) {
            throw new \RuntimeException($check['reason'] ?? 'Нельзя использовать подсказку');
        }

        $question = $session->currentQuestion ?: $session->currentQuestion()->first();
        if ($question === null) {
            throw new \RuntimeException('Вопрос не найден');
        }

        $question->loadMissing('answers');
        $answers = $question->answers;

        // Находим правильный ответ
        $correctAnswer = $answers->firstWhere('is_correct', true);
        if ($correctAnswer === null) {
            throw new \RuntimeException('Правильный ответ не найден');
        }

        // Находим неправильные ответы
        $incorrectAnswers = $answers->where('is_correct', false)->values();
        
        // Убираем 2 случайных неправильных ответа
        $toRemove = $incorrectAnswers->shuffle()->take(2);
        $toRemoveIds = $toRemove->pluck('id')->toArray();
        $remainingAnswers = $answers->reject(function ($answer) use ($toRemoveIds) {
            return in_array($answer->id, $toRemoveIds, true);
        });

        // Списываем монеты
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;
        if ($profile instanceof UserProfile) {
            $profile->coins = max(0, $profile->coins - self::HINT_COST);
            $profile->save();
        }

        // Сохраняем информацию об использовании подсказки
        $payload = $session->payload ?? [];
        $payload['hints_used'] = ['fifty_fifty' => Carbon::now()->toAtomString()];
        $session->payload = $payload;
        $session->save();

        $this->logger->info('Использована подсказка 50/50', [
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'question_id' => $question->getKey(),
        ]);

        return [
            'remaining_answers' => $remainingAnswers->values()->all(),
            'removed_count' => $toRemove->count(),
        ];
    }

    /**
     * Пропускает вопрос (переходит к следующему)
     */
    public function useSkip(GameSession $session, User $user): array
    {
        $check = $this->canUseHint($session, $user);
        if (!$check['can_use']) {
            throw new \RuntimeException($check['reason'] ?? 'Нельзя использовать подсказку');
        }

        $question = $session->currentQuestion ?: $session->currentQuestion()->first();
        if ($question === null) {
            throw new \RuntimeException('Вопрос не найден');
        }

        // Списываем монеты
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;
        if ($profile instanceof UserProfile) {
            $profile->coins = max(0, $profile->coins - self::HINT_COST);
            $profile->save();
        }

        // Сохраняем информацию об использовании подсказки
        $payload = $session->payload ?? [];
        $payload['hints_used'] = ['skip' => Carbon::now()->toAtomString()];
        $payload['skipped_questions'] = ($payload['skipped_questions'] ?? []) + [$question->getKey()];
        $session->payload = $payload;
        $session->save();

        // Переходим к следующему вопросу
        $nextQuestion = $this->gameSessionService->advanceSession($session);

        $this->logger->info('Использована подсказка "Пропуск"', [
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'question_id' => $question->getKey(),
        ]);

        return [
            'next_question' => $nextQuestion,
            'skipped_question' => $question,
        ];
    }

    /**
     * Добавляет 15 секунд времени (для сюжета)
     */
    public function useTimeBoost(GameSession $session, User $user): array
    {
        $check = $this->canUseHint($session, $user);
        if (!$check['can_use']) {
            throw new \RuntimeException($check['reason'] ?? 'Нельзя использовать подсказку');
        }

        // Списываем монеты
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;
        if ($profile instanceof UserProfile) {
            $profile->coins = max(0, $profile->coins - self::HINT_COST);
            $profile->save();
        }

        // Сохраняем информацию об использовании подсказки
        $payload = $session->payload ?? [];
        $payload['hints_used'] = ['time_boost' => Carbon::now()->toAtomString()];
        $payload['time_boost_seconds'] = ($payload['time_boost_seconds'] ?? 0) + 15;
        $session->payload = $payload;
        $session->save();

        $this->logger->info('Использована подсказка "+15 сек"', [
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return [
            'added_seconds' => 15,
            'total_boost' => $payload['time_boost_seconds'],
        ];
    }

    /**
     * Получает стоимость подсказки
     */
    public static function getHintCost(): int
    {
        return self::HINT_COST;
    }
}

