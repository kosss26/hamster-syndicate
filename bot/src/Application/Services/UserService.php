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
                'coins' => 0,
                'lives' => 3,
                'streak_days' => 0,
                'duel_wins' => 0,
                'duel_losses' => 0,
                'duel_draws' => 0,
                'story_progress_score' => 0,
                'true_false_record' => 0,
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
            'coins' => 0,
            'lives' => 3,
            'streak_days' => 0,
            'duel_wins' => 0,
            'duel_losses' => 0,
            'duel_draws' => 0,
            'story_progress_score' => 0,
            'true_false_record' => 0,
            'settings' => [],
        ]);

        $user->profile()->save($profile);
        $user->setRelation('profile', $profile);

        return $user;
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
}

