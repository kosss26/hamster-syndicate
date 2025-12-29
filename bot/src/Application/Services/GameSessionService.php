<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\Answer;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\GameSession;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;

class GameSessionService
{
    private const DEFAULT_QUESTION_COUNT = 5;

    private Logger $logger;

    private QuestionSelector $questionSelector;

    private UserService $userService;

    private ?ReferralService $referralService;

    private ?BoostService $boostService;

    public function __construct(Logger $logger, QuestionSelector $questionSelector, UserService $userService, ?ReferralService $referralService = null, ?BoostService $boostService = null)
    {
        $this->logger = $logger;
        $this->questionSelector = $questionSelector;
        $this->userService = $userService;
        $this->referralService = $referralService;
        $this->boostService = $boostService;
    }

    /**
     * @return array{session: GameSession, question: Question}
     */
    public function startCategoryRound(User $user, string $categoryCode, int $questionCount = self::DEFAULT_QUESTION_COUNT): array
    {
        $category = Category::query()->where('code', $categoryCode)->first();

        if ($category === null) {
            throw new \RuntimeException('Категория не найдена.');
        }

        $questionCount = max(1, $questionCount);

        $questions = $this->questionSelector->selectQuestions($category, $questionCount);

        if (empty($questions)) {
            throw new \RuntimeException('В выбранной категории пока нет вопросов.');
        }

        $firstQuestion = $questions[0];

        $queue = array_map(
            static fn (Question $question): int => (int) $question->getKey(),
            $questions
        );

        $this->closeActiveSessions($user);

        $payload = [
            'question_queue' => $queue,
            'current_index' => 0,
            'answers' => [],
            'total' => count($queue),
        ];

        $session = new GameSession([
            'user_id' => $user->getKey(),
            'mode' => 'category',
            'category_id' => $category->getKey(),
            'state' => 'awaiting_answer',
            'current_question_id' => $firstQuestion->getKey(),
            'score' => 0,
            'correct_count' => 0,
            'incorrect_count' => 0,
            'streak' => 0,
            'payload' => $payload,
            'started_at' => Carbon::now(),
        ]);

        $session->save();

        $this->logger->info(sprintf(
            'Создана игровая сессия %d для пользователя %d по категории %s',
            $session->getKey(),
            $user->getKey(),
            $category->code
        ));

        $session->setRelation('currentQuestion', $firstQuestion);

        return [
            'session' => $session,
            'question' => $firstQuestion,
        ];
    }

    public function findSessionForUser(User $user, int $sessionId): ?GameSession
    {
        return GameSession::query()
            ->where('id', $sessionId)
            ->where('user_id', $user->getKey())
            ->first();
    }

    /**
     * @return array{
     *     session: GameSession,
     *     answer: Answer,
     *     is_correct: bool,
     *     correct_answers: array<int, Answer>,
     *     is_last_question: bool,
     *     rewards: array<string, int>|null
     * }
     */
    public function submitAnswer(GameSession $session, int $answerId): array
    {
        if ($session->state !== 'awaiting_answer') {
            throw new \RuntimeException('Сессия находится в состоянии ' . $session->state);
        }

        $question = $session->currentQuestion ?: $session->currentQuestion()->first();

        if ($question === null) {
            throw new \RuntimeException('В сессии отсутствует активный вопрос.');
        }

        $question->loadMissing(['answers', 'category']);

        $answer = $question->answers()
            ->where('id', $answerId)
            ->first();

        if ($answer === null) {
            throw new \RuntimeException('Ответ не найден.');
        }

        $payload = $session->payload ?? [];
        $queue = $payload['question_queue'] ?? [];
        $currentIndex = (int) ($payload['current_index'] ?? 0);

        if (!isset($queue[$currentIndex]) || $queue[$currentIndex] !== $question->getKey()) {
            $this->logger->warning('Несоответствие вопроса при ответе', [
                'session_id' => $session->getKey(),
                'expected_question_id' => $queue[$currentIndex] ?? null,
                'actual_question_id' => $question->getKey(),
            ]);
        }

        $isCorrect = $answer->is_correct;

        if ($isCorrect) {
            $session->score += 10;
            $session->correct_count += 1;
            $session->streak += 1;
        } else {
            $session->incorrect_count += 1;
            $session->streak = 0;
        }

        $answers = $payload['answers'] ?? [];
        $answers[$question->getKey()] = [
            'answer_id' => $answerId,
            'is_correct' => $isCorrect,
            'answered_at' => Carbon::now()->toAtomString(),
        ];

        $payload['answers'] = $answers;
        $payload['current_index'] = $currentIndex;

        $isLastQuestion = $currentIndex >= (count($queue) - 1);

        $rewards = null;

        if ($isLastQuestion) {
            $session->state = 'completed';
            $session->finished_at = Carbon::now();
            $rewards = $this->finalizeSession($session);
        } else {
            $session->state = 'awaiting_next';
            $session->finished_at = null;
        }

        $session->payload = $payload;
        $session->save();

        $correctAnswers = $question->answers
            ->where('is_correct', true)
            ->values()
            ->all();

        $session->setRelation('currentQuestion', $question);

        $this->logger->info(sprintf(
            'Сессия %d: пользователь %d ответил %s (вопрос %d из %d)',
            $session->getKey(),
            $session->user_id,
            $isCorrect ? 'правильно' : 'неправильно',
            $currentIndex + 1,
            count($queue)
        ));

        return [
            'session' => $session,
            'answer' => $answer,
            'is_correct' => $isCorrect,
            'correct_answers' => $correctAnswers,
            'is_last_question' => $isLastQuestion,
            'rewards' => $rewards,
        ];
    }

    public function advanceSession(GameSession $session): ?Question
    {
        $payload = $session->payload ?? [];
        $queue = $payload['question_queue'] ?? [];
        $currentIndex = (int) ($payload['current_index'] ?? 0);
        $nextIndex = $currentIndex + 1;

        if ($nextIndex >= count($queue)) {
            if ($session->state !== 'completed') {
                $session->state = 'completed';
                $session->finished_at = Carbon::now();
                $session->payload = $payload;
                $session->save();
            }

            return null;
        }

        $nextQuestionId = $queue[$nextIndex];
        $payload['current_index'] = $nextIndex;

        $session->current_question_id = $nextQuestionId;
        $session->state = 'awaiting_answer';
        $session->finished_at = null;
        $session->payload = $payload;
        $session->save();

        $question = Question::query()
            ->with(['answers', 'category'])
            ->find($nextQuestionId);

        if ($question === null) {
            $this->logger->error('Следующий вопрос не найден', [
                'session_id' => $session->getKey(),
                'question_id' => $nextQuestionId,
            ]);

            $session->state = 'completed';
            $session->finished_at = Carbon::now();
            $session->payload = $payload;
            $session->save();

            return null;
        }

        $session->setRelation('currentQuestion', $question);

        return $question;
    }

    private function closeActiveSessions(User $user): void
    {
        GameSession::query()
            ->where('user_id', $user->getKey())
            ->whereIn('state', ['awaiting_answer', 'awaiting_next', 'in_progress'])
            ->update([
                'state' => 'cancelled',
                'finished_at' => Carbon::now(),
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function finalizeSession(GameSession $session): array
    {
        $payload = $session->payload ?? [];

        if (isset($payload['finalized_at'])) {
            return $payload['rewards'] ?? ['experience' => 0, 'coins' => 0];
        }

        $user = $session->user ?: $session->user()->first();

        if ($user === null) {
            throw new \RuntimeException('Пользователь для сессии не найден.');
        }

        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            throw new \RuntimeException('Не удалось создать профиль пользователя.');
        }

        $experienceGain = (int) $session->score;
        $coinsGain = (int) max(0, $session->correct_count);

        // Применяем бусты если доступны
        if ($this->boostService) {
            $experienceGain = $this->boostService->applyBoost($user, 'exp_boost', $experienceGain);
            $coinsGain = $this->boostService->applyBoost($user, 'coin_boost', $coinsGain);
        }

        $profile->experience += $experienceGain;
        $profile->coins = max(0, $profile->coins + $coinsGain);
        $profile->story_progress_score += (int) $session->score;
        $profile->save();

        $payload['finalized_at'] = Carbon::now()->toAtomString();
        $payload['rewards'] = [
            'experience' => $experienceGain,
            'coins' => $coinsGain,
        ];

        $session->payload = $payload;
        $session->save();

        $this->logger->info(sprintf(
            'Сессия %d завершена. Начислено опыта: %d, монет: %d',
            $session->getKey(),
            $experienceGain,
            $coinsGain
        ));

        // Проверяем активацию реферала
        $this->checkReferralActivation($user);

        return $payload['rewards'];
    }

    /**
     * Проверяет и активирует реферала если нужно
     */
    private function checkReferralActivation(User $user): void
    {
        if ($this->referralService === null) {
            return;
        }

        try {
            $this->referralService->checkAndActivateReferral($user);
        } catch (\Throwable $e) {
            $this->logger->debug('Не удалось проверить активацию реферала', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

