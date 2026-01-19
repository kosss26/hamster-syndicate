<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000021_add_extra_questions_sports';
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
                    ['text' => '7', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используется шайба?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хоккей', 'is_correct' => true],
                    ['text' => 'Футбол', 'is_correct' => false],
                    ['text' => 'Теннис', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна выиграла ЧМ по футболу 2022?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Аргентина', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько длится футбольный матч (основное время)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '90 минут', 'is_correct' => true],
                    ['text' => '60 минут', 'is_correct' => false],
                    ['text' => '45 минут', 'is_correct' => false],
                    ['text' => '100 минут', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта есть термин "Пат"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Шахматы', 'is_correct' => true],
                    ['text' => 'Футбол', 'is_correct' => false],
                    ['text' => 'Бокс', 'is_correct' => false],
                    ['text' => 'Покер', 'is_correct' => false],
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
                'question_text' => 'Как называют вратаря в футболе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Голкипер', 'is_correct' => true],
                    ['text' => 'Форвард', 'is_correct' => false],
                    ['text' => 'Хавбек', 'is_correct' => false],
                    ['text' => 'Рефери', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используют ракетку?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Теннис', 'is_correct' => true],
                    ['text' => 'Волейбол', 'is_correct' => false],
                    ['text' => 'Баскетбол', 'is_correct' => false],
                    ['text' => 'Гандбол', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой мяч самый тяжелый?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Баскетбольный', 'is_correct' => true],
                    ['text' => 'Футбольный', 'is_correct' => false],
                    ['text' => 'Волейбольный', 'is_correct' => false],
                    ['text' => 'Теннисный', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько периодов в хоккее?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '3', 'is_correct' => true],
                    ['text' => '2', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '5', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой вид спорта называют "Королевой спорта"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Легкая атлетика', 'is_correct' => true],
                    ['text' => 'Гимнастика', 'is_correct' => false],
                    ['text' => 'Плавание', 'is_correct' => false],
                    ['text' => 'Теннис', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется "вне игры" в футболе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Офсайд', 'is_correct' => true],
                    ['text' => 'Аут', 'is_correct' => false],
                    ['text' => 'Фол', 'is_correct' => false],
                    ['text' => 'Корнер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько очков дают за попадание в кольцо со средней дистанции в баскетболе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '2', 'is_correct' => true],
                    ['text' => '1', 'is_correct' => false],
                    ['text' => '3', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране зародилось дзюдо?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Таиланд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой максимальный брейк в снукере?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '147', 'is_correct' => true],
                    ['text' => '100', 'is_correct' => false],
                    ['text' => '155', 'is_correct' => false],
                    ['text' => '140', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется удар ногой с разворота?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Вертушка (или Уширо-гери)', 'is_correct' => true],
                    ['text' => 'Джеб', 'is_correct' => false],
                    ['text' => 'Хук', 'is_correct' => false],
                    ['text' => 'Апперкот', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько фигур у каждого игрока в начале шахматной партии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '16', 'is_correct' => true],
                    ['text' => '32', 'is_correct' => false],
                    ['text' => '10', 'is_correct' => false],
                    ['text' => '8', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта есть стиль "баттерфляй"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Плавание', 'is_correct' => true],
                    ['text' => 'Бег', 'is_correct' => false],
                    ['text' => 'Бокс', 'is_correct' => false],
                    ['text' => 'Фигурное катание', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая дистанция марафона?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '42 км 195 м', 'is_correct' => true],
                    ['text' => '40 км', 'is_correct' => false],
                    ['text' => '50 км', 'is_correct' => false],
                    ['text' => '35 км', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Лионель Месси?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Футболист', 'is_correct' => true],
                    ['text' => 'Теннисист', 'is_correct' => false],
                    ['text' => 'Баскетболист', 'is_correct' => false],
                    ['text' => 'Гонщик', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется площадка для бокса?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ринг', 'is_correct' => true],
                    ['text' => 'Корт', 'is_correct' => false],
                    ['text' => 'Поле', 'is_correct' => false],
                    ['text' => 'Татами', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько игроков в волейбольной команде на площадке?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '6', 'is_correct' => true],
                    ['text' => '5', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                    ['text' => '11', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используется кий?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бильярд', 'is_correct' => true],
                    ['text' => 'Гольф', 'is_correct' => false],
                    ['text' => 'Крикет', 'is_correct' => false],
                    ['text' => 'Хоккей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна является родиной футбола?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Англия', 'is_correct' => true],
                    ['text' => 'Бразилия', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько очков за тачдаун в американском футболе?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '6', 'is_correct' => true],
                    ['text' => '5', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                    ['text' => '3', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется "ничья" в шахматах?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Пат (или ничья)', 'is_correct' => true],
                    ['text' => 'Мат', 'is_correct' => false],
                    ['text' => 'Шах', 'is_correct' => false],
                    ['text' => 'Гамбит', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта есть "тройной тулуп"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Фигурное катание', 'is_correct' => true],
                    ['text' => 'Гимнастика', 'is_correct' => false],
                    ['text' => 'Прыжки в воду', 'is_correct' => false],
                    ['text' => 'Фристайл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какого цвета пояс новичка в карате?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Белый', 'is_correct' => true],
                    ['text' => 'Черный', 'is_correct' => false],
                    ['text' => 'Желтый', 'is_correct' => false],
                    ['text' => 'Зеленый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Усэйн Болт?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бегун (спринтер)', 'is_correct' => true],
                    ['text' => 'Пловец', 'is_correct' => false],
                    ['text' => 'Боксер', 'is_correct' => false],
                    ['text' => 'Футболист', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько кеглей в боулинге?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '10', 'is_correct' => true],
                    ['text' => '9', 'is_correct' => false],
                    ['text' => '12', 'is_correct' => false],
                    ['text' => '8', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта есть дисциплина "Скелетон"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Санный спорт', 'is_correct' => true],
                    ['text' => 'Лыжи', 'is_correct' => false],
                    ['text' => 'Коньки', 'is_correct' => false],
                    ['text' => 'Велоспорт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая максимальная оценка в фигурном катании (старая система)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '6.0', 'is_correct' => true],
                    ['text' => '10.0', 'is_correct' => false],
                    ['text' => '5.0', 'is_correct' => false],
                    ['text' => '100', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется удар головой в футболе (сленг)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Хэдер (Header)', 'is_correct' => true],
                    ['text' => 'Паненка', 'is_correct' => false],
                    ['text' => 'Рабона', 'is_correct' => false],
                    ['text' => 'Финт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько луз на бильярдном столе (пул)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '6', 'is_correct' => true],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '8', 'is_correct' => false],
                    ['text' => '5', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году проходила Олимпиада в Москве?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1980', 'is_correct' => true],
                    ['text' => '1984', 'is_correct' => false],
                    ['text' => '1976', 'is_correct' => false],
                    ['text' => '1988', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется штрафной удар в хоккее?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Буллит', 'is_correct' => true],
                    ['text' => 'Пенальти', 'is_correct' => false],
                    ['text' => 'Штрафной', 'is_correct' => false],
                    ['text' => 'Угловой', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой вид спорта называют "Игрой миллионов"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Футбол', 'is_correct' => true],
                    ['text' => 'Баскетбол', 'is_correct' => false],
                    ['text' => 'Крикет', 'is_correct' => false],
                    ['text' => 'Бейсбол', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько карт в стандартной колоде (без джокеров)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '52', 'is_correct' => true],
                    ['text' => '36', 'is_correct' => false],
                    ['text' => '54', 'is_correct' => false],
                    ['text' => '48', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Майкл Джордан?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Баскетболист', 'is_correct' => true],
                    ['text' => 'Футболист', 'is_correct' => false],
                    ['text' => 'Боксер', 'is_correct' => false],
                    ['text' => 'Актер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется самый престижный турнир в теннисе (Англия)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Уимблдон', 'is_correct' => true],
                    ['text' => 'Ролан Гаррос', 'is_correct' => false],
                    ['text' => 'US Open', 'is_correct' => false],
                    ['text' => 'Australian Open', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько длится раунд в профессиональном боксе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '3 минуты', 'is_correct' => true],
                    ['text' => '2 минуты', 'is_correct' => false],
                    ['text' => '5 минут', 'is_correct' => false],
                    ['text' => '1 минута', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком спорте используется термин "Страйк"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Боулинг (и Бейсбол)', 'is_correct' => true],
                    ['text' => 'Футбол', 'is_correct' => false],
                    ['text' => 'Теннис', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая фигура в шахматах ходит только по диагонали?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Слон', 'is_correct' => true],
                    ['text' => 'Ладья', 'is_correct' => false],
                    ['text' => 'Конь', 'is_correct' => false],
                    ['text' => 'Ферзь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта есть "Желтая майка лидера"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Велоспорт', 'is_correct' => true],
                    ['text' => 'Бег', 'is_correct' => false],
                    ['text' => 'Плавание', 'is_correct' => false],
                    ['text' => 'Биатлон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько игроков в команде по регби (классика)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '15', 'is_correct' => true],
                    ['text' => '11', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                    ['text' => '13', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Пеле"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Футболист', 'is_correct' => true],
                    ['text' => 'Баскетболист', 'is_correct' => false],
                    ['text' => 'Боксер', 'is_correct' => false],
                    ['text' => 'Гонщик', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком виде спорта используют "рапиру"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Фехтование', 'is_correct' => true],
                    ['text' => 'Теннис', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                    ['text' => 'Поло', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько секунд дается на атаку в баскетболе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '24', 'is_correct' => true],
                    ['text' => '30', 'is_correct' => false],
                    ['text' => '20', 'is_correct' => false],
                    ['text' => '15', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется "гол" в баскетболе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Попадание (или Очки)', 'is_correct' => true],
                    ['text' => 'Гол', 'is_correct' => false],
                    ['text' => 'Тачдаун', 'is_correct' => false],
                    ['text' => 'Сет', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой игре нужно выбить "Яблочко"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дартс (или Стрельба)', 'is_correct' => true],
                    ['text' => 'Боулинг', 'is_correct' => false],
                    ['text' => 'Гольф', 'is_correct' => false],
                    ['text' => 'Бильярд', 'is_correct' => false],
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
