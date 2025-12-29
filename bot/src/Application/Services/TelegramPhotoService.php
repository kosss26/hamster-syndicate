<?php

declare(strict_types=1);

namespace QuizBot\Application\Services;

use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use QuizBot\Domain\Model\User;

class TelegramPhotoService
{
    private ClientInterface $telegramClient;
    private Logger $logger;

    public function __construct(ClientInterface $telegramClient, Logger $logger)
    {
        $this->telegramClient = $telegramClient;
        $this->logger = $logger;
    }

    /**
     * Получить URL фото профиля пользователя из Telegram
     */
    public function getUserPhotoUrl(int $telegramId): ?string
    {
        try {
            // Получаем фото профиля
            $response = $this->telegramClient->request('POST', 'getUserProfilePhotos', [
                'json' => [
                    'user_id' => $telegramId,
                    'limit' => 1,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['ok']) || !$data['ok']) {
                $this->logger->warning('Не удалось получить фото профиля', [
                    'telegram_id' => $telegramId,
                    'response' => $data,
                ]);
                return null;
            }

            $photos = $data['result']['photos'] ?? [];
            
            if (empty($photos) || empty($photos[0])) {
                // У пользователя нет фото
                return null;
            }

            // Берём последнее (самое большое) фото
            $photoSizes = $photos[0];
            $largestPhoto = end($photoSizes);

            if (!isset($largestPhoto['file_id'])) {
                return null;
            }

            // Получаем file_path
            $fileResponse = $this->telegramClient->request('POST', 'getFile', [
                'json' => [
                    'file_id' => $largestPhoto['file_id'],
                ],
            ]);

            $fileData = json_decode($fileResponse->getBody()->getContents(), true);

            if (!isset($fileData['ok']) || !$fileData['ok'] || !isset($fileData['result']['file_path'])) {
                return null;
            }

            $filePath = $fileData['result']['file_path'];

            // Получаем bot token из base_uri клиента
            $baseUri = $this->telegramClient->getConfig('base_uri');
            preg_match('/bot(\d+:[A-Za-z0-9_-]+)/', $baseUri, $matches);
            $botToken = $matches[1] ?? '';

            if (empty($botToken)) {
                $this->logger->error('Не удалось извлечь bot token из base_uri');
                return null;
            }

            // Формируем полный URL
            $photoUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

            $this->logger->info('Получен URL фото профиля', [
                'telegram_id' => $telegramId,
                'photo_url' => $photoUrl,
            ]);

            return $photoUrl;

        } catch (\Throwable $e) {
            $this->logger->error('Ошибка при получении фото профиля', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Обновить фото профиля для пользователя
     */
    public function updateUserPhoto(User $user): bool
    {
        $photoUrl = $this->getUserPhotoUrl($user->telegram_id);

        if ($photoUrl === null) {
            return false;
        }

        $user->photo_url = $photoUrl;
        $user->save();

        return true;
    }
}

