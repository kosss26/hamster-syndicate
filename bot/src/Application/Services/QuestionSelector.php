<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\Question;
use QuizBot\Domain\Model\UserAnswerHistory;

class QuestionSelector
{
    private const DUEL_CATEGORY_SLOTS = 5;
    private const DIFFICULTY_EASY_THRESHOLD = 70.0;
    private const DIFFICULTY_MEDIUM_THRESHOLD = 40.0;
    private const DIFFICULTY_MIN_ATTEMPTS = 15;

    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array<int, Question>
     */
    public function selectQuestions(?Category $category, int $limit): array
    {
        $limit = max(1, $limit);

        $baseQuery = Question::query()
            ->where('is_active', true);

        if ($category !== null) {
            $baseQuery->where('category_id', $category->getKey());
        }

        $validQuestions = [];
        $seenIds = [];
        $attempts = 0;
        $maxAttempts = 5;
        $batchSize = max($limit * 3, 12);

        if ($category === null) {
            $availableCategories = (clone $baseQuery)
                ->select('category_id')
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            $targetUniqueCategories = min($limit, count($availableCategories));

            while (count($validQuestions) < $targetUniqueCategories && $attempts < $maxAttempts) {
                $attempts++;

                $batch = (clone $baseQuery)
                    ->with(['answers', 'category'])
                    ->inRandomOrder()
                    ->limit($batchSize)
                    ->get();

                foreach ($batch as $question) {
                    $questionId = (int) $question->getKey();
                    $categoryId = (int) ($question->category_id ?? 0);

                    if ($categoryId <= 0 || isset($seenIds[$questionId])) {
                        continue;
                    }

                    if (!$this->isValidQuestion($question)) {
                        continue;
                    }

                    if (isset($validQuestions[$categoryId])) {
                        continue;
                    }

                    $validQuestions[$categoryId] = $question;
                    $seenIds[$questionId] = true;

                    if (count($validQuestions) >= $targetUniqueCategories) {
                        break;
                    }
                }
            }

            $validQuestions = array_values($validQuestions);
        }

        while (count($validQuestions) < $limit && $attempts < $maxAttempts) {
            $attempts++;

            $batch = (clone $baseQuery)
                ->with(['answers', 'category'])
                ->inRandomOrder()
                ->limit($batchSize)
                ->get();

            foreach ($batch as $question) {
                $questionId = (int) $question->getKey();

                if (isset($seenIds[$questionId])) {
                    continue;
                }

                if (!$this->isValidQuestion($question)) {
                    continue;
                }

                $validQuestions[] = $question;
                $seenIds[$questionId] = true;

                if (count($validQuestions) >= $limit) {
                    break 2;
                }
            }
        }

        if (count($validQuestions) < $limit) {
            throw new \RuntimeException(sprintf(
                'Недостаточно корректных вопросов: требуется %d, доступно %d (категория %s).',
                $limit,
                count($validQuestions),
                $category ? $category->code : 'any'
            ));
        }

        return array_slice($validQuestions, 0, $limit);
    }

    /**
     * @param array<int, int> $excludeQuestionIds
     * @return array<int, Question>
     */
    public function selectDuelQuestions(
        ?Category $category,
        int $limit,
        array $excludeQuestionIds = [],
        int $categorySlots = self::DUEL_CATEGORY_SLOTS
    ): array {
        $limit = max(1, $limit);
        $excludeQuestionIds = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $excludeQuestionIds
        ))));

        if ($category !== null) {
            $pool = $this->loadCandidatePoolForCategory((int) $category->getKey(), $limit * 12, $excludeQuestionIds);
            $selected = $this->pickBalancedFromPool($pool, $limit);

            if (count($selected) < $limit) {
                throw new \RuntimeException(sprintf(
                    'Недостаточно вопросов в категории %s для дуэли: требуется %d, доступно %d.',
                    (string) $category->code,
                    $limit,
                    count($selected)
                ));
            }

            return array_slice($selected, 0, $limit);
        }

        $categoryIds = Question::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($categoryIds === []) {
            throw new \RuntimeException('Не найдено активных категорий для дуэли.');
        }

        shuffle($categoryIds);
        $effectiveSlots = min(max(1, $categorySlots), count($categoryIds), $limit);
        $selectedCategoryIds = array_slice($categoryIds, 0, $effectiveSlots);
        $allocation = $this->buildCategoryAllocation($selectedCategoryIds, $limit);

        $candidateByCategory = [];
        foreach ($allocation as $categoryId => $requiredCount) {
            $batchSize = max($requiredCount * 12, 48);
            $candidateByCategory[$categoryId] = $this->loadCandidatePoolForCategory($categoryId, $batchSize, $excludeQuestionIds);
        }

        $selected = $this->pickBalancedByCategories($candidateByCategory, $allocation, $limit);
        $selectedIds = array_fill_keys(array_map(static fn (Question $question): int => (int) $question->getKey(), $selected), true);

        if (count($selected) < $limit) {
            $fallbackPool = $this->loadGlobalFallbackPool(
                $limit * 16,
                array_values(array_unique(array_merge($excludeQuestionIds, array_keys($selectedIds))))
            );

            foreach ($fallbackPool as $question) {
                $questionId = (int) $question->getKey();
                if (isset($selectedIds[$questionId])) {
                    continue;
                }

                $selected[] = $question;
                $selectedIds[$questionId] = true;
                if (count($selected) >= $limit) {
                    break;
                }
            }
        }

        if (count($selected) < $limit) {
            throw new \RuntimeException(sprintf(
                'Недостаточно вопросов для дуэли: требуется %d, доступно %d.',
                $limit,
                count($selected)
            ));
        }

        return array_slice($selected, 0, $limit);
    }

    /**
     * @param array<int, int> $categoryIds
     * @return array<int, int>
     */
    private function buildCategoryAllocation(array $categoryIds, int $limit): array
    {
        $categoryCount = max(1, count($categoryIds));
        $base = intdiv($limit, $categoryCount);
        $remainder = $limit % $categoryCount;
        $allocation = [];

        foreach ($categoryIds as $index => $categoryId) {
            $allocation[$categoryId] = $base + ($index < $remainder ? 1 : 0);
        }

        return $allocation;
    }

    /**
     * @param array<int, int> $excludeQuestionIds
     * @return array<int, Question>
     */
    private function loadCandidatePoolForCategory(int $categoryId, int $limit, array $excludeQuestionIds): array
    {
        $query = Question::query()
            ->where('is_active', true)
            ->where('category_id', $categoryId);

        if ($excludeQuestionIds !== []) {
            $query->whereNotIn('id', $excludeQuestionIds);
        }

        $batch = $query
            ->with(['answers', 'category'])
            ->inRandomOrder()
            ->limit(max(1, $limit))
            ->get();

        $valid = [];
        foreach ($batch as $question) {
            if (!$this->isValidQuestion($question)) {
                continue;
            }
            $valid[] = $question;
        }

        return $valid;
    }

    /**
     * @param array<int, int> $excludeQuestionIds
     * @return array<int, Question>
     */
    private function loadGlobalFallbackPool(int $limit, array $excludeQuestionIds): array
    {
        $query = Question::query()
            ->where('is_active', true)
            ->with(['answers', 'category'])
            ->inRandomOrder()
            ->limit(max(1, $limit));

        if ($excludeQuestionIds !== []) {
            $query->whereNotIn('id', $excludeQuestionIds);
        }

        $batch = $query->get();
        $valid = [];
        foreach ($batch as $question) {
            if (!$this->isValidQuestion($question)) {
                continue;
            }
            $valid[] = $question;
        }

        return $valid;
    }

    /**
     * @param array<int, Question> $pool
     * @return array<int, Question>
     */
    private function pickBalancedFromPool(array $pool, int $limit): array
    {
        if ($pool === []) {
            return [];
        }

        $difficultyMap = $this->buildDynamicDifficultyMap($pool);
        $targets = $this->buildDifficultyTargets($limit);
        $remainingTargets = $targets;

        $difficultyPools = [
            'easy' => [],
            'medium' => [],
            'hard' => [],
        ];

        foreach ($pool as $question) {
            $questionId = (int) $question->getKey();
            $difficulty = $difficultyMap[$questionId] ?? 'medium';
            $difficultyPools[$difficulty][] = $question;
        }

        foreach (array_keys($difficultyPools) as $difficulty) {
            shuffle($difficultyPools[$difficulty]);
        }

        $selected = [];
        $selectedIds = [];
        while (count($selected) < $limit) {
            $nextDifficulty = $this->pickNextDifficulty($remainingTargets, $difficultyPools);
            if ($nextDifficulty === null) {
                break;
            }

            /** @var Question|null $question */
            $question = array_shift($difficultyPools[$nextDifficulty]);
            if (!$question instanceof Question) {
                continue;
            }

            $questionId = (int) $question->getKey();
            if (isset($selectedIds[$questionId])) {
                continue;
            }

            $selected[] = $question;
            $selectedIds[$questionId] = true;
            if (($remainingTargets[$nextDifficulty] ?? 0) > 0) {
                $remainingTargets[$nextDifficulty]--;
            }
        }

        return $selected;
    }

    /**
     * @param array<int, array<int, Question>> $candidateByCategory
     * @param array<int, int> $allocation
     * @return array<int, Question>
     */
    private function pickBalancedByCategories(array $candidateByCategory, array $allocation, int $limit): array
    {
        $allCandidates = [];
        foreach ($candidateByCategory as $categoryCandidates) {
            foreach ($categoryCandidates as $candidate) {
                $allCandidates[] = $candidate;
            }
        }

        if ($allCandidates === []) {
            return [];
        }

        $difficultyMap = $this->buildDynamicDifficultyMap($allCandidates);
        $targets = $this->buildDifficultyTargets($limit);
        $remainingTargets = $targets;

        $poolsByCategory = [];
        foreach ($candidateByCategory as $categoryId => $categoryCandidates) {
            $poolsByCategory[$categoryId] = [
                'easy' => [],
                'medium' => [],
                'hard' => [],
            ];

            foreach ($categoryCandidates as $question) {
                $questionId = (int) $question->getKey();
                $difficulty = $difficultyMap[$questionId] ?? 'medium';
                $poolsByCategory[$categoryId][$difficulty][] = $question;
            }

            foreach (array_keys($poolsByCategory[$categoryId]) as $difficulty) {
                shuffle($poolsByCategory[$categoryId][$difficulty]);
            }
        }

        $selected = [];
        $selectedIds = [];
        $pickedPerCategory = array_fill_keys(array_keys($allocation), 0);
        $guard = 0;
        $maxGuard = max(10, $limit * 30);

        while (count($selected) < $limit && $guard < $maxGuard) {
            $guard++;
            $pickedSomething = false;

            foreach ($allocation as $categoryId => $requiredCount) {
                if (($pickedPerCategory[$categoryId] ?? 0) >= $requiredCount) {
                    continue;
                }

                $difficultyOrder = $this->buildDifficultyOrder($remainingTargets);
                $question = $this->takeQuestionFromCategoryPools($poolsByCategory[$categoryId], $difficultyOrder);
                if (!$question instanceof Question) {
                    continue;
                }

                $questionId = (int) $question->getKey();
                if (isset($selectedIds[$questionId])) {
                    continue;
                }

                $selected[] = $question;
                $selectedIds[$questionId] = true;
                $pickedPerCategory[$categoryId] = (int) ($pickedPerCategory[$categoryId] ?? 0) + 1;

                $pickedDifficulty = $difficultyMap[$questionId] ?? 'medium';
                if (($remainingTargets[$pickedDifficulty] ?? 0) > 0) {
                    $remainingTargets[$pickedDifficulty]--;
                }

                $pickedSomething = true;
                if (count($selected) >= $limit) {
                    break 2;
                }
            }

            if (!$pickedSomething) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @param array<string, int> $remainingTargets
     * @param array<string, array<int, Question>> $difficultyPools
     */
    private function pickNextDifficulty(array $remainingTargets, array $difficultyPools): ?string
    {
        $difficultyOrder = $this->buildDifficultyOrder($remainingTargets);
        foreach ($difficultyOrder as $difficulty) {
            if (($difficultyPools[$difficulty] ?? []) !== []) {
                return $difficulty;
            }
        }

        return null;
    }

    /**
     * @param array<string, int> $remainingTargets
     * @return array<int, string>
     */
    private function buildDifficultyOrder(array $remainingTargets): array
    {
        $pairs = [];
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            $pairs[] = [
                'difficulty' => $difficulty,
                'target' => (int) ($remainingTargets[$difficulty] ?? 0),
            ];
        }

        usort($pairs, static function (array $left, array $right): int {
            return $right['target'] <=> $left['target'];
        });

        return array_map(static fn (array $pair): string => (string) $pair['difficulty'], $pairs);
    }

    /**
     * @param array<string, array<int, Question>> $categoryPools
     * @param array<int, string> $difficultyOrder
     */
    private function takeQuestionFromCategoryPools(array &$categoryPools, array $difficultyOrder): ?Question
    {
        foreach ($difficultyOrder as $difficulty) {
            if (($categoryPools[$difficulty] ?? []) === []) {
                continue;
            }

            /** @var Question|null $question */
            $question = array_shift($categoryPools[$difficulty]);
            if ($question instanceof Question) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @param array<int, Question> $questions
     * @return array<int, string>
     */
    private function buildDynamicDifficultyMap(array $questions): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (Question $question): int => (int) $question->getKey(),
            $questions
        )));
        $accuracyMap = $this->buildQuestionAccuracyMap($ids);

        $result = [];
        foreach ($ids as $questionId) {
            $attempts = (int) ($accuracyMap[$questionId]['attempts'] ?? 0);
            $accuracy = (float) ($accuracyMap[$questionId]['accuracy'] ?? 0.0);

            if ($attempts < self::DIFFICULTY_MIN_ATTEMPTS) {
                $result[$questionId] = 'medium';
                continue;
            }

            if ($accuracy > self::DIFFICULTY_EASY_THRESHOLD) {
                $result[$questionId] = 'easy';
            } elseif ($accuracy >= self::DIFFICULTY_MEDIUM_THRESHOLD) {
                $result[$questionId] = 'medium';
            } else {
                $result[$questionId] = 'hard';
            }
        }

        return $result;
    }

    /**
     * @param array<int> $questionIds
     * @return array<int, array{attempts:int, correct:int, accuracy:float}>
     */
    private function buildQuestionAccuracyMap(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $rows = UserAnswerHistory::query()
            ->selectRaw('question_id, COUNT(*) as attempts, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->whereNotNull('question_id')
            ->whereIn('question_id', $questionIds)
            ->groupBy('question_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $questionId = (int) ($row->question_id ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $attempts = (int) ($row->attempts ?? 0);
            $correct = (int) ($row->correct_answers ?? 0);
            $accuracy = $attempts > 0 ? round(($correct / $attempts) * 100, 2) : 0.0;

            $result[$questionId] = [
                'attempts' => $attempts,
                'correct' => $correct,
                'accuracy' => $accuracy,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function buildDifficultyTargets(int $limit): array
    {
        $easy = (int) round($limit * 0.30);
        $medium = (int) round($limit * 0.50);
        $hard = max(0, $limit - $easy - $medium);

        return [
            'easy' => $easy,
            'medium' => $medium,
            'hard' => $hard,
        ];
    }

    private function isValidQuestion(Question $question): bool
    {
        $questionId = (int) $question->getKey();
        $answers = $question->answers;

        if ($answers->count() !== 4) {
            $this->logger->warning('Вопрос пропущен: требуется 4 варианта ответа.', [
                'question_id' => $questionId,
                'answers_count' => $answers->count(),
            ]);
            return false;
        }

        $correctCount = $answers->where('is_correct', true)->count();
        if ($correctCount !== 1) {
            $this->logger->warning('Вопрос пропущен: должен быть ровно один правильный ответ.', [
                'question_id' => $questionId,
                'correct_count' => $correctCount,
            ]);
            return false;
        }

        $invalidText = $answers->first(function ($answer) {
            return trim((string) $answer->answer_text) === '';
        });

        if ($invalidText !== null) {
            $this->logger->warning('Вопрос пропущен: пустой текст варианта ответа.', [
                'question_id' => $questionId,
            ]);
            return false;
        }

        return true;
    }
}
