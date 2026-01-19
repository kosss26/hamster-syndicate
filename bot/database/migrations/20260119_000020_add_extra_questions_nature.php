<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000020_add_extra_questions_nature';
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
                'question_text' => 'Какое животное является самым быстрым на суше?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гепард', 'is_correct' => true],
                    ['text' => 'Лев', 'is_correct' => false],
                    ['text' => 'Антилопа', 'is_correct' => false],
                    ['text' => 'Страус', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица не умеет летать, но отлично плавает?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пингвин', 'is_correct' => true],
                    ['text' => 'Страус', 'is_correct' => false],
                    ['text' => 'Киви', 'is_correct' => false],
                    ['text' => 'Утка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое дерево сбрасывает иголки на зиму?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Лиственница', 'is_correct' => true],
                    ['text' => 'Сосна', 'is_correct' => false],
                    ['text' => 'Ель', 'is_correct' => false],
                    ['text' => 'Пихта', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное спит стоя?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Лошадь (иногда)', 'is_correct' => true],
                    ['text' => 'Кошка', 'is_correct' => false],
                    ['text' => 'Собака', 'is_correct' => false],
                    ['text' => 'Медведь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая змея самая длинная в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Сетчатый питон', 'is_correct' => true],
                    ['text' => 'Анаконда', 'is_correct' => false],
                    ['text' => 'Королевская кобра', 'is_correct' => false],
                    ['text' => 'Черная мамба', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой цветок поворачивается за солнцем?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Подсолнух', 'is_correct' => true],
                    ['text' => 'Роза', 'is_correct' => false],
                    ['text' => 'Тюльпан', 'is_correct' => false],
                    ['text' => 'Ромашка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное самое большое на Земле?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Синий кит', 'is_correct' => true],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Жираф', 'is_correct' => false],
                    ['text' => 'Бегемот', 'is_correct' => false],
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
                'question_text' => 'Какой континент является родиной картофеля?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Южная Америка', 'is_correct' => true],
                    ['text' => 'Европа', 'is_correct' => false],
                    ['text' => 'Африка', 'is_correct' => false],
                    ['text' => 'Азия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное меняет цвет?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хамелеон', 'is_correct' => true],
                    ['text' => 'Ящерица', 'is_correct' => false],
                    ['text' => 'Змея', 'is_correct' => false],
                    ['text' => 'Черепаха', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица самая маленькая в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Колибри', 'is_correct' => true],
                    ['text' => 'Воробей', 'is_correct' => false],
                    ['text' => 'Синица', 'is_correct' => false],
                    ['text' => 'Ласточка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное имеет сумку для детенышей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кенгуру', 'is_correct' => true],
                    ['text' => 'Панда', 'is_correct' => false],
                    ['text' => 'Ленивец', 'is_correct' => false],
                    ['text' => 'Обезьяна', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое дерево является символом Канады?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Клен', 'is_correct' => true],
                    ['text' => 'Дуб', 'is_correct' => false],
                    ['text' => 'Береза', 'is_correct' => false],
                    ['text' => 'Ель', 'is_correct' => false],
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
                'question_text' => 'Какая птица является символом мудрости?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сова', 'is_correct' => true],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Ворон', 'is_correct' => false],
                    ['text' => 'Голубь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое растение ест насекомых?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Венерина мухоловка', 'is_correct' => true],
                    ['text' => 'Кактус', 'is_correct' => false],
                    ['text' => 'Роза', 'is_correct' => false],
                    ['text' => 'Орхидея', 'is_correct' => false],
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
                'question_text' => 'Какая рыба может ударить током?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Электрический скат (или угорь)', 'is_correct' => true],
                    ['text' => 'Акула', 'is_correct' => false],
                    ['text' => 'Пиранья', 'is_correct' => false],
                    ['text' => 'Сом', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное является символом России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Медведь', 'is_correct' => true],
                    ['text' => 'Волк', 'is_correct' => false],
                    ['text' => 'Тигр', 'is_correct' => false],
                    ['text' => 'Орел', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ необходим растениям для дыхания?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Углекислый газ', 'is_correct' => true],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Гелий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное спит вверх ногами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Летучая мышь', 'is_correct' => true],
                    ['text' => 'Ленивец', 'is_correct' => false], // Ленивцы висят, но спят не всегда так, а летучие мыши - классика
                    ['text' => 'Обезьяна', 'is_correct' => false],
                    ['text' => 'Попугай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица может летать задом наперед?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Колибри', 'is_correct' => true],
                    ['text' => 'Ласточка', 'is_correct' => false],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Чайка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное имеет самую длинную шею?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Жираф', 'is_correct' => true],
                    ['text' => 'Страус', 'is_correct' => false],
                    ['text' => 'Верблюд', 'is_correct' => false],
                    ['text' => 'Лама', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой фрукт называют "королем фруктов" (из-за запаха)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Дуриан', 'is_correct' => true],
                    ['text' => 'Ананас', 'is_correct' => false],
                    ['text' => 'Манго', 'is_correct' => false],
                    ['text' => 'Банан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное является символом Австралии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кенгуру', 'is_correct' => true],
                    ['text' => 'Коала', 'is_correct' => false],
                    ['text' => 'Вомбат', 'is_correct' => false],
                    ['text' => 'Утконос', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая ягода самая большая?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Арбуз (тыквина, но часто считают ягодой)', 'is_correct' => true],
                    ['text' => 'Клубника', 'is_correct' => false],
                    ['text' => 'Вишня', 'is_correct' => false],
                    ['text' => 'Малина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное имеет панцирь?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Черепаха', 'is_correct' => true],
                    ['text' => 'Лягушка', 'is_correct' => false],
                    ['text' => 'Змея', 'is_correct' => false],
                    ['text' => 'Ящерица', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица самая быстрая?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Сапсан', 'is_correct' => true],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Стриж', 'is_correct' => false],
                    ['text' => 'Ласточка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное называют "царем зверей"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лев', 'is_correct' => true],
                    ['text' => 'Тигр', 'is_correct' => false],
                    ['text' => 'Медведь', 'is_correct' => false],
                    ['text' => 'Слон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой цветок является символом Японии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хризантема (или Сакура)', 'is_correct' => true],
                    ['text' => 'Лотос', 'is_correct' => false],
                    ['text' => 'Роза', 'is_correct' => false],
                    ['text' => 'Пион', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное умеет смеяться?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гиена', 'is_correct' => true], // Издает звуки, похожие на смех
                    ['text' => 'Лев', 'is_correct' => false],
                    ['text' => 'Волк', 'is_correct' => false],
                    ['text' => 'Лиса', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица разносит почту (в сказках)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Голубь', 'is_correct' => true],
                    ['text' => 'Ворона', 'is_correct' => false],
                    ['text' => 'Сова', 'is_correct' => false],
                    ['text' => 'Сорока', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное самое медленное?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ленивец', 'is_correct' => true],
                    ['text' => 'Черепаха', 'is_correct' => false],
                    ['text' => 'Улитка', 'is_correct' => false],
                    ['text' => 'Коала', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой гриб самый ядовитый?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бледная поганка', 'is_correct' => true],
                    ['text' => 'Мухомор', 'is_correct' => false],
                    ['text' => 'Лисичка', 'is_correct' => false],
                    ['text' => 'Опенок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное живет в сумке у мамы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кенгуренок', 'is_correct' => true],
                    ['text' => 'Волчонок', 'is_correct' => false],
                    ['text' => 'Лисенок', 'is_correct' => false],
                    ['text' => 'Медвежонок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица имеет самый большой размах крыльев?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Альбатрос', 'is_correct' => true],
                    ['text' => 'Орел', 'is_correct' => false],
                    ['text' => 'Кондор', 'is_correct' => false],
                    ['text' => 'Пеликан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное дает шерсть?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Овца', 'is_correct' => true],
                    ['text' => 'Корова', 'is_correct' => false],
                    ['text' => 'Свинья', 'is_correct' => false],
                    ['text' => 'Лошадь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орех растет на пальме?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кокос', 'is_correct' => true],
                    ['text' => 'Грецкий орех', 'is_correct' => false],
                    ['text' => 'Фундук', 'is_correct' => false],
                    ['text' => 'Миндаль', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное похоже на человека?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Обезьяна', 'is_correct' => true],
                    ['text' => 'Медведь', 'is_correct' => false],
                    ['text' => 'Собака', 'is_correct' => false],
                    ['text' => 'Дельфин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица живет на льдине?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пингвин', 'is_correct' => true],
                    ['text' => 'Чайка', 'is_correct' => false],
                    ['text' => 'Полярная сова', 'is_correct' => false],
                    ['text' => 'Альбатрос', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное имеет полоски?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Зебра (или Тигр)', 'is_correct' => true],
                    ['text' => 'Лев', 'is_correct' => false],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Жираф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой цветок цветет только ночью?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Луноцвет', 'is_correct' => true],
                    ['text' => 'Роза', 'is_correct' => false],
                    ['text' => 'Тюльпан', 'is_correct' => false],
                    ['text' => 'Ромашка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное имеет рог на носу?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Носорог', 'is_correct' => true],
                    ['text' => 'Бегемот', 'is_correct' => false],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Буйвол', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица является символом мира?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Голубь', 'is_correct' => true],
                    ['text' => 'Лебедь', 'is_correct' => false],
                    ['text' => 'Журавль', 'is_correct' => false],
                    ['text' => 'Ласточка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное строит плотины?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бобр', 'is_correct' => true],
                    ['text' => 'Ондатра', 'is_correct' => false],
                    ['text' => 'Выдра', 'is_correct' => false],
                    ['text' => 'Нутрия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой овощ растет под землей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Морковь', 'is_correct' => true],
                    ['text' => 'Помидор', 'is_correct' => false],
                    ['text' => 'Огурец', 'is_correct' => false],
                    ['text' => 'Капуста', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное самое сильное (относительно веса)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Муравей (или Жук-навозник)', 'is_correct' => true],
                    ['text' => 'Слон', 'is_correct' => false],
                    ['text' => 'Медведь', 'is_correct' => false],
                    ['text' => 'Горилла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая птица может повторять слова?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Попугай', 'is_correct' => true],
                    ['text' => 'Ворона', 'is_correct' => false],
                    ['text' => 'Сорока', 'is_correct' => false],
                    ['text' => 'Скворец', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое животное является "санитаром леса"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Волк', 'is_correct' => true],
                    ['text' => 'Медведь', 'is_correct' => false],
                    ['text' => 'Лиса', 'is_correct' => false],
                    ['text' => 'Заяц', 'is_correct' => false],
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
