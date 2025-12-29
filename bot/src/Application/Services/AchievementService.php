<?php

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\Achievement;
use QuizBot\Domain\Model\UserAchievement;
use QuizBot\Domain\Model\UserProfile;

class AchievementService
{
    /**
     * Получить все достижения
     */
    public function getAll(bool $includeSecret = false): array
    {
        $query = Achievement::query()->orderBy('sort_order')->orderBy('id');
        
        if (!$includeSecret) {
            $query->where('is_secret', false);
        }
        
        return $query->get()->toArray();
    }

    /**
     * Получить достижения по категории
     */
    public function getByCategory(string $category): array
    {
        return Achievement::where('category', $category)
            ->where('is_secret', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    /**
     * Получить достижения игрока с прогрессом
     */
    public function getUserAchievements(int $userId, bool $includeSecret = false): array
    {
        $achievements = $this->getAll($includeSecret);
        
        // Получаем прогресс пользователя
        $userAchievements = UserAchievement::where('user_id', $userId)
            ->get()
            ->keyBy('achievement_id');
        
        return array_map(function ($achievement) use ($userAchievements) {
            $userAch = $userAchievements->get($achievement['id']);
            
            return array_merge($achievement, [
                'current_value' => $userAch ? $userAch->current_value : 0,
                'is_completed' => $userAch ? $userAch->is_completed : false,
                'completed_at' => $userAch ? $userAch->completed_at?->format('Y-m-d H:i:s') : null,
                'is_showcased' => $userAch ? $userAch->is_showcased : false,
                'progress' => $userAch ? $userAch->progress : 0,
            ]);
        }, $achievements);
    }

    /**
     * Получить витрину достижений (избранные)
     */
    public function getShowcased(int $userId): array
    {
        $showcased = UserAchievement::where('user_id', $userId)
            ->where('is_showcased', true)
            ->where('is_completed', true)
            ->with('achievement')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();
        
        return $showcased->map(function ($userAch) {
            $achievement = $userAch->achievement;
            return [
                'id' => $achievement->id,
                'key' => $achievement->key,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'rarity' => $achievement->rarity,
                'category' => $achievement->category,
                'completed_at' => $userAch->completed_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    /**
     * Настроить витрину достижений
     */
    public function setShowcased(int $userId, array $achievementIds): bool
    {
        // Сбрасываем все текущие showcased
        UserAchievement::where('user_id', $userId)
            ->update(['is_showcased' => false]);
        
        // Ограничиваем до 5 достижений
        $achievementIds = array_slice($achievementIds, 0, 5);
        
        // Устанавливаем новые showcased (только завершённые)
        if (!empty($achievementIds)) {
            UserAchievement::where('user_id', $userId)
                ->whereIn('achievement_id', $achievementIds)
                ->where('is_completed', true)
                ->update(['is_showcased' => true]);
        }
        
        return true;
    }

    /**
     * Разблокировать достижение
     */
    public function unlockAchievement(int $userId, string $achievementKey): ?array
    {
        $achievement = Achievement::where('key', $achievementKey)->first();
        if (!$achievement) {
            return null;
        }
        
        $userAchievement = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->first();
        
        if (!$userAchievement) {
            $userAchievement = new UserAchievement([
                'user_id' => $userId,
                'achievement_id' => $achievement->id,
                'current_value' => 0,
            ]);
        }
        
        // Если уже завершено, не разблокируем повторно
        if ($userAchievement->is_completed) {
            return null;
        }
        
        // Разблокируем
        $userAchievement->current_value = $achievement->condition_value;
        $userAchievement->is_completed = true;
        $userAchievement->completed_at = now();
        $userAchievement->updated_at = now();
        $userAchievement->save();
        
        // Выдаём награды
        $profile = UserProfile::where('user_id', $userId)->first();
        if ($profile) {
            if ($achievement->reward_coins > 0) {
                $profile->coins += $achievement->reward_coins;
            }
            if ($achievement->reward_gems > 0) {
                $profile->gems += $achievement->reward_gems;
            }
            $profile->save();
        }
        
        return [
            'achievement' => $achievement->toArray(),
            'rewards' => [
                'coins' => $achievement->reward_coins,
                'gems' => $achievement->reward_gems,
            ],
        ];
    }

    /**
     * Обновить прогресс достижения
     */
    public function updateProgress(int $userId, string $achievementKey, int $newValue): ?array
    {
        $achievement = Achievement::where('key', $achievementKey)->first();
        if (!$achievement) {
            return null;
        }
        
        $userAchievement = UserAchievement::firstOrCreate(
            [
                'user_id' => $userId,
                'achievement_id' => $achievement->id,
            ],
            [
                'current_value' => 0,
                'is_completed' => false,
            ]
        );
        
        // Если уже завершено, не обновляем
        if ($userAchievement->is_completed) {
            return null;
        }
        
        // Обновляем значение
        $userAchievement->current_value = $newValue;
        $userAchievement->updated_at = now();
        
        // Проверяем, достигнуто ли условие
        if ($newValue >= $achievement->condition_value) {
            return $this->unlockAchievement($userId, $achievementKey);
        }
        
        $userAchievement->save();
        return null;
    }

    /**
     * Получить статистику достижений пользователя
     */
    public function getUserStats(int $userId): array
    {
        $total = Achievement::where('is_secret', false)->count();
        $completed = UserAchievement::where('user_id', $userId)
            ->where('is_completed', true)
            ->count();
        
        $byRarity = UserAchievement::where('user_id', $userId)
            ->where('is_completed', true)
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->selectRaw('achievements.rarity, COUNT(*) as count')
            ->groupBy('achievements.rarity')
            ->get()
            ->pluck('count', 'rarity')
            ->toArray();
        
        return [
            'total' => $total,
            'completed' => $completed,
            'completion_percent' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'by_rarity' => [
                'common' => $byRarity['common'] ?? 0,
                'rare' => $byRarity['rare'] ?? 0,
                'epic' => $byRarity['epic'] ?? 0,
                'legendary' => $byRarity['legendary'] ?? 0,
            ],
        ];
    }
}

