<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000005_add_geography_questions_part1';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        // Получаем ID категории динамически по названию
        $category = $db->table('categories')->where('title', 'География')->first();
        
        if (!$category) {
            // Если категория не найдена, пропускаем миграцию или выводим ошибку
            // В данном случае просто вернемся, чтобы не ломать процесс
            echo "Category 'География' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Какая река является самой длинной в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Амазонка', 'is_correct' => true],
                    ['text' => 'Нил', 'is_correct' => false],
                    ['text' => 'Янцзы', 'is_correct' => false],
                    ['text' => 'Миссисипи', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является город Канберра?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Австралия', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Новая Зеландия', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится пустыня Атакама?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Чили', 'is_correct' => true],
                    ['text' => 'Аргентина', 'is_correct' => false],
                    ['text' => 'Перу', 'is_correct' => false],
                    ['text' => 'Боливия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой океан омывает восточное побережье Африки?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Индийский', 'is_correct' => true],
                    ['text' => 'Атлантический', 'is_correct' => false],
                    ['text' => 'Тихий', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство является самым маленьким в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ватикан', 'is_correct' => true],
                    ['text' => 'Монако', 'is_correct' => false],
                    ['text' => 'Сан-Марино', 'is_correct' => false],
                    ['text' => 'Лихтенштейн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'На каком континенте находится самая низкая точка суши — побережье Мертвого моря?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Евразия', 'is_correct' => true],
                    ['text' => 'Африка', 'is_correct' => false],
                    ['text' => 'Северная Америка', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна занимает весь континент?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Австралия', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находятся водопады Игуасу?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'На границе Аргентины и Бразилии', 'is_correct' => true],
                    ['text' => 'В Венесуэле', 'is_correct' => false],
                    ['text' => 'На границе США и Канады', 'is_correct' => false],
                    ['text' => 'В ЮАР', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна известна как «Страна восходящего солнца»?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Южная Корея', 'is_correct' => false],
                    ['text' => 'Таиланд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе находится знаменитый Колизей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рим', 'is_correct' => true],
                    ['text' => 'Афины', 'is_correct' => false],
                    ['text' => 'Милан', 'is_correct' => false],
                    ['text' => 'Неаполь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое озеро является самым глубоким в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Байкал', 'is_correct' => true],
                    ['text' => 'Танганьика', 'is_correct' => false],
                    ['text' => 'Верхнее', 'is_correct' => false],
                    ['text' => 'Виктория', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Назовите столицу Канады.',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Оттава', 'is_correct' => true],
                    ['text' => 'Торонто', 'is_correct' => false],
                    ['text' => 'Монреаль', 'is_correct' => false],
                    ['text' => 'Ванкувер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет наибольшее население в мире (на 2023 год)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Индия', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Индонезия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой пролив разделяет Африку и Европу?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гибралтарский', 'is_correct' => true],
                    ['text' => 'Берингов', 'is_correct' => false],
                    ['text' => 'Магелланов', 'is_correct' => false],
                    ['text' => 'Босфор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Мачу-Пикчу?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Перу', 'is_correct' => true],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Чили', 'is_correct' => false],
                    ['text' => 'Колумбия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая гора является самой высокой в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Эверест', 'is_correct' => true],
                    ['text' => 'К2', 'is_correct' => false],
                    ['text' => 'Килиманджаро', 'is_correct' => false],
                    ['text' => 'Эльбрус', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое море самое соленое?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Мертвое море', 'is_correct' => true],
                    ['text' => 'Красное море', 'is_correct' => false],
                    ['text' => 'Средиземное море', 'is_correct' => false],
                    ['text' => 'Черное море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город называют «Вечным городом»?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рим', 'is_correct' => true],
                    ['text' => 'Иерусалим', 'is_correct' => false],
                    ['text' => 'Афины', 'is_correct' => false],
                    ['text' => 'Константинополь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится статуя Христа-Искупителя?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рио-де-Жанейро', 'is_correct' => true],
                    ['text' => 'Сан-Паулу', 'is_correct' => false],
                    ['text' => 'Буэнос-Айрес', 'is_correct' => false],
                    ['text' => 'Лима', 'is_correct' => false],
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
                'question_text' => 'Какой остров является самым большим в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гренландия', 'is_correct' => true],
                    ['text' => 'Мадагаскар', 'is_correct' => false],
                    ['text' => 'Новая Гвинея', 'is_correct' => false],
                    ['text' => 'Борнео', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столица какой страны — Ханой?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Вьетнам', 'is_correct' => true],
                    ['text' => 'Таиланд', 'is_correct' => false],
                    ['text' => 'Камбоджа', 'is_correct' => false],
                    ['text' => 'Лаос', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство расположено на "сапоге" Европы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Тадж-Махал?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Индия', 'is_correct' => true],
                    ['text' => 'Турция', 'is_correct' => false],
                    ['text' => 'Иран', 'is_correct' => false],
                    ['text' => 'Египет', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет больше всего часовых поясов (с учетом заморских территорий)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Франция', 'is_correct' => true],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Великобритания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется самая длинная горная цепь на суше?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Анды', 'is_correct' => true],
                    ['text' => 'Гималаи', 'is_correct' => false],
                    ['text' => 'Скалистые горы', 'is_correct' => false],
                    ['text' => 'Альпы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какого государства является Рейкьявик?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Исландия', 'is_correct' => true],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Финляндия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком океане находятся Гавайские острова?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тихий', 'is_correct' => true],
                    ['text' => 'Атлантический', 'is_correct' => false],
                    ['text' => 'Индийский', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна является родиной кленового сиропа?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'Норвегия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Турции?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Анкара', 'is_correct' => true],
                    ['text' => 'Стамбул', 'is_correct' => false],
                    ['text' => 'Измир', 'is_correct' => false],
                    ['text' => 'Анталья', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Столовая гора?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'ЮАР (Кейптаун)', 'is_correct' => true],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Кения', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство не имеет выхода к морю?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Монголия', 'is_correct' => true],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                    ['text' => 'Таиланд', 'is_correct' => false],
                    ['text' => 'Камбоджа', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Назовите самый большой вулкан на Земле.',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Мауна-Лоа', 'is_correct' => true],
                    ['text' => 'Этна', 'is_correct' => false],
                    ['text' => 'Килиманджаро', 'is_correct' => false],
                    ['text' => 'Везувий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая река пересекает экватор дважды?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Конго', 'is_correct' => true],
                    ['text' => 'Нил', 'is_correct' => false],
                    ['text' => 'Амазонка', 'is_correct' => false],
                    ['text' => 'Миссисипи', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Дубровник?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Хорватия', 'is_correct' => true],
                    ['text' => 'Черногория', 'is_correct' => false],
                    ['text' => 'Словения', 'is_correct' => false],
                    ['text' => 'Сербия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна владеет Гренландией?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дания', 'is_correct' => true],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Исландия', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе находится штаб-квартира ООН?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нью-Йорк', 'is_correct' => true],
                    ['text' => 'Женева', 'is_correct' => false],
                    ['text' => 'Вашингтон', 'is_correct' => false],
                    ['text' => 'Брюссель', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет форму длинной узкой полосы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Чили', 'is_correct' => true],
                    ['text' => 'Аргентина', 'is_correct' => false],
                    ['text' => 'Перу', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое озеро называют «морем» из-за его размеров?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Каспийское', 'is_correct' => true],
                    ['text' => 'Байкал', 'is_correct' => false],
                    ['text' => 'Ладожское', 'is_correct' => false],
                    ['text' => 'Онежское', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая африканская страна не была колонией (кроме короткого периода оккупации)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Эфиопия', 'is_correct' => true],
                    ['text' => 'Нигерия', 'is_correct' => false],
                    ['text' => 'Кения', 'is_correct' => false],
                    ['text' => 'Гана', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Египта?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Каир', 'is_correct' => true],
                    ['text' => 'Александрия', 'is_correct' => false],
                    ['text' => 'Луксор', 'is_correct' => false],
                    ['text' => 'Гиза', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится остров Бали?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Индонезия', 'is_correct' => true],
                    ['text' => 'Таиланд', 'is_correct' => false],
                    ['text' => 'Филиппины', 'is_correct' => false],
                    ['text' => 'Малайзия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой водопад самый высокий в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Анхель', 'is_correct' => true],
                    ['text' => 'Ниагарский', 'is_correct' => false],
                    ['text' => 'Виктория', 'is_correct' => false],
                    ['text' => 'Игуасу', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна известна своими фьордами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Норвегия', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Финляндия', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Хельсинки?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Финляндия', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая пустыня самая большая в мире?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Антарктическая', 'is_correct' => true],
                    ['text' => 'Сахара', 'is_correct' => false],
                    ['text' => 'Гоби', 'is_correct' => false],
                    ['text' => 'Калахари', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Петра?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Иордания', 'is_correct' => true],
                    ['text' => 'Сирия', 'is_correct' => false],
                    ['text' => 'Ливан', 'is_correct' => false],
                    ['text' => 'Ирак', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Испании?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мадрид', 'is_correct' => true],
                    ['text' => 'Барселона', 'is_correct' => false],
                    ['text' => 'Валенсия', 'is_correct' => false],
                    ['text' => 'Севилья', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна граничит с США на юге?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мексика', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Куба', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Транссибирская магистраль?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Россия', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
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
        // Не удаляем вопросы, чтобы не нарушить целостность статистики
    }
};
