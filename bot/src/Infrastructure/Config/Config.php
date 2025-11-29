<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Config;

use Dotenv\Dotenv;

final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(private array $values)
    {
    }

    public static function fromEnv(string $configPath): self
    {
        if (is_file($configPath . '/app.env')) {
            $values = parse_ini_file($configPath . '/app.env', false, INI_SCANNER_TYPED);
        } else {
            Dotenv::createImmutable($configPath)->safeLoad();
            $values = $_ENV;
        }

        return new self($values ?: []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

