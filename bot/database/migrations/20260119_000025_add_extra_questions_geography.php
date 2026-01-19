<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000025_add_extra_questions_geography';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'География')->first();
        
        if (!$category) {
            echo "Category 'География' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Какой океан омывает Антарктиду?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Южный (или все три)', 'is_correct' => true],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                    ['text' => 'Только Тихий', 'is_correct' => false],
                    ['text' => 'Только Атлантический', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Испании?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мадрид', 'is_correct' => true],
                    ['text' => 'Барселона', 'is_correct' => false],
                    ['text' => 'Севилья', 'is_correct' => false],
                    ['text' => 'Валенсия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится пустыня Гоби?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Монголия и Китай', 'is_correct' => true],
                    ['text' => 'Египет', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая река протекает через Лондон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Темза', 'is_correct' => true],
                    ['text' => 'Сена', 'is_correct' => false],
                    ['text' => 'Дунай', 'is_correct' => false],
                    ['text' => 'Рейн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна самая большая по площади?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Россия', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Эйфелева башня?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Париж', 'is_correct' => true],
                    ['text' => 'Лондон', 'is_correct' => false],
                    ['text' => 'Рим', 'is_correct' => false],
                    ['text' => 'Берлин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой континент самый жаркий?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Африка', 'is_correct' => true],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Южная Америка', 'is_correct' => false],
                    ['text' => 'Азия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Японии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Токио', 'is_correct' => true],
                    ['text' => 'Киото', 'is_correct' => false],
                    ['text' => 'Осака', 'is_correct' => false],
                    ['text' => 'Сеул', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находятся Пирамиды Гизы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Египет', 'is_correct' => true],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Перу', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой океан самый большой?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Тихий океан', 'is_correct' => true],
                    ['text' => 'Атлантический океан', 'is_correct' => false],
                    ['text' => 'Индийский океан', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый океан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Италии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рим', 'is_correct' => true],
                    ['text' => 'Милан', 'is_correct' => false],
                    ['text' => 'Венеция', 'is_correct' => false],
                    ['text' => 'Неаполь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Статуя Свободы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нью-Йорк', 'is_correct' => true],
                    ['text' => 'Вашингтон', 'is_correct' => false],
                    ['text' => 'Лос-Анджелес', 'is_correct' => false],
                    ['text' => 'Чикаго', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая гора самая высокая в мире (над уровнем моря)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Эверест', 'is_correct' => true],
                    ['text' => 'К2', 'is_correct' => false],
                    ['text' => 'Килиманджаро', 'is_correct' => false],
                    ['text' => 'Монблан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Германии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Берлин', 'is_correct' => true],
                    ['text' => 'Мюнхен', 'is_correct' => false],
                    ['text' => 'Гамбург', 'is_correct' => false],
                    ['text' => 'Франкфурт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Тадж-Махал?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Индия', 'is_correct' => true],
                    ['text' => 'Турция', 'is_correct' => false],
                    ['text' => 'Иран', 'is_correct' => false],
                    ['text' => 'Пакистан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет форму сапога?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Греция', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой материк покрыт льдом?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Антарктида', 'is_correct' => true],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Евразия', 'is_correct' => false],
                    ['text' => 'Африка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Китая?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пекин', 'is_correct' => true],
                    ['text' => 'Шанхай', 'is_correct' => false],
                    ['text' => 'Гонконг', 'is_correct' => false],
                    ['text' => 'Ухань', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Гранд-Каньон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое озеро самое глубокое в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Байкал', 'is_correct' => true],
                    ['text' => 'Виктория', 'is_correct' => false],
                    ['text' => 'Мичиган', 'is_correct' => false],
                    ['text' => 'Танганьика', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Турции?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Анкара', 'is_correct' => true],
                    ['text' => 'Стамбул', 'is_correct' => false],
                    ['text' => 'Анталья', 'is_correct' => false],
                    ['text' => 'Измир', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Колизей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рим (Италия)', 'is_correct' => true],
                    ['text' => 'Афины (Греция)', 'is_correct' => false],
                    ['text' => 'Париж (Франция)', 'is_correct' => false],
                    ['text' => 'Мадрид (Испания)', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна производит больше всего кофе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бразилия', 'is_correct' => true],
                    ['text' => 'Колумбия', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                    ['text' => 'Эфиопия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Египта?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Каир', 'is_correct' => true],
                    ['text' => 'Александрия', 'is_correct' => false],
                    ['text' => 'Луксор', 'is_correct' => false],
                    ['text' => 'Шарм-эль-Шейх', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Мачу-Пикчу?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Перу', 'is_correct' => true],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Чили', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое море самое соленое?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мертвое море', 'is_correct' => true],
                    ['text' => 'Красное море', 'is_correct' => false],
                    ['text' => 'Черное море', 'is_correct' => false],
                    ['text' => 'Средиземное море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Австралии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Канберра', 'is_correct' => true],
                    ['text' => 'Сидней', 'is_correct' => false],
                    ['text' => 'Мельбурн', 'is_correct' => false],
                    ['text' => 'Брисбен', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Ниагарский водопад?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США и Канада', 'is_correct' => true],
                    ['text' => 'Бразилия и Аргентина', 'is_correct' => false],
                    ['text' => 'Венесуэла', 'is_correct' => false],
                    ['text' => 'ЮАР', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой остров самый большой в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гренландия', 'is_correct' => true],
                    ['text' => 'Мадагаскар', 'is_correct' => false],
                    ['text' => 'Австралия (материк)', 'is_correct' => false],
                    ['text' => 'Великобритания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Канады?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Оттава', 'is_correct' => true],
                    ['text' => 'Торонто', 'is_correct' => false],
                    ['text' => 'Монреаль', 'is_correct' => false],
                    ['text' => 'Ванкувер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Кремль?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Москва', 'is_correct' => true],
                    ['text' => 'Санкт-Петербург', 'is_correct' => false],
                    ['text' => 'Казань', 'is_correct' => false],
                    ['text' => 'Новгород', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая река самая длинная в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Амазонка', 'is_correct' => true],
                    ['text' => 'Нил', 'is_correct' => false],
                    ['text' => 'Янцзы', 'is_correct' => false],
                    ['text' => 'Миссисипи', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Бразилии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бразилиа', 'is_correct' => true],
                    ['text' => 'Рио-де-Жанейро', 'is_correct' => false],
                    ['text' => 'Сан-Паулу', 'is_correct' => false],
                    ['text' => 'Сальвадор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Голливуд?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лос-Анджелес', 'is_correct' => true],
                    ['text' => 'Нью-Йорк', 'is_correct' => false],
                    ['text' => 'Майами', 'is_correct' => false],
                    ['text' => 'Сан-Франциско', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна находится и в Европе, и в Азии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Россия (и Турция)', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Индии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Нью-Дели', 'is_correct' => true],
                    ['text' => 'Мумбаи', 'is_correct' => false],
                    ['text' => 'Калькутта', 'is_correct' => false],
                    ['text' => 'Бангалор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Лувр?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Париж', 'is_correct' => true],
                    ['text' => 'Лондон', 'is_correct' => false],
                    ['text' => 'Рим', 'is_correct' => false],
                    ['text' => 'Мадрид', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город называют "Столицей мира"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Нью-Йорк', 'is_correct' => true],
                    ['text' => 'Лондон', 'is_correct' => false],
                    ['text' => 'Париж', 'is_correct' => false],
                    ['text' => 'Токио', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна славится своими тюльпанами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нидерланды (Голландия)', 'is_correct' => true],
                    ['text' => 'Бельгия', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Аргентины?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Буэнос-Айрес', 'is_correct' => true],
                    ['text' => 'Сантьяго', 'is_correct' => false],
                    ['text' => 'Лима', 'is_correct' => false],
                    ['text' => 'Богота', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Великая Китайская стена?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Китай', 'is_correct' => true],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Монголия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна самая маленькая в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ватикан', 'is_correct' => true],
                    ['text' => 'Монако', 'is_correct' => false],
                    ['text' => 'Сан-Марино', 'is_correct' => false],
                    ['text' => 'Лихтенштейн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Южной Кореи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сеул', 'is_correct' => true],
                    ['text' => 'Пхеньян', 'is_correct' => false],
                    ['text' => 'Пусан', 'is_correct' => false],
                    ['text' => 'Токио', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Биг-Бен?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лондон', 'is_correct' => true],
                    ['text' => 'Нью-Йорк', 'is_correct' => false],
                    ['text' => 'Сидней', 'is_correct' => false],
                    ['text' => 'Париж', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой океан омывает Индию?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Индийский океан', 'is_correct' => true],
                    ['text' => 'Тихий океан', 'is_correct' => false],
                    ['text' => 'Атлантический океан', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый океан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Мексики?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мехико', 'is_correct' => true],
                    ['text' => 'Канкун', 'is_correct' => false],
                    ['text' => 'Гвадалахара', 'is_correct' => false],
                    ['text' => 'Монтеррей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Красная площадь?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Москва', 'is_correct' => true],
                    ['text' => 'Киев', 'is_correct' => false],
                    ['text' => 'Минск', 'is_correct' => false],
                    ['text' => 'Варшава', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой континент самый населенный?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Азия', 'is_correct' => true],
                    ['text' => 'Африка', 'is_correct' => false],
                    ['text' => 'Европа', 'is_correct' => false],
                    ['text' => 'Северная Америка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая столица у Польши?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Варшава', 'is_correct' => true],
                    ['text' => 'Краков', 'is_correct' => false],
                    ['text' => 'Гданьск', 'is_correct' => false],
                    ['text' => 'Прага', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Амазонка (река и джунгли)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Южная Америка', 'is_correct' => true],
                    ['text' => 'Африка', 'is_correct' => false],
                    ['text' => 'Азия', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                ]
            ]
        ];

        foreach ($questions as $q) {
            $db->table('questions')->insert([
                'category_id' => $categoryId,
                'question_text' => $q['question_text'],
                'difficulty' => $q['difficulty'],
                'time_limit' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $questionId = $db->getPdo()->lastInsertId();

            foreach ($q['answers'] as $a) {
                $db->table('answers')->insert([
                    'question_id' => $questionId,
                    'answer_text' => $a['text'],
                    'is_correct' => $a['is_correct'],
                ]);
            }
        }
    }

    public function down(Builder $schema): void
    {
    }
};
