<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Collection;
use Monolog\Logger;

final class MigrationRunner
{
    private Capsule $capsule;

    private Logger $logger;

    public function __construct(Capsule $capsule, Logger $logger)
    {
        $this->capsule = $capsule;
        $this->logger = $logger;
    }

    public function run(string $migrationsPath): void
    {
        $schema = $this->capsule->getDatabaseManager()->getSchemaBuilder();
        $this->ensureMigrationsTable($schema);

        $applied = $this->getAppliedMigrations();
        $migrationFiles = glob($migrationsPath . '/*.php') ?: [];
        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $migration = $this->resolveMigration($file);

            if ($applied->contains($migration->name())) {
                $this->logger->debug(sprintf('Миграция %s уже применена, пропуск', $migration->name()));
                continue;
            }

            $this->logger->info(sprintf('Применение миграции %s', $migration->name()));
            $this->capsule->getConnection()->transaction(function () use ($migration, $schema): void {
                $migration->up($schema);
            });

            $this->storeMigration($migration->name());
        }
    }

    private function ensureMigrationsTable(Builder $schema): void
    {
        if ($schema->hasTable('migrations')) {
            return;
        }

        $schema->create('migrations', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('migration', 191)->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * @return Collection<int, string>
     */
    private function getAppliedMigrations(): Collection
    {
        return $this->capsule->getConnection()
            ->table('migrations')
            ->orderBy('id')
            ->pluck('migration');
    }

    private function storeMigration(string $name): void
    {
        $this->capsule->getConnection()->table('migrations')->insert([
            'migration' => $name,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveMigration(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException(sprintf('Файл %s не вернул экземпляр Migration.', $file));
        }

        return $migration;
    }
}

