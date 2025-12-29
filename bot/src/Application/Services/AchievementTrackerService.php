<?php

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\Achievement;
use QuizBot\Domain\Model\AchievementStat;
use QuizBot\Domain\Model\UserProfile;

class AchievementTrackerService
{
    private AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    /**
     * Установить статистику
     */
    public function setStat(int $userId, string $statKey, int $value): void
    {
        AchievementStat::updateOrCreate(
            ['user_id' => $userId, 'stat_key' => $statKey],
            ['stat_value' => $value, 'last_updated' => now()]
        );
    }

    /**
     * Увеличить счётчик
     */
    public function incrementStat(int $userId, string $statKey, int $amount = 1): int
    {
        $stat = AchievementStat::firstOrCreate(
            ['user_id' => $userId, 'stat_key' => $statKey],
            ['stat_value' => 0]
        );
        
        $stat->stat_value += $amount;
        $stat->last_updated = now();
        $stat->save();
        
        return $stat->stat_value;
    }

    /**
     * Получить статистику
     */
    public function getStat(int $userId, string $statKey): int
    {
        $stat = AchievementStat::where('user_id', $userId)
            ->where('stat_key', $statKey)
            ->first();
        
        return $stat ? $stat->stat_value : 0;
    }

    /**
     * Проверить и разблокировать достижения на основе контекста
     */
    public function checkAndUnlock(int $userId, array $context = []): array
    {
        $unlockedAchievements = [];
        
        // Получаем все достижения
        $achievements = Achievement::all();
        
        foreach ($achievements as $achievement) {
            // Пропускаем уже разблокированные
            $userAch = \QuizBot\Domain\Model\UserAchievement::where('user_id', $userId)
                ->where('achievement_id', $achievement->id)
                ->first();
            
            if ($userAch && $userAch->is_completed) {
                continue;
            }
            
            // Проверяем условие
            $shouldUnlock = $this->checkCondition($userId, $achievement, $context);
            
            if ($shouldUnlock) {
                $result = $this->achievementService->unlockAchievement($userId, $achievement->key);
                if ($result) {
                    $unlockedAchievements[] = $result;
                }
            } else {
                // Обновляем прогресс, если есть
                $currentValue = $this->getCurrentValue($userId, $achievement, $context);
                if ($currentValue !== null) {
                    $this->achievementService->updateProgress($userId, $achievement->key, $currentValue);
                }
            }
        }
        
        return $unlockedAchievements;
    }

    /**
     * Проверить условие достижения
     */
    private function checkCondition(int $userId, Achievement $achievement, array $context): bool
    {
        switch ($achievement->condition_type) {
            case 'counter':
                return $this->checkCounterCondition($userId, $achievement);
                
            case 'streak':
                return $this->checkStreakCondition($userId, $achievement);
                
            case 'special':
                return $this->checkSpecialCondition($userId, $achievement, $context);
                
            default:
                return false;
        }
    }

    /**
     * Проверить условие счётчика
     */
    private function checkCounterCondition(int $userId, Achievement $achievement): bool
    {
        $statKey = $this->getStatKeyForAchievement($achievement);
        if (!$statKey) {
            return false;
        }
        
        $currentValue = $this->getStat($userId, $statKey);
        return $currentValue >= $achievement->condition_value;
    }

    /**
     * Проверить условие серии
     */
    private function checkStreakCondition(int $userId, Achievement $achievement): bool
    {
        if (str_contains($achievement->key, 'win_streak')) {
            $currentStreak = $this->getStat($userId, 'current_win_streak');
            return $currentStreak >= $achievement->condition_value;
        }
        
        return false;
    }

    /**
     * Проверить специальные условия
     */
    private function checkSpecialCondition(int $userId, Achievement $achievement, array $context): bool
    {
        switch ($achievement->key) {
            // Идеальная дуэль (10/10)
            case 'perfect_duel':
                return ($context['context'] ?? '') === 'duel_complete' 
                    && ($context['score'] ?? 0) === 10
                    && ($context['total_questions'] ?? 0) === 10;
            
            // Камбэк после 0:5
            case 'comeback_king':
                return ($context['context'] ?? '') === 'duel_win'
                    && ($context['was_losing_badly'] ?? false) === true;
            
            // Победа над игроком с рейтингом на 500+ выше
            case 'underdog':
                return ($context['context'] ?? '') === 'duel_win'
                    && ($context['rating_difference'] ?? 0) >= 500;
            
            // Реванш
            case 'rematch_winner':
                return ($context['context'] ?? '') === 'duel_win'
                    && ($context['is_rematch'] ?? false) === true;
            
            // Ответ за 3 секунды
            case 'speed_answer':
                return ($context['context'] ?? '') === 'quiz_answer'
                    && ($context['answer_time_ms'] ?? 9999) < 3000
                    && ($context['is_correct'] ?? false) === true;
            
            // Все категории
            case 'all_categories':
                $categories = \QuizBot\Domain\Model\QuizCategory::pluck('id')->toArray();
                foreach ($categories as $categoryId) {
                    if ($this->getStat($userId, "category_{$categoryId}") === 0) {
                        return false;
                    }
                }
                return true;
            
            // Полуночник (играет в 3:00)
            case 'secret_night_owl':
                $hour = (int)date('G');
                return $hour === 3 && ($context['context'] ?? '') === 'game_start';
            
            // Счастливая семёрка
            case 'secret_lucky_7':
                $profile = UserProfile::where('user_id', $userId)->first();
                return $profile && $profile->coins === 777;
            
            default:
                return false;
        }
    }

    /**
     * Получить текущее значение для достижения
     */
    private function getCurrentValue(int $userId, Achievement $achievement, array $context): ?int
    {
        $statKey = $this->getStatKeyForAchievement($achievement);
        if (!$statKey) {
            return null;
        }
        
        return $this->getStat($userId, $statKey);
    }

    /**
     * Получить ключ статистики для достижения
     */
    private function getStatKeyForAchievement(Achievement $achievement): ?string
    {
        // Мапинг достижений на ключи статистики
        $mapping = [
            'first_duel' => 'total_duels',
            'duel_veteran' => 'total_duels',
            'duel_master' => 'total_duels',
            'first_duel_win' => 'duel_wins',
            'duel_wins_50' => 'duel_wins',
            'duel_wins_100' => 'duel_wins',
            'duel_wins_500' => 'duel_wins',
            'never_give_up' => 'duel_losses',
            
            'first_answer' => 'total_answers',
            'answers_100' => 'total_answers',
            'answers_1000' => 'total_answers',
            'answers_5000' => 'total_answers',
            'correct_100' => 'correct_answers',
            'correct_1000' => 'correct_answers',
            
            'speed_answers_50' => 'speed_answers_under_3s',
            'hard_questions_50' => 'hard_questions_correct',
            'true_or_false_master' => 'true_false_correct',
            
            'first_purchase' => 'shop_purchases',
            'shopaholic' => 'shop_purchases',
            'wheel_spin_10' => 'wheel_spins',
            'lootbox_open_10' => 'lootbox_openings',
        ];
        
        return $mapping[$achievement->key] ?? null;
    }
}

