<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000012_add_geography_questions_part2';
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
                'question_text' => 'Какая река самая длинная в Европе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Волга', 'is_correct' => true],
                    ['text' => 'Дунай', 'is_correct' => false],
                    ['text' => 'Рейн', 'is_correct' => false],
                    ['text' => 'Урал', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Марракеш?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Марокко', 'is_correct' => true],
                    ['text' => 'Египет', 'is_correct' => false],
                    ['text' => 'Тунис', 'is_correct' => false],
                    ['text' => 'Алжир', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Баку?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Азербайджан', 'is_correct' => true],
                    ['text' => 'Армения', 'is_correct' => false],
                    ['text' => 'Грузия', 'is_correct' => false],
                    ['text' => 'Турция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой океан самый маленький?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Северный Ледовитый', 'is_correct' => true],
                    ['text' => 'Индийский', 'is_correct' => false],
                    ['text' => 'Южный', 'is_correct' => false],
                    ['text' => 'Атлантический', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится самый большой коралловый риф?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Австралия (Большой Барьерный риф)', 'is_correct' => true],
                    ['text' => 'Мальдивы', 'is_correct' => false],
                    ['text' => 'Карибы', 'is_correct' => false],
                    ['text' => 'Индонезия', 'is_correct' => false],
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
                'question_text' => 'Как называется самая высокая гора в Африке?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Килиманджаро', 'is_correct' => true],
                    ['text' => 'Кения', 'is_correct' => false],
                    ['text' => 'Атлас', 'is_correct' => false],
                    ['text' => 'Эльгон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Пизанская башня?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой континент самый жаркий?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Африка', 'is_correct' => true],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Южная Америка', 'is_correct' => false],
                    ['text' => 'Азия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет форму сапога?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Осло?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Норвегия', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                    ['text' => 'Финляндия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Эйфелева башня?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Париж', 'is_correct' => true],
                    ['text' => 'Лондон', 'is_correct' => false],
                    ['text' => 'Берлин', 'is_correct' => false],
                    ['text' => 'Рим', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая река протекает через Каир?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Нил', 'is_correct' => true],
                    ['text' => 'Амазонка', 'is_correct' => false],
                    ['text' => 'Евфрат', 'is_correct' => false],
                    ['text' => 'Иордан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Сидней?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Австралия', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Новая Зеландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Китая?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пекин', 'is_correct' => true],
                    ['text' => 'Шанхай', 'is_correct' => false],
                    ['text' => 'Гонконг', 'is_correct' => false],
                    ['text' => 'Тайбэй', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна граничит с Испанией на западе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Португалия', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Марокко', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе находится Красная площадь?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Москва', 'is_correct' => true],
                    ['text' => 'Санкт-Петербург', 'is_correct' => false],
                    ['text' => 'Киев', 'is_correct' => false],
                    ['text' => 'Минск', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое море разделяет Великобританию и Скандинавию?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Северное море', 'is_correct' => true],
                    ['text' => 'Балтийское море', 'is_correct' => false],
                    ['text' => 'Средиземное море', 'is_correct' => false],
                    ['text' => 'Норвежское море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Токио?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Южная Корея', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой материк покрыт льдом?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Антарктида', 'is_correct' => true],
                    ['text' => 'Арктика (не материк)', 'is_correct' => false],
                    ['text' => 'Гренландия (остров)', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Дубай?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'ОАЭ', 'is_correct' => true],
                    ['text' => 'Саудовская Аравия', 'is_correct' => false],
                    ['text' => 'Катар', 'is_correct' => false],
                    ['text' => 'Кувейт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется самая большая пустыня в Азии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гоби', 'is_correct' => true],
                    ['text' => 'Сахара', 'is_correct' => false],
                    ['text' => 'Каракумы', 'is_correct' => false],
                    ['text' => 'Аравийская', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город стоит на реке Неве?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Санкт-Петербург', 'is_correct' => true],
                    ['text' => 'Москва', 'is_correct' => false],
                    ['text' => 'Новгород', 'is_correct' => false],
                    ['text' => 'Казань', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна известна своими тюльпанами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нидерланды', 'is_correct' => true],
                    ['text' => 'Бельгия', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Берлин?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Германия', 'is_correct' => true],
                    ['text' => 'Австрия', 'is_correct' => false],
                    ['text' => 'Польша', 'is_correct' => false],
                    ['text' => 'Швейцария', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Гранд-Каньон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Мексика', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой океан разделяет Америку и Европу?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Атлантический', 'is_correct' => true],
                    ['text' => 'Тихий', 'is_correct' => false],
                    ['text' => 'Индийский', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Мумбаи?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Индия', 'is_correct' => true],
                    ['text' => 'Пакистан', 'is_correct' => false],
                    ['text' => 'Бангладеш', 'is_correct' => false],
                    ['text' => 'Непал', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет флаг с красным кленовым листом?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Япония', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Мадрид?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Испания', 'is_correct' => true],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                    ['text' => 'Мексика', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой остров называют "Изумрудным островом"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ирландия', 'is_correct' => true],
                    ['text' => 'Исландия', 'is_correct' => false],
                    ['text' => 'Гренландия', 'is_correct' => false],
                    ['text' => 'Великобритания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находятся Альпы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Швейцария (и другие)', 'is_correct' => true],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Россия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство самое большое по площади?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Россия', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Китай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город называют "Большое Яблоко"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нью-Йорк', 'is_correct' => true],
                    ['text' => 'Лос-Анджелес', 'is_correct' => false],
                    ['text' => 'Чикаго', 'is_correct' => false],
                    ['text' => 'Вашингтон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится река Янцзы?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Китай', 'is_correct' => true],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Вена?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Австрия', 'is_correct' => true],
                    ['text' => 'Венгрия', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Польша', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое море самое теплое?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Красное море', 'is_correct' => true],
                    ['text' => 'Черное море', 'is_correct' => false],
                    ['text' => 'Средиземное море', 'is_correct' => false],
                    ['text' => 'Балтийское море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Мауна-Кеа?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Гавайи', 'is_correct' => true],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Индонезия', 'is_correct' => false],
                    ['text' => 'Исландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна известна своими пирамидами (не Египет)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Мексика', 'is_correct' => true],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Греции?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Афины', 'is_correct' => true],
                    ['text' => 'Рим', 'is_correct' => false],
                    ['text' => 'Спарта', 'is_correct' => false],
                    ['text' => 'Стамбул', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком океане находится Марианская впадина?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тихий', 'is_correct' => true],
                    ['text' => 'Атлантический', 'is_correct' => false],
                    ['text' => 'Индийский', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет столицу Бангкок?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Таиланд', 'is_correct' => true],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                    ['text' => 'Индонезия', 'is_correct' => false],
                    ['text' => 'Малайзия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой пролив разделяет Аляску и Россию?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Берингов пролив', 'is_correct' => true],
                    ['text' => 'Гибралтарский пролив', 'is_correct' => false],
                    ['text' => 'Магелланов пролив', 'is_correct' => false],
                    ['text' => 'Ла-Манш', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Варшава?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Польша', 'is_correct' => true],
                    ['text' => 'Чехия', 'is_correct' => false],
                    ['text' => 'Словакия', 'is_correct' => false],
                    ['text' => 'Венгрия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Кейптаун?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'ЮАР', 'is_correct' => true],
                    ['text' => 'Египет', 'is_correct' => false],
                    ['text' => 'Кения', 'is_correct' => false],
                    ['text' => 'Нигерия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое озеро находится в Шотландии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лох-Несс', 'is_correct' => true],
                    ['text' => 'Байкал', 'is_correct' => false],
                    ['text' => 'Мичиган', 'is_correct' => false],
                    ['text' => 'Виктория', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна является родиной пасты и пиццы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Сеул?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Южная Корея', 'is_correct' => true],
                    ['text' => 'Северная Корея', 'is_correct' => false],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Япония', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе находится Статуя Свободы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нью-Йорк', 'is_correct' => true],
                    ['text' => 'Вашингтон', 'is_correct' => false],
                    ['text' => 'Лос-Анджелес', 'is_correct' => false],
                    ['text' => 'Чикаго', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет флаг с красным кругом на белом фоне?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город стоит на реке Темзе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лондон', 'is_correct' => true],
                    ['text' => 'Париж', 'is_correct' => false],
                    ['text' => 'Берлин', 'is_correct' => false],
                    ['text' => 'Мадрид', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Амстердам?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Нидерланды', 'is_correct' => true],
                    ['text' => 'Бельгия', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                    ['text' => 'Швеция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Рио-де-Жанейро?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бразилия', 'is_correct' => true],
                    ['text' => 'Аргентина', 'is_correct' => false],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Чили', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна самая маленькая в Южной Америке?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Суринам', 'is_correct' => true],
                    ['text' => 'Уругвай', 'is_correct' => false],
                    ['text' => 'Гайана', 'is_correct' => false],
                    ['text' => 'Парагвай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Швеции?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Стокгольм', 'is_correct' => true],
                    ['text' => 'Осло', 'is_correct' => false],
                    ['text' => 'Хельсинки', 'is_correct' => false],
                    ['text' => 'Копенгаген', 'is_correct' => false],
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
