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
            'settings' => [],
        ]);

        $user->profile()->save($profile);
        $user->setRelation('profile', $profile);

        return $user;
    }
}

