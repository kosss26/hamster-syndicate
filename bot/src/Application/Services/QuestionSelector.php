<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Monolog\Logger;
use QuizBot\Domain\Model\Category;
use QuizBot\Domain\Model\Question;

class QuestionSelector
{
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
