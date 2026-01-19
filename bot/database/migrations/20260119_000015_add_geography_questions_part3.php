<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000015_add_geography_questions_part3';
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
                'question_text' => 'В какой стране находится город Касабланка?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марокко', 'is_correct' => true],
                    ['text' => 'Тунис', 'is_correct' => false],
                    ['text' => 'Египет', 'is_correct' => false],
                    ['text' => 'Алжир', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Хельсинки?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Финляндия', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна известна своими фьордами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Норвегия', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Финляндия', 'is_correct' => false],
                    ['text' => 'Исландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится гора Эверест?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Непал / Китай', 'is_correct' => true],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Пакистан', 'is_correct' => false],
                    ['text' => 'Бутан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой штат США самый большой по площади?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Аляска', 'is_correct' => true],
                    ['text' => 'Техас', 'is_correct' => false],
                    ['text' => 'Калифорния', 'is_correct' => false],
                    ['text' => 'Монтана', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Мачу-Пикчу?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Перу', 'is_correct' => true],
                    ['text' => 'Чили', 'is_correct' => false],
                    ['text' => 'Боливия', 'is_correct' => false],
                    ['text' => 'Мексика', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Лиссабон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Португалия', 'is_correct' => true],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Аргентина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое озеро самое глубокое в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Байкал', 'is_correct' => true],
                    ['text' => 'Танганьика', 'is_correct' => false],
                    ['text' => 'Верхнее', 'is_correct' => false],
                    ['text' => 'Виктория', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Тадж-Махал?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Индия', 'is_correct' => true],
                    ['text' => 'Пакистан', 'is_correct' => false],
                    ['text' => 'Бангладеш', 'is_correct' => false],
                    ['text' => 'Иран', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Турции?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Анкара', 'is_correct' => true],
                    ['text' => 'Стамбул', 'is_correct' => false],
                    ['text' => 'Анталья', 'is_correct' => false],
                    ['text' => 'Измир', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет форму кленового листа (на карте - нет, на флаге)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Ниагарский водопад?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США и Канада', 'is_correct' => true],
                    ['text' => 'Бразилия и Аргентина', 'is_correct' => false],
                    ['text' => 'Венесуэла', 'is_correct' => false],
                    ['text' => 'Африка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Дублин?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ирландия', 'is_correct' => true],
                    ['text' => 'Шотландия', 'is_correct' => false],
                    ['text' => 'Уэльс', 'is_correct' => false],
                    ['text' => 'Исландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком океане находятся Гавайские острова?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Тихий', 'is_correct' => true],
                    ['text' => 'Атлантический', 'is_correct' => false],
                    ['text' => 'Индийский', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна является родиной кенгуру?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Австралия', 'is_correct' => true],
                    ['text' => 'Новая Зеландия', 'is_correct' => false],
                    ['text' => 'ЮАР', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город называют "Вечным городом"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Рим', 'is_correct' => true],
                    ['text' => 'Афины', 'is_correct' => false],
                    ['text' => 'Иерусалим', 'is_correct' => false],
                    ['text' => 'Стамбул', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Прага?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Чехия', 'is_correct' => true],
                    ['text' => 'Словакия', 'is_correct' => false],
                    ['text' => 'Польша', 'is_correct' => false],
                    ['text' => 'Австрия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Мертвое море?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Израиль / Иордания', 'is_correct' => true],
                    ['text' => 'Египет', 'is_correct' => false],
                    ['text' => 'Турция', 'is_correct' => false],
                    ['text' => 'Саудовская Аравия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая река протекает через Лондон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Темза', 'is_correct' => true],
                    ['text' => 'Сена', 'is_correct' => false],
                    ['text' => 'Рейн', 'is_correct' => false],
                    ['text' => 'Дунай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Кремниевая долина?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США (Калифорния)', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Бангкок?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Таиланд', 'is_correct' => true],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                    ['text' => 'Камбоджа', 'is_correct' => false],
                    ['text' => 'Филиппины', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Венеция?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой континент самый маленький по площади?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Австралия', 'is_correct' => true],
                    ['text' => 'Европа', 'is_correct' => false],
                    ['text' => 'Антарктида', 'is_correct' => false],
                    ['text' => 'Южная Америка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет флаг с синим крестом на белом фоне?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Финляндия', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => false],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Дания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Каир?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Египет', 'is_correct' => true],
                    ['text' => 'Саудовская Аравия', 'is_correct' => false],
                    ['text' => 'Ирак', 'is_correct' => false],
                    ['text' => 'Марокко', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе находится Лувр?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Париж', 'is_correct' => true],
                    ['text' => 'Лондон', 'is_correct' => false],
                    ['text' => 'Рим', 'is_correct' => false],
                    ['text' => 'Мадрид', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна находится на "сапоге" Европы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Чернобыль?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Украина', 'is_correct' => true],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'Беларусь', 'is_correct' => false],
                    ['text' => 'Польша', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Будапешт?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Венгрия', 'is_correct' => true],
                    ['text' => 'Румыния', 'is_correct' => false],
                    ['text' => 'Болгария', 'is_correct' => false],
                    ['text' => 'Австрия', 'is_correct' => false],
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
                'question_text' => 'Какой океан омывает восточное побережье США?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Атлантический', 'is_correct' => true],
                    ['text' => 'Тихий', 'is_correct' => false],
                    ['text' => 'Индийский', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Гавана?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Куба', 'is_correct' => true],
                    ['text' => 'Ямайка', 'is_correct' => false],
                    ['text' => 'Доминикана', 'is_correct' => false],
                    ['text' => 'Гаити', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Иерусалим?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Израиль', 'is_correct' => true],
                    ['text' => 'Египет', 'is_correct' => false],
                    ['text' => 'Иордания', 'is_correct' => false],
                    ['text' => 'Ливан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет самый большой остров в мире (Гренландия принадлежит ей)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Дания', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Норвегия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Голливуд?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лос-Анджелес', 'is_correct' => true],
                    ['text' => 'Нью-Йорк', 'is_correct' => false],
                    ['text' => 'Майами', 'is_correct' => false],
                    ['text' => 'Чикаго', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Рейкьявик?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Исландия', 'is_correct' => true],
                    ['text' => 'Гренландия', 'is_correct' => false],
                    ['text' => 'Норвегия', 'is_correct' => false],
                    ['text' => 'Финляндия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится Стоунхендж?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Великобритания', 'is_correct' => true],
                    ['text' => 'Ирландия', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна находится между Францией и Испанией?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Андорра', 'is_correct' => true],
                    ['text' => 'Монако', 'is_correct' => false],
                    ['text' => 'Люксембург', 'is_correct' => false],
                    ['text' => 'Бельгия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Индии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Нью-Дели', 'is_correct' => true],
                    ['text' => 'Мумбаи', 'is_correct' => false],
                    ['text' => 'Калькутта', 'is_correct' => false],
                    ['text' => 'Бангалор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Транссибирская магистраль?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Россия', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Богота?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Колумбия', 'is_correct' => true],
                    ['text' => 'Венесуэла', 'is_correct' => false],
                    ['text' => 'Перу', 'is_correct' => false],
                    ['text' => 'Эквадор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Мельбурн?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Австралия', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Новая Зеландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство находится внутри Рима?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ватикан', 'is_correct' => true],
                    ['text' => 'Сан-Марино', 'is_correct' => false],
                    ['text' => 'Монако', 'is_correct' => false],
                    ['text' => 'Мальта', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится пустыня Атакама (самая сухая)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Чили', 'is_correct' => true],
                    ['text' => 'Перу', 'is_correct' => false],
                    ['text' => 'Аргентина', 'is_correct' => false],
                    ['text' => 'Боливия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Минск?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Беларусь', 'is_correct' => true],
                    ['text' => 'Украина', 'is_correct' => false],
                    ['text' => 'Польша', 'is_correct' => false],
                    ['text' => 'Литва', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Шанхай?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Китай', 'is_correct' => true],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город называют "Северной столицей" России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Санкт-Петербург', 'is_correct' => true],
                    ['text' => 'Мурманск', 'is_correct' => false],
                    ['text' => 'Архангельск', 'is_correct' => false],
                    ['text' => 'Новосибирск', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Оттава?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Новая Зеландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится остров Мадагаскар?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мадагаскар (это страна)', 'is_correct' => true],
                    ['text' => 'ЮАР', 'is_correct' => false],
                    ['text' => 'Мозамбик', 'is_correct' => false],
                    ['text' => 'Танзания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится "Запретный город"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Пекин', 'is_correct' => true],
                    ['text' => 'Токио', 'is_correct' => false],
                    ['text' => 'Сеул', 'is_correct' => false],
                    ['text' => 'Бангкок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна знаменита своими часами и сыром?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Швейцария', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Бельгия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Манила?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Филиппины', 'is_correct' => true],
                    ['text' => 'Индонезия', 'is_correct' => false],
                    ['text' => 'Малайзия', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Мюнхен?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Германия', 'is_correct' => true],
                    ['text' => 'Австрия', 'is_correct' => false],
                    ['text' => 'Швейцария', 'is_correct' => false],
                    ['text' => 'Бельгия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое море находится между Африкой и Европой?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Средиземное море', 'is_correct' => true],
                    ['text' => 'Красное море', 'is_correct' => false],
                    ['text' => 'Черное море', 'is_correct' => false],
                    ['text' => 'Северное море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Буэнос-Айрес?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Аргентина', 'is_correct' => true],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Чили', 'is_correct' => false],
                    ['text' => 'Перу', 'is_correct' => false],
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
