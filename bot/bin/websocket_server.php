<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Infrastructure\Config\Config;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\SocketServer;

require dirname(__DIR__) . '/vendor/autoload.php';

final class DuelWsServer implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, array{user_id:int, duel_id:int, last_seen:int}> */
    private SplObjectStorage $clients;

    /** @var array<int, string> */
    private array $stateHashes = [];

    /** @var array<string, int> */
    private array $consumedTicketJti = [];

    /** @var string */
    private string $secret;

    /** @var int */
    private int $clientTimeoutSeconds;

    /** @var int */
    private int $maxMessageBytes;

    /** @var string */
    private string $eventsPath;

    /** @var array<int, string> */
    private array $forcedEventVersion = [];

    /** @var array<int, float> */
    private array $lastForcedPushAt = [];

    /** @var int */
    private int $forcedMinIntervalMs;

    /** @var int */
    private int $eventsTtlSeconds;

    /** @var int */
    private int $statsIntervalSeconds;

    /** @var int */
    private int $signalsConsumed = 0;

    /** @var int */
    private int $forcedPushBatches = 0;

    /** @var int */
    private int $diffPushBatches = 0;

    /** @var int */
    private int $sendFailures = 0;

    /** @var int */
    private int $connectionsOpened = 0;

    /** @var int */
    private int $connectionsClosed = 0;

    /** @var int */
    private int $lastStatsLoggedAt = 0;

    /** @var float */
    private float $lastFullSyncAt = 0.0;

    /** @var int */
    private int $fullSyncIntervalSeconds;

    /** @var int */
    private int $fullSyncBatchSize;

    /** @var int */
    private int $fullSyncCursor = 0;

    public function __construct(
        string $secret,
        int $clientTimeoutSeconds,
        int $maxMessageBytes,
        string $eventsPath,
        int $forcedMinIntervalMs,
        int $eventsTtlSeconds,
        int $statsIntervalSeconds,
        int $fullSyncIntervalSeconds,
        int $fullSyncBatchSize
    )
    {
        $this->secret = $secret;
        $this->clientTimeoutSeconds = max(30, $clientTimeoutSeconds);
        $this->maxMessageBytes = max(256, $maxMessageBytes);
        $this->eventsPath = $eventsPath;
        $this->forcedMinIntervalMs = max(0, $forcedMinIntervalMs);
        $this->eventsTtlSeconds = max(30, $eventsTtlSeconds);
        $this->statsIntervalSeconds = max(10, $statsIntervalSeconds);
        $this->fullSyncIntervalSeconds = max(1, $fullSyncIntervalSeconds);
        $this->fullSyncBatchSize = max(1, $fullSyncBatchSize);
        $this->lastStatsLoggedAt = time();
        $this->clients = new SplObjectStorage();

        if (!is_dir($this->eventsPath)) {
            @mkdir($this->eventsPath, 0775, true);
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        parse_str($conn->httpRequest->getUri()->getQuery(), $query);
        $ticket = (string) ($query['ticket'] ?? '');

        $payload = $this->decodeTicket($ticket);
        $now = time();
        $ticketValid = is_array($payload)
            && isset($payload['duel_id'], $payload['user_id'], $payload['iat'], $payload['exp'], $payload['jti'])
            && (int) $payload['duel_id'] > 0
            && (int) $payload['user_id'] > 0
            && (int) $payload['iat'] <= ($now + 5)
            && (int) $payload['exp'] >= $now
            && is_string($payload['jti'])
            && $payload['jti'] !== '';

        if (!$ticketValid) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'invalid_ticket'], JSON_UNESCAPED_UNICODE));
            $conn->close();
            return;
        }

        $jti = (string) $payload['jti'];
        if (isset($this->consumedTicketJti[$jti]) && $this->consumedTicketJti[$jti] >= $now) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'ticket_already_used'], JSON_UNESCAPED_UNICODE));
            $conn->close();
            return;
        }

        $duel = \QuizBot\Domain\Model\Duel::query()->find((int) $payload['duel_id']);
        $isParticipant = $duel
            && (
                (int) $duel->initiator_user_id === (int) $payload['user_id']
                || (int) ($duel->opponent_user_id ?? 0) === (int) $payload['user_id']
            );

        if (!$isParticipant) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'duel_access_denied'], JSON_UNESCAPED_UNICODE));
            $conn->close();
            return;
        }

        if (in_array((string) $duel->status, ['finished', 'cancelled'], true)) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'duel_closed'], JSON_UNESCAPED_UNICODE));
            $conn->close();
            return;
        }

        $this->consumedTicketJti[$jti] = (int) $payload['exp'];

        $this->closeDuplicateConnections((int) $payload['duel_id'], (int) $payload['user_id']);

        $this->clients->attach($conn, [
            'user_id' => (int) $payload['user_id'],
            'duel_id' => (int) $payload['duel_id'],
            'last_seen' => $now,
        ]);
        $this->connectionsOpened++;

        $conn->send(json_encode([
            'type' => 'connected',
            'duel_id' => (int) $payload['duel_id'],
            'server_ts' => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $rawMessage = (string) $msg;
        $this->touchConnection($from);

        if (strlen($rawMessage) > $this->maxMessageBytes) {
            $this->safeSend($from, json_encode(['type' => 'error', 'message' => 'message_too_large'], JSON_UNESCAPED_UNICODE));
            $from->close();
            return;
        }

        $payload = json_decode($rawMessage, true);
        if (!is_array($payload)) {
            return;
        }

        $type = (string) ($payload['type'] ?? '');

        if ($type === 'ping') {
            $from->send(json_encode(['type' => 'pong', 'ts' => time()], JSON_UNESCAPED_UNICODE));
            return;
        }

        if ($type === 'pong') {
            return;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
            $this->connectionsClosed++;
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        error_log('[ws] connection error: ' . $e->getMessage());
        $conn->close();
    }

    public function pushDiffUpdates(): void
    {
        $now = time();
        $this->pruneInactiveClients($now);
        $this->consumeEventSignals();
        $this->cleanupStaleSignalFiles($now);
        $this->logStatsIfNeeded($now);

        foreach ($this->consumedTicketJti as $jti => $expiresAt) {
            if ($expiresAt < $now) {
                unset($this->consumedTicketJti[$jti]);
            }
        }

        if (count($this->clients) === 0) {
            return;
        }

        $nowTs = microtime(true);
        if (($nowTs - $this->lastFullSyncAt) < $this->fullSyncIntervalSeconds) {
            return;
        }
        $this->lastFullSyncAt = $nowTs;

        $duelIds = array_keys($this->getConnectedDuelIds());
        if (count($duelIds) === 0) {
            return;
        }

        $selected = $this->selectFullSyncBatch($duelIds);
        if (count($selected) > 0) {
            $this->diffPushBatches++;
        }
        $this->pushUpdatesForDuels($selected);
    }

    /**
     * Быстрый проход: обрабатывает только форс-сигналы дуэлей.
     * Используется отдельным таймером с малым интервалом.
     */
    public function pushForcedUpdates(): void
    {
        if (count($this->clients) === 0) {
            return;
        }

        $this->consumeEventSignals();
        if (count($this->forcedEventVersion) === 0) {
            return;
        }

        $connected = $this->getConnectedDuelIds();
        if (count($connected) === 0) {
            return;
        }

        $now = microtime(true);
        $duelIds = [];
        foreach (array_keys($this->forcedEventVersion) as $duelId) {
            if (!isset($connected[$duelId])) {
                // Нет подключенных клиентов к этой дуэли: сигнал нам не нужен.
                unset($this->forcedEventVersion[$duelId]);
                continue;
            }

            if ($this->forcedMinIntervalMs > 0) {
                $lastPushAt = $this->lastForcedPushAt[$duelId] ?? 0.0;
                $deltaMs = (int) (($now - $lastPushAt) * 1000);
                if ($deltaMs < $this->forcedMinIntervalMs) {
                    continue;
                }
            }

            $duelIds[] = $duelId;
            $this->lastForcedPushAt[$duelId] = $now;
        }

        if (count($duelIds) === 0) {
            return;
        }

        $this->forcedPushBatches++;
        $this->pushUpdatesForDuels($duelIds);
    }

    /**
     * @param array<int> $duelIds
     */
    private function pushUpdatesForDuels(array $duelIds): void
    {
        foreach ($duelIds as $duelId) {
            $duel = \QuizBot\Domain\Model\Duel::query()->find($duelId);
            if (!$duel) {
                unset($this->forcedEventVersion[$duelId], $this->stateHashes[$duelId], $this->lastForcedPushAt[$duelId]);
                continue;
            }

            $lastRound = \QuizBot\Domain\Model\DuelRound::query()
                ->where('duel_id', $duelId)
                ->orderByDesc('round_number')
                ->first();

            $snapshot = [
                'duel_id' => $duelId,
                'status' => $duel->status,
                'updated_at' => (string) $duel->updated_at,
                'last_round_number' => $lastRound ? $lastRound->round_number : null,
                'last_round_closed_at' => (string) ($lastRound ? $lastRound->closed_at : ''),
                'event_version' => $this->forcedEventVersion[$duelId] ?? null,
            ];

            $hash = hash('sha256', json_encode($snapshot));
            if (($this->stateHashes[$duelId] ?? null) === $hash) {
                unset($this->forcedEventVersion[$duelId]);
                continue;
            }
            $this->stateHashes[$duelId] = $hash;

            $event = json_encode(['type' => 'duel_update', 'payload' => $snapshot], JSON_UNESCAPED_UNICODE);
            $isTerminalStatus = in_array((string) $duel->status, ['finished', 'cancelled'], true);
            $clientsToDetach = [];

            foreach ($this->clients as $client) {
                $ctx = $this->clients[$client];
                if ($ctx['duel_id'] !== $duelId) {
                    continue;
                }

                if (!$this->safeSend($client, $event)) {
                    $clientsToDetach[] = $client;
                    continue;
                }

                if ($isTerminalStatus) {
                    $this->safeSend($client, json_encode(['type' => 'duel_closed', 'duel_id' => $duelId], JSON_UNESCAPED_UNICODE));
                    $client->close();
                    $clientsToDetach[] = $client;
                }
            }

            foreach ($clientsToDetach as $client) {
                if ($this->clients->contains($client)) {
                    $this->clients->detach($client);
                }
            }

            if (isset($this->forcedEventVersion[$duelId])) {
                unset($this->forcedEventVersion[$duelId]);
            }
        }
    }

    /**
     * @return array<int, bool>
     */
    private function getConnectedDuelIds(): array
    {
        $duelIds = [];
        foreach ($this->clients as $client) {
            $ctx = $this->clients[$client];
            $duelIds[$ctx['duel_id']] = true;
        }

        return $duelIds;
    }

    /**
     * @param array<int> $duelIds
     * @return array<int>
     */
    private function selectFullSyncBatch(array $duelIds): array
    {
        $total = count($duelIds);
        if ($total <= $this->fullSyncBatchSize) {
            $this->fullSyncCursor = 0;
            return $duelIds;
        }

        $batch = [];
        $start = $this->fullSyncCursor % $total;
        for ($i = 0; $i < $this->fullSyncBatchSize; $i++) {
            $idx = ($start + $i) % $total;
            $batch[] = $duelIds[$idx];
        }

        $this->fullSyncCursor = ($start + $this->fullSyncBatchSize) % $total;
        return $batch;
    }

    private function consumeEventSignals(): void
    {
        if (!is_dir($this->eventsPath)) {
            return;
        }

        $files = glob($this->eventsPath . '/duel_*.signal') ?: [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (!preg_match('/^duel_(\d+)\.signal$/', $basename, $m)) {
                continue;
            }

            $duelId = (int) $m[1];
            if ($duelId <= 0) {
                @unlink($file);
                continue;
            }

            $version = @file_get_contents($file);
            if (!is_string($version) || $version === '') {
                $version = (string) microtime(true);
            }

            $this->forcedEventVersion[$duelId] = trim($version);
            $this->signalsConsumed++;
            @unlink($file);
        }
    }

    private function cleanupStaleSignalFiles(int $now): void
    {
        if (!is_dir($this->eventsPath)) {
            return;
        }

        $files = glob($this->eventsPath . '/duel_*.signal') ?: [];
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false) {
                continue;
            }

            if (($now - $mtime) > $this->eventsTtlSeconds) {
                @unlink($file);
            }
        }
    }

    private function logStatsIfNeeded(int $now): void
    {
        if (($now - $this->lastStatsLoggedAt) < $this->statsIntervalSeconds) {
            return;
        }

        $this->lastStatsLoggedAt = $now;
        $connectedClients = count($this->clients);
        $duelCount = count($this->getConnectedDuelIds());

        echo sprintf(
            "[ws] stats clients=%d duels=%d opened=%d closed=%d signals=%d forced_batches=%d diff_batches=%d send_failures=%d\n",
            $connectedClients,
            $duelCount,
            $this->connectionsOpened,
            $this->connectionsClosed,
            $this->signalsConsumed,
            $this->forcedPushBatches,
            $this->diffPushBatches,
            $this->sendFailures
        );
    }

    private function touchConnection(ConnectionInterface $conn): void
    {
        if (!$this->clients->contains($conn)) {
            return;
        }

        $ctx = $this->clients[$conn];
        $ctx['last_seen'] = time();
        $this->clients[$conn] = $ctx;
    }

    private function pruneInactiveClients(int $now): void
    {
        $toClose = [];

        foreach ($this->clients as $client) {
            $ctx = $this->clients[$client];
            if (($now - (int) ($ctx['last_seen'] ?? 0)) > $this->clientTimeoutSeconds) {
                $toClose[] = $client;
            }
        }

        foreach ($toClose as $client) {
            $this->safeSend($client, json_encode(['type' => 'error', 'message' => 'connection_timeout'], JSON_UNESCAPED_UNICODE));
            $client->close();
            if ($this->clients->contains($client)) {
                $this->clients->detach($client);
            }
        }
    }

    private function safeSend(ConnectionInterface $conn, string $payload): bool
    {
        try {
            $conn->send($payload);
            return true;
        } catch (Throwable $e) {
            error_log('[ws] send failed: ' . $e->getMessage());
            $this->sendFailures++;
            return false;
        }
    }


    /**
     * Закрывает старые соединения того же пользователя в рамках той же дуэли.
     */
    private function closeDuplicateConnections(int $duelId, int $userId): void
    {
        $toClose = [];

        foreach ($this->clients as $client) {
            $ctx = $this->clients[$client];
            if ($ctx['duel_id'] === $duelId && $ctx['user_id'] === $userId) {
                $toClose[] = $client;
            }
        }

        foreach ($toClose as $client) {
            if ($this->clients->contains($client)) {
                $client->close();
                $this->clients->detach($client);
            }
        }
    }

    private function decodeTicket(string $ticket): ?array
    {
        if ($ticket === '' || strpos($ticket, '.') === false) {
            return null;
        }

        [$encoded, $signature] = explode('.', $ticket, 2);

        $base64 = strtr($encoded, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode($base64, true);
        if ($json === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $json, $this->secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }
}

$app = new AppBootstrap(dirname(__DIR__));
$container = $app->getContainer();
/** @var Config $config */
$config = $container->get(Config::class);

$secret = (string) $config->get('WEBSOCKET_TICKET_SECRET', $config->get('TELEGRAM_BOT_TOKEN', ''));
$wsHost = (string) $config->get('WEBSOCKET_HOST', '0.0.0.0');
$wsPort = (int) $config->get('WEBSOCKET_PORT', 8090);
$syncInterval = max(0.5, (float) $config->get('WEBSOCKET_SYNC_INTERVAL', 1));
$eventPollInterval = max(0.1, (float) $config->get('WEBSOCKET_EVENT_POLL_INTERVAL', 0.2));
$clientTimeoutSeconds = max(30, (int) $config->get('WEBSOCKET_CLIENT_TIMEOUT_SECONDS', 70));
$maxMessageBytes = max(256, (int) $config->get('WEBSOCKET_MAX_MESSAGE_BYTES', 2048));
$eventsPath = (string) $config->get('WEBSOCKET_EVENTS_PATH', dirname(__DIR__) . '/storage/runtime/duel_events');
$forcedMinIntervalMs = max(0, (int) $config->get('WEBSOCKET_FORCED_MIN_INTERVAL_MS', 120));
$eventsTtlSeconds = max(30, (int) $config->get('WEBSOCKET_EVENTS_TTL_SECONDS', 120));
$statsIntervalSeconds = max(10, (int) $config->get('WEBSOCKET_STATS_INTERVAL_SECONDS', 30));
$fullSyncIntervalSeconds = max(1, (int) $config->get('WEBSOCKET_FULL_SYNC_INTERVAL_SECONDS', 4));
$fullSyncBatchSize = max(1, (int) $config->get('WEBSOCKET_FULL_SYNC_BATCH_SIZE', 50));

if ($secret === '') {
    fwrite(STDERR, "[ws] WEBSOCKET_TICKET_SECRET (or TELEGRAM_BOT_TOKEN fallback) is empty\n");
    exit(1);
}

$component = new DuelWsServer(
    $secret,
    $clientTimeoutSeconds,
    $maxMessageBytes,
    $eventsPath,
    $forcedMinIntervalMs,
    $eventsTtlSeconds,
    $statsIntervalSeconds,
    $fullSyncIntervalSeconds,
    $fullSyncBatchSize
);
$loop = LoopFactory::create();
$loop->addPeriodicTimer($syncInterval, static fn() => $component->pushDiffUpdates());
$loop->addPeriodicTimer($eventPollInterval, static fn() => $component->pushForcedUpdates());

$socket = new SocketServer("{$wsHost}:{$wsPort}", [], $loop);
$server = new IoServer(new HttpServer(new WsServer($component)), $socket, $loop);

echo "[ws] Duel server started at {$wsHost}:{$wsPort}, sync_tick={$syncInterval}s, full_sync={$fullSyncIntervalSeconds}s, full_sync_batch={$fullSyncBatchSize}, event_poll={$eventPollInterval}s, forced_min_interval={$forcedMinIntervalMs}ms, events_ttl={$eventsTtlSeconds}s, stats_interval={$statsIntervalSeconds}s, timeout={$clientTimeoutSeconds}s, max_message={$maxMessageBytes}B\n";
$server->run();
