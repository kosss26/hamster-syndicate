<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000010_add_nature_questions';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'Природа')->first();
        
        if (!$category) {
            echo "Category 'Природа' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Какое животное самое быстрое на суше?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гепард', 'is_correct' => true],
                    ['text' => 'Лев', 'is_correct' => false],
                    ['text' => 'Антилопа', 'is_correct' => false],
                    ['text' => 'Лошадь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется самое высокое дерево в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Секвойя', 'is_correct' => true],
                    ['text' => 'Дуб', 'is_correct' => false],
                    ['text' => 'Баобаб', 'is_correct' => false],
                    ['text' => 'Эвкалипт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица не умеет летать, но быстро бегает?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Страус', 'is_correct' => true],
                    ['text' => 'Пингвин', 'is_correct' => false],
                    ['text' => 'Курица', 'is_correct' => false],
                    ['text' => 'Утка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое млекопитающее единственное умеет летать?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Летучая мышь', 'is_correct' => true],
                    ['text' => 'Белка-летяга', 'is_correct' => false],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Птерозавр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько ног у паука?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '8', 'is_correct' => true],
                    ['text' => '6', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '10', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное спит стоя?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Лошадь (часто)', 'is_correct' => true],
                    ['text' => 'Собака', 'is_correct' => false],
                    ['text' => 'Кошка', 'is_correct' => false],
                    ['text' => 'Медведь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что едят панды?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бамбук', 'is_correct' => true],
                    ['text' => 'Рыбу', 'is_correct' => false],
                    ['text' => 'Мясо', 'is_correct' => false],
                    ['text' => 'Фрукты', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая змея самая длинная в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Сетчатый питон', 'is_correct' => true],
                    ['text' => 'Анаконда', 'is_correct' => false],
                    ['text' => 'Кобра', 'is_correct' => false],
                    ['text' => 'Удав', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное является символом Австралии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кенгуру', 'is_correct' => true],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Тигр', 'is_correct' => false],
                    ['text' => 'Лев', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется самое большое животное на Земле?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Синий кит', 'is_correct' => true],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Жираф', 'is_correct' => false],
                    ['text' => 'Акула', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица может летать назад?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Колибри', 'is_correct' => true],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Ласточка', 'is_correct' => false],
                    ['text' => 'Воробей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько сердец у осьминога?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '3', 'is_correct' => true],
                    ['text' => '1', 'is_correct' => false],
                    ['text' => '2', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое растение хищное?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Венерина мухоловка', 'is_correct' => true],
                    ['text' => 'Роза', 'is_correct' => false],
                    ['text' => 'Кактус', 'is_correct' => false],
                    ['text' => 'Орхидея', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное меняет цвет кожи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хамелеон', 'is_correct' => true],
                    ['text' => 'Ящерица', 'is_correct' => false],
                    ['text' => 'Крокодил', 'is_correct' => false],
                    ['text' => 'Змея', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является царем зверей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лев', 'is_correct' => true],
                    ['text' => 'Тигр', 'is_correct' => false],
                    ['text' => 'Медведь', 'is_correct' => false],
                    ['text' => 'Волк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая рыба самая быстрая?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Парусник', 'is_correct' => true],
                    ['text' => 'Акула', 'is_correct' => false],
                    ['text' => 'Тунец', 'is_correct' => false],
                    ['text' => 'Щука', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что теряет лось каждую зиму?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Рога', 'is_correct' => true],
                    ['text' => 'Шерсть', 'is_correct' => false],
                    ['text' => 'Зубы', 'is_correct' => false],
                    ['text' => 'Хвост', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное живет дольше всех?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гренландская акула (или моллюск)', 'is_correct' => true],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Черепаха', 'is_correct' => false],
                    ['text' => 'Попугай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называются детеныши лягушки?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Головастики', 'is_correct' => true],
                    ['text' => 'Мальки', 'is_correct' => false],
                    ['text' => 'Личинки', 'is_correct' => false],
                    ['text' => 'Гусеницы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое дерево сбрасывает иголки на зиму?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лиственница', 'is_correct' => true],
                    ['text' => 'Ель', 'is_correct' => false],
                    ['text' => 'Сосна', 'is_correct' => false],
                    ['text' => 'Пихта', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой цветок является символом Японии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хризантема (или Сакура)', 'is_correct' => true],
                    ['text' => 'Роза', 'is_correct' => false],
                    ['text' => 'Лотос', 'is_correct' => false],
                    ['text' => 'Тюльпан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'У какого животного самая длинная шея?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Жираф', 'is_correct' => true],
                    ['text' => 'Страус', 'is_correct' => false],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Зебра', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое насекомое производит мед?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пчела', 'is_correct' => true],
                    ['text' => 'Оса', 'is_correct' => false],
                    ['text' => 'Шмель', 'is_correct' => false],
                    ['text' => 'Муравей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица самая крупная в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Страус', 'is_correct' => true],
                    ['text' => 'Альбатрос', 'is_correct' => false],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Пеликан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько горбов у дромадера?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1', 'is_correct' => true],
                    ['text' => '2', 'is_correct' => false],
                    ['text' => '3', 'is_correct' => false],
                    ['text' => '0', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное строит плотины?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бобр', 'is_correct' => true],
                    ['text' => 'Выдра', 'is_correct' => false],
                    ['text' => 'Ондатра', 'is_correct' => false],
                    ['text' => 'Барсук', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое айсберг?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Плавучая ледяная гора', 'is_correct' => true],
                    ['text' => 'Остров', 'is_correct' => false],
                    ['text' => 'Корабль', 'is_correct' => false],
                    ['text' => 'Облако', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное может обходиться без воды дольше верблюда?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Жираф (или кенгуровая крыса)', 'is_correct' => true],
                    ['text' => 'Лев', 'is_correct' => false],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Лошадь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'У какого животного черно-белые полосы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Зебра', 'is_correct' => true],
                    ['text' => 'Тигр', 'is_correct' => false],
                    ['text' => 'Панда', 'is_correct' => false],
                    ['text' => 'Лемур', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа львов?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Прайд', 'is_correct' => true],
                    ['text' => 'Стая', 'is_correct' => false],
                    ['text' => 'Табун', 'is_correct' => false],
                    ['text' => 'Косяк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное является самым тяжелым наземным животным?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Слон', 'is_correct' => true],
                    ['text' => 'Бегемот', 'is_correct' => false],
                    ['text' => 'Носорог', 'is_correct' => false],
                    ['text' => 'Зубр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое цунами?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гигантская волна', 'is_correct' => true],
                    ['text' => 'Ураган', 'is_correct' => false],
                    ['text' => 'Землетрясение', 'is_correct' => false],
                    ['text' => 'Вулкан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько лап у насекомых?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '6', 'is_correct' => true],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '8', 'is_correct' => false],
                    ['text' => '10', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица является символом мира?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Голубь', 'is_correct' => true],
                    ['text' => 'Лебедь', 'is_correct' => false],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Ворон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется лес, где всегда тепло и влажно?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тропический лес (Джунгли)', 'is_correct' => true],
                    ['text' => 'Тайга', 'is_correct' => false],
                    ['text' => 'Тундра', 'is_correct' => false],
                    ['text' => 'Степь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное носит своих детенышей в сумке?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кенгуру', 'is_correct' => true],
                    ['text' => 'Обезьяна', 'is_correct' => false],
                    ['text' => 'Медведь', 'is_correct' => false],
                    ['text' => 'Лев', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "царь грибов"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Белый гриб (Боровик)', 'is_correct' => true],
                    ['text' => 'Мухомор', 'is_correct' => false],
                    ['text' => 'Шампиньон', 'is_correct' => false],
                    ['text' => 'Лисичка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое морское животное считается самым умным?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дельфин', 'is_correct' => true],
                    ['text' => 'Акула', 'is_correct' => false],
                    ['text' => 'Кит', 'is_correct' => false],
                    ['text' => 'Скат', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой цветок поворачивается за солнцем?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Подсолнух', 'is_correct' => true],
                    ['text' => 'Ромашка', 'is_correct' => false],
                    ['text' => 'Василек', 'is_correct' => false],
                    ['text' => 'Мак', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая порода собак самая маленькая?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Чихуахуа', 'is_correct' => true],
                    ['text' => 'Бульдог', 'is_correct' => false],
                    ['text' => 'Пудель', 'is_correct' => false],
                    ['text' => 'Мопс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где живут пингвины (в основном)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Антарктида', 'is_correct' => true],
                    ['text' => 'Арктика', 'is_correct' => false],
                    ['text' => 'Африка', 'is_correct' => false],
                    ['text' => 'Гренландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное называют "кораблем пустыни"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Верблюд', 'is_correct' => true],
                    ['text' => 'Лошадь', 'is_correct' => false],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Осел', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое насекомое живет в муравейнике?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Муравей', 'is_correct' => true],
                    ['text' => 'Пчела', 'is_correct' => false],
                    ['text' => 'Жук', 'is_correct' => false],
                    ['text' => 'Термит', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое коралловый риф?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Колония морских организмов', 'is_correct' => true],
                    ['text' => 'Скала', 'is_correct' => false],
                    ['text' => 'Растение', 'is_correct' => false],
                    ['text' => 'Затонувший корабль', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица считается самой умной?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ворон', 'is_correct' => true],
                    ['text' => 'Попугай', 'is_correct' => false],
                    ['text' => 'Сова', 'is_correct' => false],
                    ['text' => 'Голубь', 'is_correct' => false],
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
