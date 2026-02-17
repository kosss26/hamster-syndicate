<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\User;

trait DuelRematchTrait
{
    public function createRematchInvite(User $initiator, User $target, ?Duel $sourceDuel = null): Duel
    {
        if ((int) $initiator->getKey() === (int) $target->getKey()) {
            throw new \RuntimeException('Нельзя отправить реванш самому себе.');
        }

        return $this->createDuel($initiator, null, null, [
            'rematch_invite' => true,
            'target_user_id' => (int) $target->getKey(),
            'rematch_started_at' => Carbon::now()->toIso8601String(),
            'rematch_expires_at' => Carbon::now()->addSeconds(self::REMATCH_INVITE_TTL_SECONDS)->toIso8601String(),
            'rematch_source_duel_id' => $sourceDuel ? (int) $sourceDuel->getKey() : null,
            'reward_factor' => self::REMATCH_REWARD_COEFFICIENT,
        ]);
    }

    public function isRematchInvite(Duel $duel): bool
    {
        $settings = $duel->settings ?? [];
        return ($settings['rematch_invite'] ?? false) === true;
    }

    public function getIncomingRematchInvite(User $target): ?Duel
    {
        $duel = Duel::query()
            ->where('status', 'waiting')
            ->where('initiator_user_id', '!=', (int) $target->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->first(function (Duel $duel) use ($target): bool {
                if (!$this->isRematchInvite($duel) || $duel->opponent_user_id !== null) {
                    return false;
                }

                $settings = $duel->settings ?? [];
                return (int) ($settings['target_user_id'] ?? 0) === (int) $target->getKey();
            });

        if (!$duel) {
            return null;
        }

        if ($this->expireRematchIfNeeded($duel)) {
            return null;
        }

        return $duel->refresh();
    }

    public function acceptRematchInvite(Duel $duel, User $target): Duel
    {
        if (!$this->isRematchInvite($duel)) {
            throw new \RuntimeException('Это не приглашение на реванш.');
        }

        if ($duel->status !== 'waiting' || $duel->opponent_user_id !== null) {
            throw new \RuntimeException('Приглашение уже неактуально.');
        }

        if ($this->expireRematchIfNeeded($duel)) {
            throw new \RuntimeException('Приглашение на реванш истекло.');
        }

        $settings = $duel->settings ?? [];
        $expectedTarget = (int) ($settings['target_user_id'] ?? 0);
        if ($expectedTarget <= 0 || $expectedTarget !== (int) $target->getKey()) {
            throw new \RuntimeException('Этот реванш предназначен другому игроку.');
        }

        return $this->acceptDuel($duel, $target);
    }

    public function declineRematchInvite(Duel $duel, User $target): Duel
    {
        if (!$this->isRematchInvite($duel)) {
            throw new \RuntimeException('Это не приглашение на реванш.');
        }

        $settings = $duel->settings ?? [];
        $expectedTarget = (int) ($settings['target_user_id'] ?? 0);
        if ($expectedTarget <= 0 || $expectedTarget !== (int) $target->getKey()) {
            throw new \RuntimeException('Этот реванш предназначен другому игроку.');
        }

        if ($duel->status !== 'waiting') {
            throw new \RuntimeException('Нельзя отклонить неактуальное приглашение.');
        }

        $settings['cancel_reason'] = 'rematch_declined';
        $settings['cancelled_by_user_id'] = (int) $target->getKey();
        $duel->settings = $settings;
        $duel->status = 'cancelled';
        $duel->finished_at = Carbon::now();
        $duel->save();

        return $duel->refresh();
    }

    public function cancelRematchInvite(Duel $duel, User $initiator): Duel
    {
        if (!$this->isRematchInvite($duel)) {
            throw new \RuntimeException('Это не приглашение на реванш.');
        }

        if ((int) $duel->initiator_user_id !== (int) $initiator->getKey()) {
            throw new \RuntimeException('Можно отменить только своё приглашение.');
        }

        if ($duel->status !== 'waiting') {
            throw new \RuntimeException('Приглашение уже неактуально.');
        }

        $settings = $duel->settings ?? [];
        $settings['cancel_reason'] = 'rematch_cancelled_by_initiator';
        $settings['cancelled_by_user_id'] = (int) $initiator->getKey();
        $duel->settings = $settings;
        $duel->status = 'cancelled';
        $duel->finished_at = Carbon::now();
        $duel->save();

        return $duel->refresh();
    }

    private function expireRematchIfNeeded(Duel $duel): bool
    {
        if (!$this->isRematchInvite($duel) || $duel->status !== 'waiting') {
            return false;
        }

        $settings = $duel->settings ?? [];
        $expiresAtRaw = (string) ($settings['rematch_expires_at'] ?? '');
        if ($expiresAtRaw === '') {
            return false;
        }

        try {
            $expiresAt = Carbon::parse($expiresAtRaw);
        } catch (\Throwable $e) {
            return false;
        }

        if (Carbon::now()->lt($expiresAt)) {
            return false;
        }

        $settings['cancel_reason'] = 'rematch_expired';
        $duel->settings = $settings;
        $duel->status = 'cancelled';
        $duel->finished_at = Carbon::now();
        $duel->save();

        return true;
    }
}
