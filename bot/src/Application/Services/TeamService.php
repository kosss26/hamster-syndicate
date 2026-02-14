<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use QuizBot\Domain\Model\Team;
use QuizBot\Domain\Model\TeamMember;
use QuizBot\Domain\Model\User;
use Illuminate\Database\Capsule\Manager as DB;

class TeamService
{
    public function createTeam(User $owner, string $name, string $tag): Team
    {
        // Проверка, что юзер не в клане
        if (TeamMember::where('user_id', $owner->getKey())->exists()) {
            throw new \RuntimeException('Вы уже состоите в клане');
        }

        return DB::transaction(function () use ($owner, $name, $tag) {
            $team = new Team([
                'name' => $name,
                'tag' => strtoupper($tag),
                'owner_id' => $owner->getKey(),
                'members_count' => 1,
            ]);
            $team->save();

            $member = new TeamMember([
                'team_id' => $team->getKey(),
                'user_id' => $owner->getKey(),
                'role' => 'owner',
            ]);
            $member->save();

            return $team;
        });
    }

    public function joinTeam(User $user, int $teamId): void
    {
        if (TeamMember::where('user_id', $user->getKey())->exists()) {
            throw new \RuntimeException('Вы уже состоите в клане');
        }

        $team = Team::find($teamId);
        if (!$team) {
            throw new \RuntimeException('Клан не найден');
        }

        if (!$team->is_open) {
            throw new \RuntimeException('Клан закрыт для вступления');
        }

        // Проверка рейтинга
        $userRating = $user->profile?->rating ?? 0;
        if ($userRating < $team->min_rating) {
            throw new \RuntimeException("Недостаточно рейтинга (нужно {$team->min_rating})");
        }

        DB::transaction(function () use ($user, $team) {
            $member = new TeamMember([
                'team_id' => $team->getKey(),
                'user_id' => $user->getKey(),
                'role' => 'member',
            ]);
            $member->save();

            $team->increment('members_count');
        });
    }

    public function leaveTeam(User $user): void
    {
        $member = TeamMember::where('user_id', $user->getKey())->first();
        if (!$member) {
            throw new \RuntimeException('Вы не состоите в клане');
        }

        if ($member->role === 'owner') {
            throw new \RuntimeException('Владелец не может покинуть клан. Передайте права или удалите клан.');
        }

        DB::transaction(function () use ($member) {
            $team = $member->team;
            $member->delete();
            $team->decrement('members_count');
        });
    }

    public function getTeam(int $teamId): ?Team
    {
        return Team::with(['members.user', 'owner'])->find($teamId);
    }

    public function getUserTeam(User $user): ?Team
    {
        $member = TeamMember::where('user_id', $user->getKey())->first();
        return $member ? $member->team()->with('members.user')->first() : null;
    }

    public function getLeaderboard(int $limit = 50): array
    {
        return Team::orderByDesc('score')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
