<?php

declare(strict_types=1);

namespace QuizBot\Bootstrap;

use DI\Container;
use DI\ContainerBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use QuizBot\Infrastructure\Config\Config;
use QuizBot\Infrastructure\Logging\LoggerFactory;
use QuizBot\Infrastructure\Telegram\TelegramClientFactory;
use QuizBot\Infrastructure\Telegram\WebhookHandler;
use QuizBot\Infrastructure\Cache\CacheFactory;
use QuizBot\Infrastructure\Database\MigrationRunner;
use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\QuestionSelector;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\GameSessionService;
use QuizBot\Application\Services\StoryService;
use QuizBot\Application\Services\ProfileFormatter;
use QuizBot\Application\Services\HintService;
use QuizBot\Application\Services\TrueFalseService;
use QuizBot\Application\Services\StatisticsService;
use QuizBot\Application\Services\ReferralService;
use QuizBot\Database\Seeders\SampleDataSeeder;

final class AppBootstrap
{
    private ContainerInterface $container;

    private ?WebhookHandler $webhookHandler = null;

    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->container = $this->buildContainer();
        $this->bootstrapDatabase();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getWebhookHandler(): WebhookHandler
    {
        if ($this->webhookHandler === null) {
            $this->webhookHandler = $this->container->get(WebhookHandler::class);
        }

        return $this->webhookHandler;
    }

    private function buildContainer(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            'basePath' => $this->basePath,
            Config::class => function () {
                return Config::fromEnv($this->basePath . '/config');
            },
            Logger::class => function (Container $c) {
                /** @var Config $config */
                $config = $c->get(Config::class);

                $loggerFactory = new LoggerFactory(
                    $config->get('APP_ENV', 'production'),
                    $config->get('LOG_CHANNEL', 'stack'),
                    $this->basePath . '/storage/logs'
                );

                return $loggerFactory->create('quiz-bot', new StreamHandler(
                    $this->basePath . '/storage/logs/app.log'
                ));
            },
            Capsule::class => function (Container $c) {
                /** @var Config $config */
                $config = $c->get(Config::class);

                $capsule = new Capsule();
                $driver = $config->get('DB_CONNECTION', 'mysql');
                
                if ($driver === 'sqlite') {
                    $database = $config->get('DB_DATABASE', 'database.sqlite');
                    // Если путь относительный, делаем его абсолютным относительно basePath
                    if (substr($database, 0, 1) !== '/') {
                        $database = $this->basePath . '/storage/database/' . $database;
                    }
                    // Создаём директорию, если её нет
                    $dbDir = dirname($database);
                    if (!is_dir($dbDir)) {
                        mkdir($dbDir, 0755, true);
                    }
                    
                    $capsule->addConnection([
                        'driver' => 'sqlite',
                        'database' => $database,
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ]);
                } else {
                    $capsule->addConnection([
                        'driver' => $driver,
                        'host' => $config->get('DB_HOST', '127.0.0.1'),
                        'port' => (int) $config->get('DB_PORT', 3306),
                        'database' => $config->get('DB_DATABASE', 'quiz_bot'),
                        'username' => $config->get('DB_USERNAME', 'root'),
                        'password' => $config->get('DB_PASSWORD', ''),
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                    ]);
                }

                $capsule->setAsGlobal();
                $capsule->bootEloquent();

                return $capsule;
            },
            CacheFactory::class => function (Container $c) {
                /** @var Config $config */
                $config = $c->get(Config::class);

                return new CacheFactory($config->get('CACHE_DRIVER', 'array'), $this->basePath . '/storage/cache');
            },
            TelegramClientFactory::class => function (Container $c) {
                /** @var Config $config */
                $config = $c->get(Config::class);

                return new TelegramClientFactory($config->get('TELEGRAM_BOT_TOKEN'));
            },
            QuestionSelector::class => function (Container $c) {
                return new QuestionSelector($c->get(Logger::class));
            },
            UserService::class => function (Container $c) {
                return new UserService($c->get(Logger::class));
            },
            DuelService::class => function (Container $c) {
                return new DuelService(
                    $c->get(Logger::class),
                    $c->get(QuestionSelector::class),
                    $c->get(ReferralService::class)
                );
            },
            GameSessionService::class => function (Container $c) {
                return new GameSessionService(
                    $c->get(Logger::class),
                    $c->get(QuestionSelector::class),
                    $c->get(UserService::class),
                    $c->get(ReferralService::class)
                );
            },
            StoryService::class => function (Container $c) {
                return new StoryService(
                    $c->get(Logger::class)
                );
            },
            HintService::class => function (Container $c) {
                return new HintService(
                    $c->get(Logger::class),
                    $c->get(UserService::class),
                    $c->get(GameSessionService::class)
                );
            },
            TrueFalseService::class => function (Container $c) {
                return new TrueFalseService(
                    $c->get(CacheFactory::class)->create(),
                    $c->get(Logger::class),
                    $c->get(UserService::class)
                );
            },
            StatisticsService::class => function (Container $c) {
                return new StatisticsService(
                    $c->get(Logger::class)
                );
            },
            ReferralService::class => function (Container $c) {
                return new ReferralService(
                    $c->get(Logger::class),
                    $c->get(UserService::class)
                );
            },
            MessageFormatter::class => function (Container $c) {
                return new \QuizBot\Application\Services\MessageFormatter();
            },
            ProfileFormatter::class => function (Container $c) {
                return new ProfileFormatter(
                    $c->get(UserService::class),
                    $c->get(\QuizBot\Application\Services\MessageFormatter::class)
                );
            },
            \QuizBot\Application\Services\AdminService::class => function (Container $c) {
                return new \QuizBot\Application\Services\AdminService(
                    $c->get(Config::class),
                    $c->get(Logger::class),
                    $c->get(DuelService::class),
                    $c->get(TelegramClientFactory::class)->create()
                );
            },
            SampleDataSeeder::class => function (Container $c) {
                return new SampleDataSeeder();
            },
            WebhookHandler::class => function (Container $c) {
                return new WebhookHandler(
                    $c->get(Config::class),
                    $c->get(Logger::class),
                    $c->get(TelegramClientFactory::class),
                    $c->get(CacheFactory::class),
                    $c->get(UserService::class),
                    $c->get(DuelService::class),
                    $c->get(GameSessionService::class),
                    $c->get(StoryService::class),
                    $c->get(ProfileFormatter::class),
                    $c->get(MessageFormatter::class),
                    $c
                );
            },
            MigrationRunner::class => function (Container $c) {
                return new MigrationRunner(
                    $c->get(Capsule::class),
                    $c->get(Logger::class)
                );
            },
        ]);

        return $builder->build();
    }

    private function bootstrapDatabase(): void
    {
        $this->container->get(Capsule::class);
    }
}

