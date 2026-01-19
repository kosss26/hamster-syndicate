<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000009_add_sports_questions';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'Спорт')->first();
        
        if (!$category) {
            echo "Category 'Спорт' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Сколько игроков в футбольной команде на поле?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '11', 'is_correct' => true],
                    ['text' => '10', 'is_correct' => false],
                    ['text' => '12', 'is_correct' => false],
                    ['text' => '9', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используется термин "туше"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Борьба', 'is_correct' => true],
                    ['text' => 'Фехтование', 'is_correct' => false],
                    ['text' => 'Бокс', 'is_correct' => false],
                    ['text' => 'Теннис', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой длины марафонская дистанция?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '42 км 195 м', 'is_correct' => true],
                    ['text' => '40 км', 'is_correct' => false],
                    ['text' => '50 км', 'is_correct' => false],
                    ['text' => '41 км 500 м', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране зародились Олимпийские игры?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Греция', 'is_correct' => true],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Египет', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется мяч в бадминтоне?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Волан', 'is_correct' => true],
                    ['text' => 'Шайба', 'is_correct' => false],
                    ['text' => 'Пинг', 'is_correct' => false],
                    ['text' => 'Снитч', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько периодов в хоккейном матче?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '3', 'is_correct' => true],
                    ['text' => '2', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '1', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой цвет майки лидера "Тур де Франс"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Желтый', 'is_correct' => true],
                    ['text' => 'Розовый', 'is_correct' => false],
                    ['text' => 'Зеленый', 'is_correct' => false],
                    ['text' => 'Белый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта прославился Майкл Джордан?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Баскетбол', 'is_correct' => true],
                    ['text' => 'Бейсбол', 'is_correct' => false],
                    ['text' => 'Футбол', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько фигур у одного игрока в начале шахматной партии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '16', 'is_correct' => true],
                    ['text' => '12', 'is_correct' => false],
                    ['text' => '10', 'is_correct' => false],
                    ['text' => '20', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна чаще всего выигрывала Чемпионат мира по футболу?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бразилия', 'is_correct' => true],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Аргентина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что означает аббревиатура НХЛ?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Национальная хоккейная лига', 'is_correct' => true],
                    ['text' => 'Новая хоккейная лига', 'is_correct' => false],
                    ['text' => 'Народная хоккейная лига', 'is_correct' => false],
                    ['text' => 'Немецкая хоккейная лига', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используется бита?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бейсбол', 'is_correct' => true],
                    ['text' => 'Теннис', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                    ['text' => 'Хоккей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется "ничья" в шахматах?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Пат (или ничья)', 'is_correct' => true],
                    ['text' => 'Мат', 'is_correct' => false],
                    ['text' => 'Шах', 'is_correct' => false],
                    ['text' => 'Рокировка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько кеглей в боулинге?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '10', 'is_correct' => true],
                    ['text' => '9', 'is_correct' => false],
                    ['text' => '12', 'is_correct' => false],
                    ['text' => '15', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой стиль плавания самый быстрый?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кроль (вольный стиль)', 'is_correct' => true],
                    ['text' => 'Брасс', 'is_correct' => false],
                    ['text' => 'Баттерфляй', 'is_correct' => false],
                    ['text' => 'На спине', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году прошли Олимпийские игры в Москве?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1980', 'is_correct' => true],
                    ['text' => '1976', 'is_correct' => false],
                    ['text' => '1984', 'is_correct' => false],
                    ['text' => '1988', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется площадка для бокса?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ринг', 'is_correct' => true],
                    ['text' => 'Татами', 'is_correct' => false],
                    ['text' => 'Корт', 'is_correct' => false],
                    ['text' => 'Поле', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько очков дается за попадание в кольцо со штрафного в баскетболе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '1', 'is_correct' => true],
                    ['text' => '2', 'is_correct' => false],
                    ['text' => '3', 'is_correct' => false],
                    ['text' => '0.5', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта выступал Усэйн Болт?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Легкая атлетика (спринт)', 'is_correct' => true],
                    ['text' => 'Плавание', 'is_correct' => false],
                    ['text' => 'Бокс', 'is_correct' => false],
                    ['text' => 'Футбол', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой максимальный брейк в снукере?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '147', 'is_correct' => true],
                    ['text' => '100', 'is_correct' => false],
                    ['text' => '155', 'is_correct' => false],
                    ['text' => '180', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько геймов нужно выиграть для победы в сете в теннисе (обычно)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '6', 'is_correct' => true],
                    ['text' => '5', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна является родиной дзюдо?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Таиланд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько минут длится футбольный тайм?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '45', 'is_correct' => true],
                    ['text' => '30', 'is_correct' => false],
                    ['text' => '60', 'is_correct' => false],
                    ['text' => '40', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используется "шайба"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хоккей', 'is_correct' => true],
                    ['text' => 'Керлинг', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                    ['text' => 'Бильярд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Лионель Месси?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Футболист', 'is_correct' => true],
                    ['text' => 'Теннисист', 'is_correct' => false],
                    ['text' => 'Гонщик', 'is_correct' => false],
                    ['text' => 'Боксер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется гонка "Формула-1"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гран-при', 'is_correct' => true],
                    ['text' => 'Ралли', 'is_correct' => false],
                    ['text' => 'Дрифт', 'is_correct' => false],
                    ['text' => 'Спринт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта спортсмены спускаются по ледяному желобу на санях?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бобслей (или сани/скелетон)', 'is_correct' => true],
                    ['text' => 'Биатлон', 'is_correct' => false],
                    ['text' => 'Керлинг', 'is_correct' => false],
                    ['text' => 'Фристайл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько колец на Олимпийском флаге?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '5', 'is_correct' => true],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '6', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой вид спорта называют "королевой спорта"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Легкая атлетика', 'is_correct' => true],
                    ['text' => 'Гимнастика', 'is_correct' => false],
                    ['text' => 'Плавание', 'is_correct' => false],
                    ['text' => 'Фигурное катание', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используется рапира?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Фехтование', 'is_correct' => true],
                    ['text' => 'Стрельба', 'is_correct' => false],
                    ['text' => 'Дартс', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько игроков в волейбольной команде на площадке?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '6', 'is_correct' => true],
                    ['text' => '5', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                    ['text' => '11', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется начало игры в баскетболе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Спорный бросок', 'is_correct' => true],
                    ['text' => 'Вбрасывание', 'is_correct' => false],
                    ['text' => 'Подача', 'is_correct' => false],
                    ['text' => 'Буллит', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто выиграл больше всего золотых олимпийских медалей в истории?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Майкл Фелпс', 'is_correct' => true],
                    ['text' => 'Лариса Латынина', 'is_correct' => false],
                    ['text' => 'Усэйн Болт', 'is_correct' => false],
                    ['text' => 'Карл Льюис', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта есть термин "апперкот"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бокс', 'is_correct' => true],
                    ['text' => 'Карате', 'is_correct' => false],
                    ['text' => 'Борьба', 'is_correct' => false],
                    ['text' => 'Дзюдо', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какого цвета нет на олимпийских кольцах?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Фиолетовый', 'is_correct' => true],
                    ['text' => 'Синий', 'is_correct' => false],
                    ['text' => 'Черный', 'is_correct' => false],
                    ['text' => 'Желтый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется "вне игры" в футболе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Офсайд', 'is_correct' => true],
                    ['text' => 'Аут', 'is_correct' => false],
                    ['text' => 'Фол', 'is_correct' => false],
                    ['text' => 'Пенальти', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком спорте есть ворота в форме буквы "Н"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Регби', 'is_correct' => true],
                    ['text' => 'Футбол', 'is_correct' => false],
                    ['text' => 'Хоккей', 'is_correct' => false],
                    ['text' => 'Гандбол', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна принимает "Ролан Гаррос"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Франция', 'is_correct' => true],
                    ['text' => 'Англия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько очков максимум можно выбить одним дротиком в дартс?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '60 (трипл 20)', 'is_correct' => true],
                    ['text' => '50 (булл)', 'is_correct' => false],
                    ['text' => '20', 'is_correct' => false],
                    ['text' => '100', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто считается "Королем футбола"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пеле', 'is_correct' => true],
                    ['text' => 'Марадона', 'is_correct' => false],
                    ['text' => 'Роналду', 'is_correct' => false],
                    ['text' => 'Зидан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько лунок на стандартном поле для гольфа?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '18', 'is_correct' => true],
                    ['text' => '9', 'is_correct' => false],
                    ['text' => '12', 'is_correct' => false],
                    ['text' => '20', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта соревнуются на татами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дзюдо (или карате)', 'is_correct' => true],
                    ['text' => 'Бокс', 'is_correct' => false],
                    ['text' => 'Сумо', 'is_correct' => false],
                    ['text' => 'Фехтование', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется штрафной удар с 11 метров в футболе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пенальти', 'is_correct' => true],
                    ['text' => 'Угловой', 'is_correct' => false],
                    ['text' => 'Свободный', 'is_correct' => false],
                    ['text' => 'Аут', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой вид спорта сочетает лыжные гонки и стрельбу?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Биатлон', 'is_correct' => true],
                    ['text' => 'Триатлон', 'is_correct' => false],
                    ['text' => 'Пентатлон', 'is_correct' => false],
                    ['text' => 'Керлинг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе находится стадион "Камп Ноу"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Барселона', 'is_correct' => true],
                    ['text' => 'Мадрид', 'is_correct' => false],
                    ['text' => 'Милан', 'is_correct' => false],
                    ['text' => 'Лондон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько длится раунд в профессиональном боксе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '3 минуты', 'is_correct' => true],
                    ['text' => '2 минуты', 'is_correct' => false],
                    ['text' => '5 минут', 'is_correct' => false],
                    ['text' => '1 минута', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется палка для игры в гольф?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Клюшка', 'is_correct' => true],
                    ['text' => 'Бита', 'is_correct' => false],
                    ['text' => 'Ракетка', 'is_correct' => false],
                    ['text' => 'Кий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используются термины "страйк" и "спэр"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Боулинг', 'is_correct' => true],
                    ['text' => 'Бейсбол', 'is_correct' => false],
                    ['text' => 'Бильярд', 'is_correct' => false],
                    ['text' => 'Крикет', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой предмет используют в керлинге для натирания льда?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Щетка', 'is_correct' => true],
                    ['text' => 'Швабра', 'is_correct' => false],
                    ['text' => 'Лопата', 'is_correct' => false],
                    ['text' => 'Веник', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько игроков в команде по водному поло?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '7', 'is_correct' => true],
                    ['text' => '6', 'is_correct' => false],
                    ['text' => '11', 'is_correct' => false],
                    ['text' => '5', 'is_correct' => false],
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
