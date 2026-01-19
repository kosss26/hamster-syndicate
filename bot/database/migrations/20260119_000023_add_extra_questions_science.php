<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000023_add_extra_questions_science';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'Наука и технологии')->first();
        
        if (!$category) {
            echo "Category 'Наука и технологии' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Что измеряется в вольтах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Напряжение', 'is_correct' => true],
                    ['text' => 'Сила тока', 'is_correct' => false],
                    ['text' => 'Сопротивление', 'is_correct' => false],
                    ['text' => 'Мощность', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета ближе всех к Солнцу?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Меркурий', 'is_correct' => true],
                    ['text' => 'Венера', 'is_correct' => false],
                    ['text' => 'Земля', 'is_correct' => false],
                    ['text' => 'Марс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая формула воды?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'H2O', 'is_correct' => true],
                    ['text' => 'CO2', 'is_correct' => false],
                    ['text' => 'O2', 'is_correct' => false],
                    ['text' => 'NaCl', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сформулировал законы движения?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Исаак Ньютон', 'is_correct' => true],
                    ['text' => 'Альберт Эйнштейн', 'is_correct' => false],
                    ['text' => 'Галилео Галилей', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое ДНК?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дезоксирибонуклеиновая кислота', 'is_correct' => true],
                    ['text' => 'Динамическая нейронная карта', 'is_correct' => false],
                    ['text' => 'Двойной нуклеиновый код', 'is_correct' => false],
                    ['text' => 'Дополнительный набор клеток', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая скорость света (примерно)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '300 000 км/с', 'is_correct' => true],
                    ['text' => '100 000 км/с', 'is_correct' => false],
                    ['text' => '1 000 000 км/с', 'is_correct' => false],
                    ['text' => '330 м/с', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой элемент самый распространенный во Вселенной?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Водород', 'is_correct' => true],
                    ['text' => 'Гелий', 'is_correct' => false],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Углерод', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Атом"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мельчайшая частица вещества', 'is_correct' => true],
                    ['text' => 'Тип энергии', 'is_correct' => false],
                    ['text' => 'Космическое тело', 'is_correct' => false],
                    ['text' => 'Единица информации', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел радио?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Попов (или Маркони)', 'is_correct' => true],
                    ['text' => 'Эдисон', 'is_correct' => false],
                    ['text' => 'Белл', 'is_correct' => false],
                    ['text' => 'Тесла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой металл жидкий при комнатной температуре?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ртуть', 'is_correct' => true],
                    ['text' => 'Свинец', 'is_correct' => false],
                    ['text' => 'Олово', 'is_correct' => false],
                    ['text' => 'Алюминий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что изучает ботаника?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Растения', 'is_correct' => true],
                    ['text' => 'Животных', 'is_correct' => false],
                    ['text' => 'Грибы', 'is_correct' => false], // Микология, но в школе часто вместе
                    ['text' => 'Камни', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета называется "Красной планетой"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марс', 'is_correct' => true],
                    ['text' => 'Юпитер', 'is_correct' => false],
                    ['text' => 'Венера', 'is_correct' => false],
                    ['text' => 'Сатурн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Черная дыра"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Область с сильной гравитацией', 'is_correct' => true],
                    ['text' => 'Пустота в космосе', 'is_correct' => false],
                    ['text' => 'Потухшая звезда', 'is_correct' => false],
                    ['text' => 'Вход в другое измерение', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган у человека качает кровь?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сердце', 'is_correct' => true],
                    ['text' => 'Печень', 'is_correct' => false],
                    ['text' => 'Почки', 'is_correct' => false],
                    ['text' => 'Легкие', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл пенициллин?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Александр Флеминг', 'is_correct' => true],
                    ['text' => 'Луи Пастер', 'is_correct' => false],
                    ['text' => 'Роберт Кох', 'is_correct' => false],
                    ['text' => 'Илья Мечников', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Озон"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Газ, модификация кислорода (O3)', 'is_correct' => true],
                    ['text' => 'Парниковый газ', 'is_correct' => false],
                    ['text' => 'Жидкий азот', 'is_correct' => false],
                    ['text' => 'Редкий металл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая температура кипения воды (при нормальном давлении)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '100°C', 'is_correct' => true],
                    ['text' => '90°C', 'is_correct' => false],
                    ['text' => '110°C', 'is_correct' => false],
                    ['text' => '80°C', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ мы выдыхаем?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Углекислый газ', 'is_correct' => true],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Водород', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Гравитация"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сила притяжения', 'is_correct' => true],
                    ['text' => 'Сила трения', 'is_correct' => false],
                    ['text' => 'Магнитное поле', 'is_correct' => false],
                    ['text' => 'Давление воздуха', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто предложил гелиоцентрическую систему мира?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Николай Коперник', 'is_correct' => true],
                    ['text' => 'Птолемей', 'is_correct' => false],
                    ['text' => 'Джордано Бруно', 'is_correct' => false],
                    ['text' => 'Исаак Ньютон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой самый твердый природный минерал?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Алмаз', 'is_correct' => true],
                    ['text' => 'Гранит', 'is_correct' => false],
                    ['text' => 'Железо', 'is_correct' => false],
                    ['text' => 'Кварц', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что изучает астрономия?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Небесные тела и Вселенную', 'is_correct' => true],
                    ['text' => 'Знаки зодиака (это астрология)', 'is_correct' => false],
                    ['text' => 'Погоду', 'is_correct' => false],
                    ['text' => 'Атомы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется спутник Земли?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Луна', 'is_correct' => true],
                    ['text' => 'Фобос', 'is_correct' => false],
                    ['text' => 'Деймос', 'is_correct' => false],
                    ['text' => 'Титан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета самая большая в Солнечной системе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Юпитер', 'is_correct' => true],
                    ['text' => 'Сатурн', 'is_correct' => false],
                    ['text' => 'Уран', 'is_correct' => false],
                    ['text' => 'Нептун', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Лазер"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Усиление света (пучок)', 'is_correct' => true],
                    ['text' => 'Радиоволны', 'is_correct' => false],
                    ['text' => 'Звуковая волна', 'is_correct' => false],
                    ['text' => 'Электрический разряд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой элемент обозначается символом O?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кислород', 'is_correct' => true],
                    ['text' => 'Олово', 'is_correct' => false],
                    ['text' => 'Осмий', 'is_correct' => false],
                    ['text' => 'Золото', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел динамит?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Альфред Нобель', 'is_correct' => true],
                    ['text' => 'Дмитрий Менделеев', 'is_correct' => false],
                    ['text' => 'Мария Кюри', 'is_correct' => false],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Эволюция"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Процесс развития живой природы', 'is_correct' => true],
                    ['text' => 'Революция в науке', 'is_correct' => false],
                    ['text' => 'Рост населения', 'is_correct' => false],
                    ['text' => 'Создание новых видов', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая кость самая длинная в теле человека?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бедренная кость', 'is_correct' => true],
                    ['text' => 'Берцовая кость', 'is_correct' => false],
                    ['text' => 'Плечевая кость', 'is_correct' => false],
                    ['text' => 'Позвоночник', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Фотосинтез"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Образование органики на свету', 'is_correct' => true],
                    ['text' => 'Синтез фотографий', 'is_correct' => false],
                    ['text' => 'Дыхание растений', 'is_correct' => false],
                    ['text' => 'Поглощение воды', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой прибор измеряет атмосферное давление?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Барометр', 'is_correct' => true],
                    ['text' => 'Термометр', 'is_correct' => false],
                    ['text' => 'Гигрометр', 'is_correct' => false],
                    ['text' => 'Анемометр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько хромосом у здорового человека?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '46', 'is_correct' => true],
                    ['text' => '48', 'is_correct' => false],
                    ['text' => '42', 'is_correct' => false],
                    ['text' => '23', 'is_correct' => false], // 23 пары
                ]
            ],
            [
                'question_text' => 'Какая планета имеет кольца (самые заметные)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сатурн', 'is_correct' => true],
                    ['text' => 'Юпитер', 'is_correct' => false],
                    ['text' => 'Уран', 'is_correct' => false],
                    ['text' => 'Нептун', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто создал периодическую таблицу элементов?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дмитрий Менделеев', 'is_correct' => true],
                    ['text' => 'Михаил Ломоносов', 'is_correct' => false],
                    ['text' => 'Нильс Бор', 'is_correct' => false],
                    ['text' => 'Эрнест Резерфорд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Нанотехнологии"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Работа с объектами атомного масштаба', 'is_correct' => true],
                    ['text' => 'Новые компьютерные игры', 'is_correct' => false],
                    ['text' => 'Космические технологии', 'is_correct' => false],
                    ['text' => 'Строительство зданий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа крови является универсальным донором (по старой системе)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'I (0) отрицательная', 'is_correct' => true],
                    ['text' => 'IV (AB) положительная', 'is_correct' => false],
                    ['text' => 'II (A)', 'is_correct' => false],
                    ['text' => 'III (B)', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "GPS"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Глобальная система позиционирования', 'is_correct' => true],
                    ['text' => 'Генератор полей связи', 'is_correct' => false],
                    ['text' => 'Городская поисковая служба', 'is_correct' => false],
                    ['text' => 'Главный почтовый сервер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой витамин мы получаем от солнца?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Витамин D', 'is_correct' => true],
                    ['text' => 'Витамин C', 'is_correct' => false],
                    ['text' => 'Витамин A', 'is_correct' => false],
                    ['text' => 'Витамин B12', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Интернет"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Всемирная компьютерная сеть', 'is_correct' => true],
                    ['text' => 'Большая библиотека', 'is_correct' => false],
                    ['text' => 'Космическая связь', 'is_correct' => false],
                    ['text' => 'Мобильный телефон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ используется в воздушных шарах (легче воздуха)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гелий', 'is_correct' => true],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Углекислый газ', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Ген"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Единица наследственности', 'is_correct' => true],
                    ['text' => 'Часть клетки', 'is_correct' => false],
                    ['text' => 'Вирус', 'is_correct' => false],
                    ['text' => 'Белок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел первый самолет?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Братья Райт', 'is_correct' => true],
                    ['text' => 'Леонардо да Винчи', 'is_correct' => false],
                    ['text' => 'Игорь Сикорский', 'is_correct' => false],
                    ['text' => 'Альберто Сантос-Дюмон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая часть клетки содержит ДНК?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ядро', 'is_correct' => true],
                    ['text' => 'Мембрана', 'is_correct' => false],
                    ['text' => 'Цитоплазма', 'is_correct' => false],
                    ['text' => 'Митохондрия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Wi-Fi"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Беспроводная сеть', 'is_correct' => true],
                    ['text' => 'Интернет по проводу', 'is_correct' => false],
                    ['text' => 'Спутниковая тарелка', 'is_correct' => false],
                    ['text' => 'Радиостанция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой прибор используется для наблюдения за звездами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Телескоп', 'is_correct' => true],
                    ['text' => 'Микроскоп', 'is_correct' => false],
                    ['text' => 'Бинокль', 'is_correct' => false],
                    ['text' => 'Перископ', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая единица измерения информации базовая?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бит (или Байт)', 'is_correct' => true],
                    ['text' => 'Герц', 'is_correct' => false],
                    ['text' => 'Пиксель', 'is_correct' => false],
                    ['text' => 'Вольт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Робот"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Автоматическое устройство', 'is_correct' => true],
                    ['text' => 'Компьютерная программа', 'is_correct' => false],
                    ['text' => 'Игрушка', 'is_correct' => false],
                    ['text' => 'Человек в костюме', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл Америку (в научном смысле - географию)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Колумб (для Европы)', 'is_correct' => true],
                    ['text' => 'Магеллан', 'is_correct' => false],
                    ['text' => 'Кук', 'is_correct' => false],
                    ['text' => 'Веспуччи', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган очищает кровь?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Почки (и печень)', 'is_correct' => true],
                    ['text' => 'Сердце', 'is_correct' => false],
                    ['text' => 'Желудок', 'is_correct' => false],
                    ['text' => 'Легкие', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Магнит"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Тело с магнитным полем', 'is_correct' => true],
                    ['text' => 'Электрический заряд', 'is_correct' => false],
                    ['text' => 'Камень', 'is_correct' => false],
                    ['text' => 'Металл', 'is_correct' => false],
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
