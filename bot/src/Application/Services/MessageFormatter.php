<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

/**
 * Сервис для форматирования сообщений с визуальными элементами
 */
class MessageFormatter
{
    /**
     * Создаёт прогресс-бар
     *
     * @param float $current Текущее значение
     * @param float $max Максимальное значение
     * @param int $length Длина прогресс-бара
     * @param string $filled Символ для заполненной части
     * @param string $empty Символ для пустой части
     */
    public function progressBar(float $current, float $max, int $length = 10, string $filled = '█', string $empty = '░'): string
    {
        if ($max <= 0) {
            return str_repeat($empty, $length);
        }

        $percentage = min(100, max(0, ($current / $max) * 100));
        $filledCount = (int) round(($percentage / 100) * $length);
        $emptyCount = $length - $filledCount;

        return str_repeat($filled, $filledCount) . str_repeat($empty, $emptyCount) . sprintf(' %.0f%%', $percentage);
    }

    /**
     * Форматирует опыт до следующего уровня
     */
    public function formatExperience(int $current, int $nextLevel): string
    {
        $needed = $nextLevel - $current;
        $bar = $this->progressBar($current, $nextLevel, 12);

        return sprintf("🌟 Опыт: %d / %d\n%s", $current, $nextLevel, $bar);
    }

    /**
     * Форматирует здоровье в сюжете
     */
    public function formatHealth(int $lives): string
    {
        $hearts = str_repeat('❤️', max(0, $lives));
        $empty = str_repeat('🤍', max(0, 3 - $lives));

        return sprintf("Жизни: %s%s (%d/3)", $hearts, $empty, $lives);
    }

    /**
     * Создаёт полоску здоровья
     */
    public function healthBar(int $current, int $total, string $filledChar = '❤️', string $emptyChar = '🤍'): string
    {
        return str_repeat($filledChar, $current) . str_repeat($emptyChar, $total - $current);
    }

    /**
     * Форматирует прогресс дуэли
     * 
     * @param int $current Текущий раунд
     * @param int $total Всего раундов
     * @param \Illuminate\Support\Collection|null $allRounds Все раунды дуэли для определения правильности ответов
     * @param int|null $userId ID пользователя для которого показывается прогресс (для определения правильности ответов)
     */
    public function formatDuelProgress(int $current, int $total, $allRounds = null, ?int $userId = null): string
    {
        $progress = [];
        
        for ($i = 1; $i <= $total; $i++) {
            if ($i < $current) {
                // Прошлые раунды - показываем результат
                if ($allRounds !== null) {
                    $pastRound = $allRounds->firstWhere('round_number', $i);
                    if ($pastRound !== null && $userId !== null) {
                        // Определяем правильность ответа для конкретного пользователя из payload
                        $pastRound->loadMissing('duel');
                        $isInitiator = $pastRound->duel->initiator_user_id === $userId;
                        $payload = $isInitiator ? ($pastRound->initiator_payload ?? []) : ($pastRound->opponent_payload ?? []);
                        
                        // Проверяем, есть ли информация об ответе
                        if (isset($payload['completed']) && $payload['completed'] === true) {
                            $isCorrect = ($payload['is_correct'] ?? false) === true;
                            $progress[] = $isCorrect ? '🟢' : '🔴';
                        } else {
                            // Если ответ ещё не получен, показываем белый круг
                            $progress[] = '⚪';
                        }
                    } else {
                        // Если нет информации, показываем белый круг
                        $progress[] = '⚪';
                    }
                } else {
                    $progress[] = '⚪';
                }
            } elseif ($i === $current) {
                // Текущий раунд - мечи
                $progress[] = '⚔️';
            } else {
                // Будущие раунды - белый круг
                $progress[] = '⚪';
            }
        }
        
        $progressBar = implode('', $progress);
        
        // Возвращаем только прогресс-бар, без заголовка (заголовок добавляется отдельно)
        return $progressBar;
    }

    /**
     * Создаёт красивую рамку для текста
     */
    public function box(string $title, string $content, string $icon = '📦'): string
    {
        $lines = [
            sprintf('%s <b>%s</b>', $icon, $title),
            '━━━━━━━━━━━━━━━━━━',
            $content,
        ];

        return implode("\n", $lines);
    }

    /**
     * Форматирует правильный ответ с анимацией
     */
    public function correctAnswer(string $message = 'Верно!'): string
    {
        return sprintf("🎯 %s\n✨ Отлично!\n🎉 +1 очко", $message);
    }

    /**
     * Форматирует правильный ответ с анимацией (для истории)
     */
    public function animatedCorrectAnswer(string $pointsText): string
    {
        return sprintf('🎯 Верно! ✨ Отлично! 🎉 %s', $pointsText);
    }

    /**
     * Форматирует неправильный ответ с анимацией
     */
    public function incorrectAnswer(string $correctAnswer): string
    {
        return sprintf("❌ Неверно\n💥 Правильный ответ: <b>%s</b>\n😢 Попробуй ещё раз!", $correctAnswer);
    }

    /**
     * Форматирует неправильный ответ с анимацией (для истории)
     */
    public function animatedIncorrectAnswer(string $correctAnswerText): string
    {
        return sprintf('❌ Неверно 💥 Правильный ответ: %s 😢 Попробуй ещё раз!', $correctAnswerText);
    }

    /**
     * Создаёт красивую рамку для вопроса
     */
    public function questionBox(string $questionText): string
    {
        $lines = explode("\n", $questionText);
        $maxLength = 0;
        foreach ($lines as $line) {
            $lineLength = mb_strlen($line);
            if ($lineLength > $maxLength) {
                $maxLength = $lineLength;
            }
        }

        $box = [];
        $box[] = '```';
        $box[] = '┌' . str_repeat('─', $maxLength + 2) . '┐';
        foreach ($lines as $line) {
            $box[] = '│ ' . str_pad($line, $maxLength, ' ') . ' │';
        }
        $box[] = '└' . str_repeat('─', $maxLength + 2) . '┘';
        $box[] = '```';

        return implode("\n", $box);
    }

    /**
     * Создаёт разделитель
     */
    public function separator(string $char = '━', int $length = 20): string
    {
        return str_repeat($char, $length);
    }

    /**
     * Форматирует число с эмодзи
     */
    public function formatNumber(int $number, string $emoji): string
    {
        return sprintf('%s %s', $emoji, number_format($number, 0, ',', ' '));
    }

    /**
     * Создаёт красивый заголовок
     */
    public function header(string $text, string $emoji = '⭐'): string
    {
        return sprintf('%s <b>%s</b> %s', $emoji, $text, $emoji);
    }
}

