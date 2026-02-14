<?php

declare(strict_types=1);

namespace QuizBot\Presentation\Api\Controllers;

use Psr\Container\ContainerInterface;
use QuizBot\Application\Services\TeamService;
use QuizBot\Application\Services\UserService;

class TeamController extends BaseController
{
    private TeamService $teamService;
    private UserService $userService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->teamService = $container->get(TeamService::class);
        $this->userService = $container->get(UserService::class);
    }

    public function create(?array $telegramUser, array $body): void
    {
        if (!$telegramUser) {
            $this->jsonError('Не авторизован', 401);
        }

        $user = $this->userService->findByTelegramId((int) $telegramUser['id']);
        
        try {
            $name = $body['name'] ?? '';
            $tag = $body['tag'] ?? '';
            
            if (strlen($name) < 3 || strlen($tag) < 2) {
                throw new \RuntimeException('Слишком короткое название или тег');
            }

            $team = $this->teamService->createTeam($user, $name, $tag);
            $this->jsonResponse(['team_id' => $team->getKey()]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function join(?array $telegramUser, array $body): void
    {
        if (!$telegramUser) {
            $this->jsonError('Не авторизован', 401);
        }

        $user = $this->userService->findByTelegramId((int) $telegramUser['id']);
        $teamId = (int) ($body['team_id'] ?? 0);

        try {
            $this->teamService->joinTeam($user, $teamId);
            $this->jsonResponse(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function leave(?array $telegramUser): void
    {
        if (!$telegramUser) {
            $this->jsonError('Не авторизован', 401);
        }

        $user = $this->userService->findByTelegramId((int) $telegramUser['id']);

        try {
            $this->teamService->leaveTeam($user);
            $this->jsonResponse(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function getMyTeam(?array $telegramUser): void
    {
        if (!$telegramUser) {
            $this->jsonError('Не авторизован', 401);
        }

        $user = $this->userService->findByTelegramId((int) $telegramUser['id']);
        $team = $this->teamService->getUserTeam($user);

        $this->jsonResponse(['team' => $team]);
    }

    public function getLeaderboard(): void
    {
        $limit = 50;
        $leaders = $this->teamService->getLeaderboard($limit);
        $this->jsonResponse(['leaders' => $leaders]);
    }
}
