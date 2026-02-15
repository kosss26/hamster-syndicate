<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\User;
use QuizBot\Domain\Model\UserProfile;
use Monolog\Logger;
use Illuminate\Support\Carbon;

class UserService
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $from
     */
    public function syncFromTelegram(array $from): User
    {
        if (!isset($from['id'])) {
            throw new \InvalidArgumentException('Поле id отсутствует в данных пользователя Telegram.');
        }

        $telegramId = (int) $from['id'];

        $user = User::query()->firstOrNew(['telegram_id' => $telegramId]);

        $user->username = $from['username'] ?? $user->username;
        $user->first_name = $from['first_name'] ?? $user->first_name;
        $user->last_name = $from['last_name'] ?? $user->last_name;
        $user->language_code = $from['language_code'] ?? $user->language_code;
        $user->photo_url = $from['photo_url'] ?? $user->photo_url;

        if (!$user->exists) {
            $user->onboarded_at = Carbon::now();
        }

        $isNewUser = !$user->exists;
        $user->save();
        $isNewUser = $user->wasRecentlyCreated || $isNewUser;

        $user->loadMissing('profile');

        $profileCreated = false;

        if ($user->profile === null) {
            $profile = new UserProfile([
                'level' => 1,
                'experience' => 0,
                'rating' => 0,
                'coins' => 300,
                'lives' => 3,
                'streak_days' => 0,
                'duel_wins' => 0,
                'duel_losses' => 0,
                'duel_draws' => 0,
                'story_progress_score' => 0,
                'true_false_record' => 0,
                'gems' => 10,
                'hints' => 1,
                'settings' => [],
            ]);

            $user->profile()->save($profile);
            $user->setRelation('profile', $profile);
            $profileCreated = true;
        }

        if ($isNewUser) {
            $this->logger->info(sprintf('Зарегистрирован новый пользователь Telegram %d', $telegramId));
        } elseif ($profileCreated) {
            $this->logger->info(sprintf('Профиль создан для пользователя Telegram %d', $telegramId));
        } else {
            $this->logger->debug(sprintf('Пользователь Telegram %d синхронизирован', $telegramId));
        }

        return $user;
    }

    public function findByTelegramId(int $telegramId): ?User
    {
        return User::query()->where('telegram_id', $telegramId)->first();
    }

    public function findByUsername(string $username): ?User
    {
        $normalized = strtolower(ltrim($username, '@'));

        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(username) = ?', [$normalized])
            ->first();
    }

    public function ensureProfile(User $user): User
    {
        $user->loadMissing('profile');

        if ($user->profile !== null) {
            return $user;
        }

        $profile = new UserProfile([
            'level' => 1,
            'experience' => 0,
            'rating' => 0,
            'coins' => 300,
            'lives' => 3,
            'streak_days' => 0,
            'duel_wins' => 0,
            'duel_losses' => 0,
            'duel_draws' => 0,
            'story_progress_score' => 0,
            'true_false_record' => 0,
            'gems' => 10,
            'hints' => 1,
            'settings' => [],
        ]);

        $user->profile()->save($profile);
        $user->setRelation('profile', $profile);

        return $user;
    }

    /**
     * Выдать опыт пользователю и пересчитать уровень.
     *
     * @return array{added:int,old_level:int,new_level:int,leveled_up:bool,total_experience:int,progress:array<string,int>}
     */
    public function grantExperience(User $user, int $amount): array
    {
        $user = $this->ensureProfile($user);
        $profile = $user->profile;

        if (!$profile instanceof UserProfile) {
            throw new \RuntimeException('Профиль пользователя не найден.');
        }

        $gain = max(0, $amount);
        $oldLevel = (int) $profile->level;
        $profile->experience = (int) $profile->experience + $gain;
        $newLevel = $this->calculateLevelByExperience((int) $profile->experience);
        $profile->level = $newLevel;
        $profile->save();

        return [
            'added' => $gain,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'leveled_up' => $newLevel > $oldLevel,
            'total_experience' => (int) $profile->experience,
            'progress' => $this->getExperienceProgress($profile),
        ];
    }

    /**
     * @return array{level:int,current_experience:int,current_level_start:int,next_level_experience:int,exp_into_level:int,exp_to_next_level:int}
     */
    public function getExperienceProgress(UserProfile $profile): array
    {
        $level = max(1, (int) $profile->level);
        $currentExp = (int) $profile->experience;
        $currentLevelStart = $this->getTotalExperienceForLevel($level);
        $nextLevelExp = $this->getTotalExperienceForLevel($level + 1);
        $expIntoLevel = max(0, $currentExp - $currentLevelStart);
        $expToNext = max(0, $nextLevelExp - $currentExp);

        return [
            'level' => $level,
            'current_experience' => $currentExp,
            'current_level_start' => $currentLevelStart,
            'next_level_experience' => $nextLevelExp,
            'exp_into_level' => $expIntoLevel,
            'exp_to_next_level' => $expToNext,
        ];
    }

    private function calculateLevelByExperience(int $experience): int
    {
        $exp = max(0, $experience);
        $level = 1;

        while ($level < 500 && $exp >= $this->getTotalExperienceForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    private function getTotalExperienceForLevel(int $level): int
    {
        $targetLevel = max(1, $level);
        $total = 0;

        for ($i = 1; $i < $targetLevel; $i++) {
            $total += $this->getExperienceRequiredForNextLevel($i);
        }

        return $total;
    }

    private function getExperienceRequiredForNextLevel(int $level): int
    {
        $currentLevel = max(1, $level);
        return 100 + (($currentLevel - 1) * 25);
    }

    /**
     * Получить топ игроков по рейтингу
     * 
     * @param int $limit
     * @return array<int, array{position: int, user: User, rating: int}>
     */
    public function getTopPlayersByRating(int $limit = 10): array
    {
        try {
            $profiles = UserProfile::query()
                ->whereNotNull('rating')
                ->where('rating', '>', 0)
                ->orderByDesc('rating')
                ->limit($limit)
                ->with('user')
                ->get();

            $result = [];
            $position = 1;

            foreach ($profiles as $profile) {
                $user = $profile->user;
                
                if ($user === null) {
                    continue;
                }

                $rating = (int) ($profile->rating ?? 0);
                $user->setRelation('profile', $profile);
                
                $result[] = [
                    'position' => $position++,
                    'user' => $user,
                    'rating' => $rating,
                ];
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при получении топ игроков', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            throw $exception;
        }
    }

    /**
     * Получить позицию пользователя в глобальном рейтинге
     * 
     * @param User $user
     * @return int|null Позиция в рейтинге или null, если пользователь не найден
     */
    public function getUserRatingPosition(User $user): ?int
    {
        try {
            $user = $this->ensureProfile($user);
            $profile = $user->profile;

            if ($profile === null) {
                return null;
            }

            $rating = (int) $profile->rating;

            // Подсчитываем количество пользователей с рейтингом выше текущего
            $usersAbove = UserProfile::query()
                ->where('rating', '>', $rating)
                ->count();

            return $usersAbove + 1;
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при получении позиции пользователя в рейтинге', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Получает топ игроков по лучшей серии в режиме "Правда или ложь"
     *
     * @return array<int, array{position: int, user: User, record: int}>
     */
    public function getTopPlayersByTrueFalseRecord(int $limit = 10): array
    {
        try {
            $profiles = UserProfile::query()
                ->whereNotNull('true_false_record')
                ->where('true_false_record', '>', 0)
                ->orderByDesc('true_false_record')
                ->limit($limit)
                ->with('user')
                ->get();

            $result = [];
            $position = 1;

            foreach ($profiles as $profile) {
                $user = $profile->user;
                
                if ($user === null) {
                    continue;
                }

                $record = (int) ($profile->true_false_record ?? 0);
                $user->setRelation('profile', $profile);
                
                $result[] = [
                    'position' => $position++,
                    'user' => $user,
                    'record' => $record,
                ];
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при получении топа игроков по Правда/Ложь', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Получает позицию пользователя в рейтинге "Правда или ложь"
     */
    public function getUserTrueFalsePosition(User $user): ?int
    {
        try {
            $user = $this->ensureProfile($user);
            $profile = $user->profile;

            if ($profile === null) {
                return null;
            }

            $record = (int) ($profile->true_false_record ?? 0);

            if ($record === 0) {
                return null;
            }

            // Подсчитываем количество пользователей с рекордом выше текущего
            $usersAbove = UserProfile::query()
                ->where('true_false_record', '>', $record)
                ->count();

            return $usersAbove + 1;
        } catch (\Throwable $exception) {
            $this->logger->error('Ошибка при получении позиции в рейтинге Правда/Ложь', [
                'error' => $exception->getMessage(),
                'user_id' => $user->getKey(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return null;
        }
    }
}
