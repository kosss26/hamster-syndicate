<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241229_000002_create_shop_system';
    }

    public function up(Builder $schema): void
    {
        // 1. Обновляем user_profiles - добавляем новые поля
        $schema->table('user_profiles', function (Blueprint $table): void {
            $table->integer('gems')->default(0)->after('coins'); // Кристаллы (премиум валюта)
            $table->integer('hints')->default(3)->after('gems'); // Подсказки
            $table->timestamp('last_wheel_spin')->nullable()->after('wheel_streak'); // Последнее вращение колеса
            $table->integer('wheel_streak')->default(0)->after('last_wheel_spin'); // Дней подряд крутил колесо
            $table->string('equipped_frame', 100)->nullable()->after('wheel_streak'); // Экипированная рамка профиля
            $table->string('equipped_emoji', 50)->nullable()->after('equipped_frame'); // Экипированный эмодзи
        });
        
        // 2. Товары магазина
        $schema->create('shop_items', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50); // 'hint', 'life', 'boost', 'cosmetic', 'lootbox'
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50);
            $table->string('rarity', 20)->default('common'); // common, rare, epic, legendary
            $table->integer('price_coins')->default(0);
            $table->integer('price_gems')->default(0);
            $table->json('metadata')->nullable(); // Дополнительные данные (duration для бустов и т.д.)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_active');
        });
        
        // 3. Инвентарь игрока
        $schema->create('user_inventory', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('item_type', 50); // 'hint', 'life', 'boost', 'cosmetic'
            $table->unsignedBigInteger('item_id')->nullable(); // ID из shop_items
            $table->string('item_key', 100)->nullable(); // Уникальный ключ предмета
            $table->integer('quantity')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('acquired_at')->useCurrent();
            
            $table->index(['user_id', 'item_type']);
        });
        
        // 4. Активные бусты
        $schema->create('user_boosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('boost_type', 50); // 'exp_boost', 'coin_boost'
            $table->decimal('multiplier', 3, 2)->default(1.50); // 1.5 = +50%
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['user_id', 'boost_type', 'expires_at']);
        });
        
        // 5. История вращений колеса фортуны
        $schema->create('fortune_wheel_spins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reward_type', 50); // 'coins', 'exp', 'hint', 'lootbox', 'gems'
            $table->integer('reward_amount');
            $table->boolean('is_paid')->default(false); // Бесплатное или за кристаллы
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });
        
        // 6. История покупок в магазине
        $schema->create('shop_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->references('id')->on('shop_items')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->integer('price_coins')->default(0);
            $table->integer('price_gems')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });
        
        // 7. История открытых лутбоксов
        $schema->create('lootbox_openings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('lootbox_type', 50); // 'bronze', 'silver', 'gold', 'legendary'
            $table->json('rewards'); // [{type: 'coins', amount: 500}, ...]
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });
        
        // 8. Косметика пользователей
        $schema->create('user_cosmetics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('cosmetic_type', 50); // 'frame', 'emoji'
            $table->string('cosmetic_id', 100); // Уникальный ID косметики
            $table->string('rarity', 20)->default('common');
            $table->boolean('is_equipped')->default(false);
            $table->timestamp('acquired_at')->useCurrent();
            
            $table->index(['user_id', 'cosmetic_type']);
            $table->unique(['user_id', 'cosmetic_id']);
        });
    }
    
    public function down(Builder $schema): void
    {
        $schema->dropIfExists('user_cosmetics');
        $schema->dropIfExists('lootbox_openings');
        $schema->dropIfExists('shop_purchases');
        $schema->dropIfExists('fortune_wheel_spins');
        $schema->dropIfExists('user_boosts');
        $schema->dropIfExists('user_inventory');
        $schema->dropIfExists('shop_items');
        
        $schema->table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'gems',
                'hints',
                'last_wheel_spin',
                'wheel_streak',
                'equipped_frame',
                'equipped_emoji'
            ]);
        });
    }
};
