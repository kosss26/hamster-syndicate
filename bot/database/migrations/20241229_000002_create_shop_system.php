<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Миграция для системы магазина, колеса фортуны и лутбоксов
 */
return new class {
    public function up(): void
    {
        $schema = DB::schema();
        
        // 1. Обновляем user_profiles - добавляем новые поля
        $schema->table('user_profiles', function ($table) {
            $table->integer('gems')->default(0); // Кристаллы (премиум валюта)
            $table->integer('hints')->default(3); // Подсказки
            $table->integer('lives')->default(3); // Жизни
            $table->timestamp('last_wheel_spin')->nullable(); // Последнее вращение колеса
            $table->integer('wheel_streak')->default(0); // Дней подряд крутил колесо
            $table->string('equipped_frame', 100)->nullable(); // Экипированная рамка профиля
            $table->string('equipped_emoji', 50)->nullable(); // Экипированный эмодзи
        });
        
        // 2. Товары магазина
        $schema->create('shop_items', function ($table) {
            $table->increments('id');
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
        $schema->create('user_inventory', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('item_type', 50); // 'hint', 'life', 'boost', 'cosmetic'
            $table->integer('item_id')->unsigned()->nullable(); // ID из shop_items
            $table->string('item_key', 100)->nullable(); // Уникальный ключ предмета
            $table->integer('quantity')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('acquired_at');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'item_type']);
        });
        
        // 4. Активные бусты
        $schema->create('user_boosts', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('boost_type', 50); // 'exp_boost', 'coin_boost'
            $table->decimal('multiplier', 3, 2)->default(1.50); // 1.5 = +50%
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'boost_type', 'expires_at']);
        });
        
        // 5. История вращений колеса фортуны
        $schema->create('fortune_wheel_spins', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('reward_type', 50); // 'coins', 'exp', 'hint', 'lootbox', 'gems'
            $table->integer('reward_amount');
            $table->boolean('is_paid')->default(false); // Бесплатное или за кристаллы
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });
        
        // 6. История покупок в магазине
        $schema->create('shop_purchases', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('item_id')->unsigned();
            $table->integer('quantity')->default(1);
            $table->integer('price_coins')->default(0);
            $table->integer('price_gems')->default(0);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('shop_items')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });
        
        // 7. История открытых лутбоксов
        $schema->create('lootbox_openings', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('lootbox_type', 50); // 'bronze', 'silver', 'gold', 'legendary'
            $table->json('rewards'); // [{type: 'coins', amount: 500}, ...]
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });
        
        // 8. Косметика пользователей
        $schema->create('user_cosmetics', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('cosmetic_type', 50); // 'frame', 'emoji'
            $table->string('cosmetic_id', 100); // Уникальный ID косметики
            $table->string('rarity', 20)->default('common');
            $table->boolean('is_equipped')->default(false);
            $table->timestamp('acquired_at');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'cosmetic_type']);
            $table->unique(['user_id', 'cosmetic_id']);
        });
    }
    
    public function down(): void
    {
        $schema = DB::schema();
        
        $schema->dropIfExists('user_cosmetics');
        $schema->dropIfExists('lootbox_openings');
        $schema->dropIfExists('shop_purchases');
        $schema->dropIfExists('fortune_wheel_spins');
        $schema->dropIfExists('user_boosts');
        $schema->dropIfExists('user_inventory');
        $schema->dropIfExists('shop_items');
        
        $schema->table('user_profiles', function ($table) {
            $table->dropColumn([
                'gems',
                'hints',
                'lives',
                'last_wheel_spin',
                'wheel_streak',
                'equipped_frame',
                'equipped_emoji'
            ]);
        });
    }
};

