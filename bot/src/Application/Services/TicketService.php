<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use Monolog\Logger;
use QuizBot\Domain\Model\User;

class TicketService
{
    public const TICKET_CAP = 50;
    public const REGEN_CAP = 10;
    public const REGEN_INTERVAL_SECONDS = 300; // 1 билет в 5 минут

    private Logger $logger;
    private UserService $userService;

    public function __construct(Logger $logger, UserService $userService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
    }

    /**
     * Синхронизирует реген билетов и возвращает состояние.
     *
     * @return array{tickets:int,cap:int,regen_cap:int,seconds_to_next:int,next_ticket_at:?string}
     */
    public function sync(User $user): array
    {
        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;

        $tickets = max(0, min(self::TICKET_CAP, (int) ($profile->lives ?? 0)));
        $settings = is_array($profile->settings) ? $profile->settings : [];
        $now = Carbon::now();
        $lastRegen = $this->parseRegenAnchor($settings['tickets_last_regen_at'] ?? null, $now);

        if ($tickets >= self::REGEN_CAP) {
            // При запасе >= regen_cap накопление времени не идёт.
            $lastRegen = $now;
        } else {
            $elapsed = max(0, $lastRegen->diffInSeconds($now, false));
            $regenSteps = (int) floor($elapsed / self::REGEN_INTERVAL_SECONDS);
            if ($regenSteps > 0) {
                $gain = min($regenSteps, self::REGEN_CAP - $tickets);
                if ($gain > 0) {
                    $tickets += $gain;
                    $lastRegen = $lastRegen->copy()->addSeconds($gain * self::REGEN_INTERVAL_SECONDS);
                }

                // Если уткнулись в порог автогенерации, обнуляем "накопление".
                if ($tickets >= self::REGEN_CAP) {
                    $lastRegen = $now;
                }
            }
        }

        $profile->lives = max(0, min(self::TICKET_CAP, $tickets));
        $settings['tickets_last_regen_at'] = $lastRegen->toIso8601String();
        $profile->settings = $settings;
        $profile->save();

        $secondsToNext = 0;
        $nextTicketAt = null;
        if ($tickets < self::REGEN_CAP) {
            $elapsedSinceAnchor = max(0, $lastRegen->diffInSeconds($now, false));
            $secondsToNext = max(0, self::REGEN_INTERVAL_SECONDS - ($elapsedSinceAnchor % self::REGEN_INTERVAL_SECONDS));
            $nextTicketAt = $now->copy()->addSeconds($secondsToNext)->toIso8601String();
        }

        return [
            'tickets' => (int) $profile->lives,
            'cap' => self::TICKET_CAP,
            'regen_cap' => self::REGEN_CAP,
            'seconds_to_next' => $secondsToNext,
            'next_ticket_at' => $nextTicketAt,
        ];
    }

    /**
     * @return array{success:bool,tickets_left:int,error?:string}
     */
    public function spend(User $user, int $amount = 1): array
    {
        $amount = max(1, $amount);
        $state = $this->sync($user);

        if ($state['tickets'] < $amount) {
            return [
                'success' => false,
                'error' => 'Недостаточно билетов',
                'tickets_left' => $state['tickets'],
            ];
        }

        $user = $this->userService->ensureProfile($user);
        $profile = $user->profile;
        $before = (int) ($profile->lives ?? 0);
        $after = max(0, $before - $amount);
        $profile->lives = $after;

        $settings = is_array($profile->settings) ? $profile->settings : [];
        // После траты с уровня >= regen_cap таймер регена стартует сейчас.
        if ($before >= self::REGEN_CAP && $after < self::REGEN_CAP) {
            $settings['tickets_last_regen_at'] = Carbon::now()->toIso8601String();
        }
        $profile->settings = $settings;
        $profile->save();

        return [
            'success' => true,
            'tickets_left' => $after,
        ];
    }

    /**
     * @param mixed $value
     */
    private function parseRegenAnchor($value, Carbon $fallback): Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return $fallback->copy();
        }
    }
}
