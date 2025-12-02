<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use QuizBot\Infrastructure\Config\Config;
use QuizBot\Domain\Model\User;
use Monolog\Logger;
use GuzzleHttp\ClientInterface;

final class AdminService
{
    private Config $config;
    private Logger $logger;
    private DuelService $duelService;
    private ClientInterface $telegramClient;

    public function __construct(Config $config, Logger $logger, DuelService $duelService, ClientInterface $telegramClient)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->duelService = $duelService;
        $this->telegramClient = $telegramClient;
    }

    public function isAdmin(User $user): bool
    {
        $adminIds = $this->getAdminTelegramIds();

        if (empty($adminIds)) {
            return false;
        }

        return \in_array($user->telegram_id, $adminIds, true);
    }

    /**
     * @return array<int>
     */
    private function getAdminTelegramIds(): array
    {
        $adminIdsStr = $this->config->get('ADMIN_TELEGRAM_IDS', '');

        // Преобразуем в строку, если это число или другой тип
        if (!is_string($adminIdsStr)) {
            if (is_numeric($adminIdsStr)) {
                $adminIdsStr = (string) $adminIdsStr;
            } else {
                return [];
            }
        }

        if (empty(trim($adminIdsStr))) {
            return [];
        }

        $ids = explode(',', $adminIdsStr);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, function ($id) {
            return is_numeric($id) && !empty($id);
        });

        return array_map('intval', $ids);
    }

    /**
     * Завершает все активные дуэли
     *
     * @return array{completed: int, cancelled: int, errors: array<string>}
     */
    public function finishAllActiveDuels(): array
    {
        $result = [
            'completed' => 0,
            'cancelled' => 0,
            'errors' => [],
        ];

        $activeDuels = \QuizBot\Domain\Model\Duel::query()
            ->whereIn('status', ['waiting', 'matched', 'in_progress'])
            ->with(['initiator', 'opponent', 'rounds'])
            ->get();

        foreach ($activeDuels as $duel) {
            try {
                if ($duel->status === 'waiting' && $duel->opponent_user_id === null) {
                    // Просто отменяем ожидающие дуэли без соперника
                    $duel->status = 'cancelled';
                    $duel->finished_at = \Illuminate\Support\Carbon::now();
                    $duel->save();
                    $result['cancelled']++;
                    $this->logger->info(sprintf('Админ: отменена ожидающая дуэль %s', $duel->code));
                } elseif ($duel->opponent_user_id !== null) {
                    // Завершаем дуэли с соперником
                    $this->duelService->finalizeDuel($duel);
                    $result['completed']++;
                    $this->logger->info(sprintf('Админ: завершена дуэль %s', $duel->code));
                } else {
                    // Отменяем остальные
                    $duel->status = 'cancelled';
                    $duel->finished_at = \Illuminate\Support\Carbon::now();
                    $duel->save();
                    $result['cancelled']++;
                }
            } catch (\Throwable $e) {
                $errorMsg = sprintf('Ошибка при завершении дуэли %s: %s', $duel->code, $e->getMessage());
                $result['errors'][] = $errorMsg;
                $this->logger->error($errorMsg, [
                    'duel_id' => $duel->getKey(),
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /**
     * Сбрасывает рейтинг всех пользователей до 0
     *
     * @return int Количество обновленных профилей
     */
    public function resetAllRatings(): int
    {
        $updated = \QuizBot\Domain\Model\UserProfile::query()
            ->where('rating', '!=', 0)
            ->update(['rating' => 0]);

        $this->logger->info(sprintf('Админ: сброшен рейтинг у %d пользователей', $updated));

        return $updated;
    }

    /**
     * Получить всех админов по их telegram_id
     *
     * @return array<User>
     */
    public function getAdminUsers(): array
    {
        $adminIds = $this->getAdminTelegramIds();

        if (empty($adminIds)) {
            return [];
        }

        return User::query()
            ->whereIn('telegram_id', $adminIds)
            ->get()
            ->all();
    }

    /**
     * Отправляет сообщение от пользователя всем админам
     */
    public function sendFeedbackToAdmins(User $fromUser, string $messageText): void
    {
        $adminIds = $this->getAdminTelegramIds();
        if (empty($adminIds)) {
            $this->logger->warning('Нет настроенных админов для отправки обратной связи.');
            return;
        }

        $senderName = $this->formatUserName($fromUser);
        $feedbackMessage = sprintf(
            "📩 <b>Новое сообщение от пользователя</b>\n\n" .
            "От: %s (ID: %d)\n" .
            "Сообщение:\n<i>%s</i>",
            $senderName,
            $fromUser->telegram_id,
            htmlspecialchars($messageText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        foreach ($adminIds as $adminTelegramId) {
            try {
                $this->telegramClient->request('POST', 'sendMessage', [
                    'json' => [
                        'chat_id' => $adminTelegramId,
                        'text' => $feedbackMessage,
                        'parse_mode' => 'HTML',
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => '💬 Ответить',
                                        'callback_data' => sprintf('admin:reply_to_user:%d', $fromUser->getKey()),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
                $this->logger->info('Обратная связь отправлена админу', ['admin_id' => $adminTelegramId, 'from_user_id' => $fromUser->getKey()]);
            } catch (\Throwable $e) {
                $this->logger->error('Не удалось отправить обратную связь админу', [
                    'admin_id' => $adminTelegramId,
                    'from_user_id' => $fromUser->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Форматирует имя пользователя для отображения
     */
    private function formatUserName(User $user): string
    {
        if (!empty($user->first_name) && !empty($user->last_name)) {
            return htmlspecialchars($user->first_name . ' ' . $user->last_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->first_name)) {
            return htmlspecialchars($user->first_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!empty($user->username)) {
            return '@' . htmlspecialchars($user->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return 'Пользователь #' . $user->getKey();
    }
}

