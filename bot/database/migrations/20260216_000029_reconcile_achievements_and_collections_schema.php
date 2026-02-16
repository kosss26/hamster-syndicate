<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260216_000029_reconcile_achievements_and_collections_schema';
    }

    public function up(Builder $schema): void
    {
        $this->ensureAchievementsTable($schema);
        $this->ensureUserAchievementsTable($schema);
        $this->ensureCollectionsTable($schema);
        $this->ensureCollectionItemsTable($schema);
        $this->ensureUserCollectionItemsTable($schema);
        $this->ensureAchievementStatsTable($schema);
    }

    private function ensureAchievementsTable(Builder $schema): void
    {
        if (!$schema->hasTable('achievements')) {
            $schema->create('achievements', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('title');
                $table->text('description');
                $table->string('icon', 50);
                $table->string('rarity', 20)->default('common');
                $table->string('category', 50)->default('special');
                $table->string('condition_type', 50)->default('counter');
                $table->integer('condition_value')->default(1);
                $table->integer('reward_coins')->default(0);
                $table->integer('reward_gems')->default(0);
                $table->boolean('is_secret')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
            return;
        }

        $schema->table('achievements', function (Blueprint $table) use ($schema): void {
            if (!$schema->hasColumn('achievements', 'key')) {
                $table->string('key', 100)->nullable();
            }
            if (!$schema->hasColumn('achievements', 'title')) {
                $table->string('title')->default('Достижение');
            }
            if (!$schema->hasColumn('achievements', 'description')) {
                $table->text('description')->nullable();
            }
            if (!$schema->hasColumn('achievements', 'icon')) {
                $table->string('icon', 50)->default('🏅');
            }
            if (!$schema->hasColumn('achievements', 'rarity')) {
                $table->string('rarity', 20)->default('common');
            }
            if (!$schema->hasColumn('achievements', 'category')) {
                $table->string('category', 50)->default('special');
            }
            if (!$schema->hasColumn('achievements', 'condition_type')) {
                $table->string('condition_type', 50)->default('counter');
            }
            if (!$schema->hasColumn('achievements', 'condition_value')) {
                $table->integer('condition_value')->default(1);
            }
            if (!$schema->hasColumn('achievements', 'reward_coins')) {
                $table->integer('reward_coins')->default(0);
            }
            if (!$schema->hasColumn('achievements', 'reward_gems')) {
                $table->integer('reward_gems')->default(0);
            }
            if (!$schema->hasColumn('achievements', 'is_secret')) {
                $table->boolean('is_secret')->default(false);
            }
            if (!$schema->hasColumn('achievements', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
            if (!$schema->hasColumn('achievements', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
        });

        if ($schema->hasColumn('achievements', 'code') && $schema->hasColumn('achievements', 'key')) {
            Capsule::statement("
                UPDATE achievements
                SET key = code
                WHERE (key IS NULL OR key = '') AND code IS NOT NULL AND code <> ''
            ");
        }

        if ($schema->hasColumn('achievements', 'points') && $schema->hasColumn('achievements', 'reward_coins')) {
            Capsule::statement("
                UPDATE achievements
                SET reward_coins = points
                WHERE (reward_coins IS NULL OR reward_coins = 0) AND points IS NOT NULL
            ");
        }

        if ($schema->hasColumn('achievements', 'key')) {
            Capsule::table('achievements')
                ->where(function ($q): void {
                    $q->whereNull('key')->orWhere('key', '');
                })
                ->select(['id'])
                ->orderBy('id')
                ->chunk(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $id = (int) ($row->id ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        Capsule::table('achievements')
                            ->where('id', $id)
                            ->update(['key' => 'achievement_' . $id]);
                    }
                });
        }
    }

    private function ensureUserAchievementsTable(Builder $schema): void
    {
        if (!$schema->hasTable('user_achievements')) {
            $schema->create('user_achievements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('achievement_id');
                $table->integer('current_value')->default(0);
                $table->boolean('is_completed')->default(false);
                $table->timestamp('completed_at')->nullable();
                $table->boolean('is_showcased')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->unique(['user_id', 'achievement_id']);
            });
            return;
        }

        $schema->table('user_achievements', function (Blueprint $table) use ($schema): void {
            if (!$schema->hasColumn('user_achievements', 'current_value')) {
                $table->integer('current_value')->default(0);
            }
            if (!$schema->hasColumn('user_achievements', 'is_completed')) {
                $table->boolean('is_completed')->default(false);
            }
            if (!$schema->hasColumn('user_achievements', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (!$schema->hasColumn('user_achievements', 'is_showcased')) {
                $table->boolean('is_showcased')->default(false);
            }
            if (!$schema->hasColumn('user_achievements', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!$schema->hasColumn('user_achievements', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        if ($schema->hasColumn('user_achievements', 'unlocked_at') && $schema->hasColumn('user_achievements', 'completed_at')) {
            Capsule::statement("
                UPDATE user_achievements
                SET completed_at = unlocked_at
                WHERE completed_at IS NULL AND unlocked_at IS NOT NULL
            ");
        }

        if ($schema->hasColumn('user_achievements', 'is_completed')) {
            Capsule::statement("
                UPDATE user_achievements
                SET is_completed = CASE WHEN completed_at IS NOT NULL THEN 1 ELSE is_completed END
                WHERE completed_at IS NOT NULL
            ");
        }

        if ($schema->hasColumn('user_achievements', 'current_value') && $schema->hasColumn('user_achievements', 'is_completed')) {
            Capsule::statement("
                UPDATE user_achievements
                SET current_value = COALESCE(
                    (SELECT condition_value FROM achievements WHERE achievements.id = user_achievements.achievement_id),
                    current_value
                )
                WHERE is_completed = 1 AND (current_value IS NULL OR current_value = 0)
            ");
        }

        if ($schema->hasColumn('user_achievements', 'created_at')) {
            Capsule::statement("
                UPDATE user_achievements
                SET created_at = COALESCE(created_at, CURRENT_TIMESTAMP)
            ");
        }

        if ($schema->hasColumn('user_achievements', 'updated_at')) {
            Capsule::statement("
                UPDATE user_achievements
                SET updated_at = COALESCE(updated_at, CURRENT_TIMESTAMP)
            ");
        }
    }

    private function ensureCollectionsTable(Builder $schema): void
    {
        if (!$schema->hasTable('collections')) {
            $schema->create('collections', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('title');
                $table->text('description');
                $table->string('icon', 50);
                $table->integer('total_items')->default(0);
                $table->string('rarity', 20)->default('common');
                $table->integer('reward_coins')->default(0);
                $table->integer('reward_gems')->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
            return;
        }

        $schema->table('collections', function (Blueprint $table) use ($schema): void {
            if (!$schema->hasColumn('collections', 'key')) {
                $table->string('key', 100)->nullable();
            }
            if (!$schema->hasColumn('collections', 'title')) {
                $table->string('title')->default('Коллекция');
            }
            if (!$schema->hasColumn('collections', 'description')) {
                $table->text('description')->nullable();
            }
            if (!$schema->hasColumn('collections', 'icon')) {
                $table->string('icon', 50)->default('📚');
            }
            if (!$schema->hasColumn('collections', 'total_items')) {
                $table->integer('total_items')->default(0);
            }
            if (!$schema->hasColumn('collections', 'rarity')) {
                $table->string('rarity', 20)->default('common');
            }
            if (!$schema->hasColumn('collections', 'reward_coins')) {
                $table->integer('reward_coins')->default(0);
            }
            if (!$schema->hasColumn('collections', 'reward_gems')) {
                $table->integer('reward_gems')->default(0);
            }
            if (!$schema->hasColumn('collections', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
        });

        if ($schema->hasColumn('collections', 'key')) {
            Capsule::table('collections')
                ->where(function ($q): void {
                    $q->whereNull('key')->orWhere('key', '');
                })
                ->select(['id'])
                ->orderBy('id')
                ->chunk(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $id = (int) ($row->id ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        Capsule::table('collections')
                            ->where('id', $id)
                            ->update(['key' => 'collection_' . $id]);
                    }
                });
        }
    }

    private function ensureCollectionItemsTable(Builder $schema): void
    {
        if (!$schema->hasTable('collection_items')) {
            $schema->create('collection_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('collection_id');
                $table->string('key', 100)->unique();
                $table->string('name');
                $table->text('description');
                $table->string('image_url')->nullable();
                $table->string('rarity', 20)->default('common');
                $table->decimal('drop_chance', 5, 4)->default(0.1);
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->index('collection_id');
            });
            return;
        }

        $schema->table('collection_items', function (Blueprint $table) use ($schema): void {
            if (!$schema->hasColumn('collection_items', 'collection_id')) {
                $table->unsignedBigInteger('collection_id')->default(0);
            }
            if (!$schema->hasColumn('collection_items', 'key')) {
                $table->string('key', 100)->nullable();
            }
            if (!$schema->hasColumn('collection_items', 'name')) {
                $table->string('name')->default('Карточка');
            }
            if (!$schema->hasColumn('collection_items', 'description')) {
                $table->text('description')->nullable();
            }
            if (!$schema->hasColumn('collection_items', 'image_url')) {
                $table->string('image_url')->nullable();
            }
            if (!$schema->hasColumn('collection_items', 'rarity')) {
                $table->string('rarity', 20)->default('common');
            }
            if (!$schema->hasColumn('collection_items', 'drop_chance')) {
                $table->decimal('drop_chance', 5, 4)->default(0.1);
            }
            if (!$schema->hasColumn('collection_items', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
            if (!$schema->hasColumn('collection_items', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
        });

        if ($schema->hasColumn('collection_items', 'key')) {
            Capsule::table('collection_items')
                ->where(function ($q): void {
                    $q->whereNull('key')->orWhere('key', '');
                })
                ->select(['id'])
                ->orderBy('id')
                ->chunk(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $id = (int) ($row->id ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        Capsule::table('collection_items')
                            ->where('id', $id)
                            ->update(['key' => 'collection_item_' . $id]);
                    }
                });
        }
    }

    private function ensureUserCollectionItemsTable(Builder $schema): void
    {
        if (!$schema->hasTable('user_collection_items')) {
            $schema->create('user_collection_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('collection_item_id');
                $table->timestamp('obtained_at')->useCurrent();
                $table->string('obtained_from', 50)->nullable();
                $table->unique(['user_id', 'collection_item_id']);
                $table->index('user_id');
            });
            return;
        }

        $schema->table('user_collection_items', function (Blueprint $table) use ($schema): void {
            if (!$schema->hasColumn('user_collection_items', 'user_id')) {
                $table->unsignedBigInteger('user_id')->default(0);
            }
            if (!$schema->hasColumn('user_collection_items', 'collection_item_id')) {
                $table->unsignedBigInteger('collection_item_id')->default(0);
            }
            if (!$schema->hasColumn('user_collection_items', 'obtained_at')) {
                $table->timestamp('obtained_at')->nullable();
            }
            if (!$schema->hasColumn('user_collection_items', 'obtained_from')) {
                $table->string('obtained_from', 50)->nullable();
            }
        });
    }

    private function ensureAchievementStatsTable(Builder $schema): void
    {
        if ($schema->hasTable('achievement_stats')) {
            return;
        }

        $schema->create('achievement_stats', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('stat_key', 100);
            $table->integer('stat_value')->default(0);
            $table->timestamp('last_updated')->useCurrent();
            $table->unique(['user_id', 'stat_key']);
            $table->index('user_id');
            $table->index('stat_key');
        });
    }
};
