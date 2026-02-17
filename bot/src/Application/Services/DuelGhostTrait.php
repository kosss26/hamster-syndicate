<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use Illuminate\Support\Carbon;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelGhostSnapshot;
use QuizBot\Domain\Model\User;

trait DuelGhostTrait
{
    private function selectGhostSnapshotForUser(User $user): ?DuelGhostSnapshot
    {
        $user = $user->fresh(['profile']);
        $targetRating = 1000;
        if ($user && $user->profile) {
            $targetRating = (int) $user->profile->rating;
        }
        $ratingMin = max(0, $targetRating - 250);
        $ratingMax = $targetRating + 250;

        $baseQuery = DuelGhostSnapshot::query()
            ->leftJoin('duel_ghost_plays', function ($join) use ($user): void {
                $join->on('duel_ghost_plays.snapshot_id', '=', 'duel_ghost_snapshots.id')
                    ->where('duel_ghost_plays.user_id', '=', (int) $user->getKey());
            })
            ->whereNull('duel_ghost_plays.id')
            ->where('duel_ghost_snapshots.source_user_id', '!=', (int) $user->getKey())
            ->orderByDesc('duel_ghost_snapshots.quality_score')
            ->orderByRaw('ABS(duel_ghost_snapshots.source_rating - ?) ASC', [$targetRating])
            ->orderByDesc('duel_ghost_snapshots.created_at')
            ->select('duel_ghost_snapshots.*');

        $strict = (clone $baseQuery)
            ->whereBetween('duel_ghost_snapshots.source_rating', [$ratingMin, $ratingMax])
            ->first();

        if ($strict instanceof DuelGhostSnapshot) {
            return $strict;
        }

        return (clone $baseQuery)->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildGhostRoundConfigsFromSnapshot(DuelGhostSnapshot $snapshot): array
    {
        $payload = is_array($snapshot->rounds_payload) ? $snapshot->rounds_payload : [];
        $rounds = is_array($payload['rounds'] ?? null) ? $payload['rounds'] : [];
        $configs = [];

        foreach ($rounds as $round) {
            if (!is_array($round)) {
                continue;
            }

            $questionId = (int) ($round['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $ghostPayload = $this->normalizeGhostRoundPayload($round);
            $configs[] = [
                'question_id' => $questionId,
                'time_limit' => max(10, (int) ($round['time_limit'] ?? 30)),
                'opponent_payload' => $ghostPayload,
            ];
        }

        return $configs;
    }

    /**
     * @param array<string, mixed> $round
     * @return array<string, mixed>
     */
    private function normalizeGhostRoundPayload(array $round): array
    {
        $isCorrect = (bool) ($round['is_correct'] ?? false);
        $reason = isset($round['reason']) ? (string) $round['reason'] : null;
        $timeElapsed = max(0, (int) ($round['time_elapsed'] ?? 0));

        return [
            'completed' => true,
            'is_correct' => $isCorrect,
            'answer_id' => isset($round['answer_id']) ? (int) $round['answer_id'] : null,
            'score' => $isCorrect ? 1 : 0,
            'reason' => $reason,
            'time_elapsed' => $timeElapsed,
            'answered_at' => isset($round['answered_at']) ? (string) $round['answered_at'] : Carbon::now()->toAtomString(),
        ];
    }

    private function isGhostMatch(Duel $duel): bool
    {
        $settings = is_array($duel->settings) ? $duel->settings : [];
        return $this->isGhostModeSettings($settings);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function isGhostModeSettings(array $settings): bool
    {
        return (($settings['ghost_mode'] ?? false) === true) || (($settings['match_type'] ?? '') === 'ghost');
    }

    private function createGhostSnapshotsForDuel(Duel $duel): void
    {
        if ($this->isGhostMatch($duel)) {
            return;
        }

        $duel->loadMissing('rounds.question', 'initiator.profile', 'opponent.profile');
        if (!$duel->opponent) {
            return;
        }

        $participants = [
            [
                'user' => $duel->initiator,
                'is_initiator' => true,
            ],
            [
                'user' => $duel->opponent,
                'is_initiator' => false,
            ],
        ];

        foreach ($participants as $participant) {
            /** @var User|null $participantUser */
            $participantUser = $participant['user'];
            if (!$participantUser) {
                continue;
            }

            $rounds = [];
            $validRounds = 0;
            foreach ($duel->rounds as $round) {
                $payload = $participant['is_initiator'] ? ($round->initiator_payload ?? []) : ($round->opponent_payload ?? []);
                if (!is_array($payload) || empty($payload['completed'])) {
                    continue;
                }

                $questionId = (int) $round->question_id;
                if ($questionId <= 0) {
                    continue;
                }

                $rounds[] = [
                    'round_number' => (int) $round->round_number,
                    'question_id' => $questionId,
                    'time_limit' => max(10, (int) ($round->time_limit ?? 30)),
                    'answer_id' => isset($payload['answer_id']) && $payload['answer_id'] !== null ? (int) $payload['answer_id'] : null,
                    'is_correct' => (bool) ($payload['is_correct'] ?? false),
                    'reason' => isset($payload['reason']) ? (string) $payload['reason'] : null,
                    'time_elapsed' => max(0, (int) ($payload['time_elapsed'] ?? 0)),
                    'answered_at' => isset($payload['answered_at']) ? (string) $payload['answered_at'] : null,
                ];
                $validRounds++;
            }

            if ($validRounds < 3) {
                continue;
            }

            $qualityScore = (int) round($validRounds * 10);
            DuelGhostSnapshot::query()->create([
                'source_duel_id' => (int) $duel->getKey(),
                'source_user_id' => (int) $participantUser->getKey(),
                'source_rating' => $participantUser->profile ? (int) $participantUser->profile->rating : 1000,
                'question_count' => $validRounds,
                'quality_score' => $qualityScore,
                'rounds_payload' => ['rounds' => $rounds],
                'created_at' => Carbon::now(),
            ]);
        }
    }
}
