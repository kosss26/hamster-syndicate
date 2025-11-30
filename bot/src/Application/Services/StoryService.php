<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Carbon\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\StoryChapter;
use QuizBot\Domain\Model\StoryProgress;
use QuizBot\Domain\Model\StoryStep;
use QuizBot\Domain\Model\StoryQuestion;
use QuizBot\Domain\Model\StoryQuestionAnswer;
use QuizBot\Domain\Model\User;

class StoryService
{
    public const STATUS_LOCKED = 'locked';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array<int, array{chapter: StoryChapter, progress: StoryProgress, status: string}>
     */
    public function getChaptersForUser(User $user): array
    {
        $chapters = StoryChapter::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $progressMap = StoryProgress::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('chapter_id');

        $result = [];
        $previousCompleted = true;

        foreach ($chapters as $chapter) {
            $progress = $progressMap->get($chapter->getKey());

            $firstStepId = $this->resolveFirstStepId($chapter);

            if ($progress === null) {
                $progress = new StoryProgress([
                    'user_id' => $user->getKey(),
                    'chapter_id' => $chapter->getKey(),
                    'current_step_id' => $firstStepId,
                    'status' => $previousCompleted ? self::STATUS_AVAILABLE : self::STATUS_LOCKED,
                    'score' => 0,
                    'lives_remaining' => 3,
                    'mistakes' => 0,
                ]);
                $progress->save();
            } elseif ($progress->current_step_id === null && $firstStepId !== null) {
                $progress->current_step_id = $firstStepId;
                $progress->save();
            }

            if ($progress->status === self::STATUS_COMPLETED) {
                $previousCompleted = true;
            } else {
                if ($previousCompleted && $progress->status === self::STATUS_LOCKED) {
                    $progress->status = self::STATUS_AVAILABLE;
                    $progress->save();
                }

                if (!$previousCompleted && $progress->status !== self::STATUS_COMPLETED && $progress->status !== self::STATUS_LOCKED) {
                    $progress->status = self::STATUS_LOCKED;
                    $progress->save();
                }

                $previousCompleted = false;
            }

            $result[] = [
                'chapter' => $chapter,
                'progress' => $progress,
                'status' => (string) $progress->status,
            ];
        }

        return $result;
    }

    /**
     * @return array{
     *     chapter: StoryChapter,
     *     progress: StoryProgress,
     *     status: string,
     *     step: ?StoryStep,
     *     question: ?StoryQuestion,
     *     continue_code: ?string,
     *     completed: bool
     * }
     */
    public function startChapter(User $user, string $chapterCode): array
    {
        $chapter = $this->findChapterByCode($chapterCode);
        $chapters = $this->getChaptersForUser($user);

        $entry = null;

        foreach ($chapters as $item) {
            if ($item['chapter']->getKey() === $chapter->getKey()) {
                $entry = $item;
                break;
            }
        }

        if ($entry === null) {
            throw new \RuntimeException('Не удалось подготовить прогресс для главы.');
        }

        /** @var StoryProgress $progress */
        $progress = $entry['progress'];
        $status = $entry['status'];

        if ($status === self::STATUS_LOCKED) {
            throw new \DomainException('Глава ещё закрыта.');
        }

        $step = $this->resolveCurrentStep($progress, $chapter);

        if ($step === null) {
            $this->logger->warning('В главе отсутствуют активные шаги', [
                'chapter_id' => $chapter->getKey(),
                'chapter_code' => $chapter->code,
            ]);
        }

        if ($status === self::STATUS_AVAILABLE) {
            $progress->status = self::STATUS_IN_PROGRESS;
            $progress->started_at = Carbon::now();
        }

        if ($step !== null && $progress->current_step_id !== $step->getKey()) {
            $progress->current_step_id = $step->getKey();
        }

        if ($progress->isDirty()) {
            $progress->save();
        }

        return $this->prepareState($chapter, $progress, $step);
    }

    /**
     * Продолжение шага без вопроса (повествовательный блок или выбор).
     */
    public function continueStep(User $user, string $chapterCode, string $stepCode, ?string $choiceKey = null): array
    {
        $chapter = $this->findChapterByCode($chapterCode);
        $progress = $this->loadProgress($user, $chapter);
        $currentStep = $this->findStepByCode($chapter, $stepCode);

        if ($currentStep === null) {
            throw new \RuntimeException('Шаг не найден.');
        }

        if ($progress->current_step_id !== $currentStep->getKey()) {
            throw new \RuntimeException('Шаг уже не активен для пользователя.');
        }

        // Если это шаг с выбором, обрабатываем выбор
        if ($currentStep->step_type === StoryStep::TYPE_CHOICE && $choiceKey !== null) {
            $transitions = $currentStep->transitions ?? [];
            $nextCode = $transitions[$choiceKey] ?? null;
        } else {
            $nextCode = $this->resolveTransition($currentStep, null);
        }

        $nextStep = $nextCode !== null ? $this->findStepByCode($chapter, $nextCode) : null;

        return $this->moveToStep($chapter, $progress, $currentStep, $nextStep, null);
    }

    /**
     * Обработка ответа пользователя на вопрос истории.
     */
    public function submitAnswer(User $user, string $chapterCode, string $stepCode, int $answerId): array
    {
        $chapter = $this->findChapterByCode($chapterCode);
        $progress = $this->loadProgress($user, $chapter);
        $currentStep = $this->findStepByCode($chapter, $stepCode);

        if ($currentStep === null) {
            throw new \RuntimeException('Шаг для ответа не найден.');
        }

        if ($progress->current_step_id !== $currentStep->getKey()) {
            throw new \RuntimeException('Шаг уже завершён.');
        }

        // Получаем вопрос истории для этого шага
        $storyQuestion = $currentStep->questions()->orderBy('position')->first();

        if ($storyQuestion === null) {
            throw new \RuntimeException('У шага нет вопроса истории.');
        }

        $storyQuestion->load('answers');

        /** @var StoryQuestionAnswer|null $answer */
        $answer = $storyQuestion->answers()
            ->where('id', $answerId)
            ->first();

        if ($answer === null) {
            throw new \RuntimeException('Ответ не найден.');
        }

        $isCorrect = (bool) $answer->is_correct;

        if ($isCorrect) {
            $progress->score += (int) ($currentStep->reward_points ?? 10);
        } else {
            $progress->mistakes += 1;
            if ($progress->lives_remaining > 0) {
                $progress->lives_remaining -= 1;
            }
        }

        $progress->save();

        $nextCode = $this->resolveTransition($currentStep, $isCorrect);
        $nextStep = $nextCode !== null ? $this->findStepByCode($chapter, $nextCode) : null;

        $state = $this->moveToStep($chapter, $progress, $currentStep, $nextStep, $isCorrect);
        
        // Добавляем информацию об ответе и объяснение
        $state['answer_feedback'] = [
            'is_correct' => $isCorrect,
            'question' => $storyQuestion,
            'correct_answers' => $storyQuestion->answers->where('is_correct', true)->values()->all(),
            'explanation' => $storyQuestion->explanation,
        ];

        return $state;
    }

    /**
     * @return array{
     *     chapter: StoryChapter,
     *     progress: StoryProgress,
     *     step: ?StoryStep,
     *     question: ?StoryQuestion,
     *     continue_code: ?string,
     *     completed: bool
     * }
     */
    private function prepareState(StoryChapter $chapter, StoryProgress $progress, ?StoryStep $step): array
    {
        $question = null;
        $continueCode = null;
        $completed = false;

        if ($step === null) {
            $completed = true;
            if ($progress->status !== self::STATUS_COMPLETED) {
                $progress->status = self::STATUS_COMPLETED;
                $progress->completed_at = Carbon::now();
                $progress->save();
            }
        } else {
            // Загружаем вопрос истории для шага
            if ($step->step_type === StoryStep::TYPE_QUESTION) {
                $question = $step->questions()->orderBy('position')->with('answers')->first();
            }

            if ($step->step_type === StoryStep::TYPE_NARRATIVE || $step->step_type === StoryStep::TYPE_CHOICE) {
                $continueCode = sprintf('story-continue:%s:%s', $chapter->code, $step->code);
            }
        }

        return [
            'chapter' => $chapter,
            'progress' => $progress,
            'status' => $progress->status,
            'step' => $step,
            'question' => $question,
            'continue_code' => $continueCode,
            'completed' => $completed,
        ];
    }

    /**
     * Переход к следующему шагу.
     */
    private function moveToStep(
        StoryChapter $chapter,
        StoryProgress $progress,
        StoryStep $currentStep,
        ?StoryStep $nextStep,
        ?bool $wasCorrect
    ): array {
        if ($nextStep === null) {
            $progress->status = self::STATUS_COMPLETED;
            $progress->completed_at = Carbon::now();
            $progress->current_step_id = null;
        } else {
            $progress->current_step_id = $nextStep->getKey();
        }

        $progress->save();

        return $this->prepareState($chapter, $progress, $nextStep);
    }

    private function resolveTransition(StoryStep $step, ?bool $isCorrect): ?string
    {
        $transitions = $step->transitions ?? [];

        if ($isCorrect === true && isset($transitions['correct'])) {
            return $transitions['correct'];
        }

        if ($isCorrect === false && isset($transitions['incorrect'])) {
            return $transitions['incorrect'];
        }

        if (isset($transitions['next'])) {
            return $transitions['next'];
        }

        if (isset($transitions['auto'])) {
            return $transitions['auto'];
        }

        return null;
    }

    private function findChapterByCode(string $code): StoryChapter
    {
        $chapter = StoryChapter::query()->where('code', $code)->first();

        if ($chapter === null) {
            throw new \RuntimeException(sprintf('Глава с кодом %s не найдена.', $code));
        }

        return $chapter;
    }

    private function findStepByCode(StoryChapter $chapter, string $code): ?StoryStep
    {
        return $chapter->steps()->where('code', $code)->first();
    }

    private function loadProgress(User $user, StoryChapter $chapter): StoryProgress
    {
        $progress = StoryProgress::query()
            ->where('user_id', $user->getKey())
            ->where('chapter_id', $chapter->getKey())
            ->first();

        if ($progress === null) {
            throw new \RuntimeException('Прогресс по главе не найден.');
        }

        return $progress;
    }

    private function resolveCurrentStep(StoryProgress $progress, StoryChapter $chapter): ?StoryStep
    {
        if ($progress->current_step_id !== null) {
            $step = StoryStep::query()
                ->where('id', $progress->current_step_id)
                ->where('chapter_id', $chapter->getKey())
                ->first();

            if ($step instanceof StoryStep) {
                return $step;
            }
        }

        return $chapter->steps()->orderBy('position')->first();
    }

    private function resolveFirstStepId(StoryChapter $chapter): ?int
    {
        return $chapter->steps()
            ->orderBy('position')
            ->value('id');
    }
}
