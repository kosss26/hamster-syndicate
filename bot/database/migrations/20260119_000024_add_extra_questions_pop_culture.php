<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000024_add_extra_questions_pop_culture';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'Поп-культура')->first();
        
        if (!$category) {
            echo "Category 'Поп-культура' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Кто сыграл Железного Человека в фильмах Marvel?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Роберт Дауни мл.', 'is_correct' => true],
                    ['text' => 'Крис Эванс', 'is_correct' => false],
                    ['text' => 'Крис Хемсворт', 'is_correct' => false],
                    ['text' => 'Том Холланд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя "Гарри Поттера"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гарри Поттер', 'is_correct' => true],
                    ['text' => 'Рон Уизли', 'is_correct' => false],
                    ['text' => 'Драко Малфой', 'is_correct' => false],
                    ['text' => 'Северус Снейп', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть "Железный трон"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Игра престолов', 'is_correct' => true],
                    ['text' => 'Ведьмак', 'is_correct' => false],
                    ['text' => 'Властелин колец', 'is_correct' => false],
                    ['text' => 'Викинги', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет песню "Shape of You"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ed Sheeran', 'is_correct' => true],
                    ['text' => 'Justin Bieber', 'is_correct' => false],
                    ['text' => 'Drake', 'is_correct' => false],
                    ['text' => 'The Weeknd', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая фамилия у Шрека?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'У него нет фамилии', 'is_correct' => true],
                    ['text' => 'Болотный', 'is_correct' => false],
                    ['text' => 'Грин', 'is_correct' => false],
                    ['text' => 'Фиона', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Билли Айлиш?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Певица', 'is_correct' => true],
                    ['text' => 'Актриса', 'is_correct' => false],
                    ['text' => 'Модель', 'is_correct' => false],
                    ['text' => 'Блогер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного злодея в "Звездных войнах"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дарт Вейдер', 'is_correct' => true],
                    ['text' => 'Люк Скайуокер', 'is_correct' => false],
                    ['text' => 'Хан Соло', 'is_correct' => false],
                    ['text' => 'Йода', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году вышел первый iPhone?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '2007', 'is_correct' => true],
                    ['text' => '2005', 'is_correct' => false],
                    ['text' => '2010', 'is_correct' => false],
                    ['text' => '2000', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут друга Губки Боба (морскую звезду)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Патрик', 'is_correct' => true],
                    ['text' => 'Сквидвард', 'is_correct' => false],
                    ['text' => 'Планктон', 'is_correct' => false],
                    ['text' => 'Сэнди', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является автором "Властелина колец"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дж. Р. Р. Толкин', 'is_correct' => true],
                    ['text' => 'Дж. К. Роулинг', 'is_correct' => false],
                    ['text' => 'Джордж Мартин', 'is_correct' => false],
                    ['text' => 'Стивен Кинг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая игра самая продаваемая в мире (на 2024)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Minecraft', 'is_correct' => true],
                    ['text' => 'GTA V', 'is_correct' => false],
                    ['text' => 'Tetris', 'is_correct' => false],
                    ['text' => 'Fortnite', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Мистер Бист"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Популярный блогер (YouTube)', 'is_correct' => true],
                    ['text' => 'Рэпер', 'is_correct' => false],
                    ['text' => 'Актер', 'is_correct' => false],
                    ['text' => 'Политик', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме Ди Каприо получил Оскар?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Выживший', 'is_correct' => true],
                    ['text' => 'Титаник', 'is_correct' => false],
                    ['text' => 'Волк с Уолл-стрит', 'is_correct' => false],
                    ['text' => 'Начало', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вселенная супергероев с Бэтменом?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'DC', 'is_correct' => true],
                    ['text' => 'Marvel', 'is_correct' => false],
                    ['text' => 'Dark Horse', 'is_correct' => false],
                    ['text' => 'Image', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет песню "Bad Guy"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Billie Eilish', 'is_correct' => true],
                    ['text' => 'Ariana Grande', 'is_correct' => false],
                    ['text' => 'Taylor Swift', 'is_correct' => false],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой персонаж говорит "I\'ll be back"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Терминатор', 'is_correct' => true],
                    ['text' => 'Рэмбо', 'is_correct' => false],
                    ['text' => 'Робокоп', 'is_correct' => false],
                    ['text' => 'Джеймс Бонд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется город, где живет Бэтмен?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Готэм', 'is_correct' => true],
                    ['text' => 'Метрополис', 'is_correct' => false],
                    ['text' => 'Нью-Йорк', 'is_correct' => false],
                    ['text' => 'Чикаго', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Ким Кардашьян?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Звезда реалити-шоу и бизнесвумен', 'is_correct' => true],
                    ['text' => 'Певица', 'is_correct' => false],
                    ['text' => 'Актриса кино', 'is_correct' => false],
                    ['text' => 'Политик', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой сериал про зомби был очень популярен?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ходячие мертвецы', 'is_correct' => true],
                    ['text' => 'Во все тяжкие', 'is_correct' => false],
                    ['text' => 'Остаться в живых', 'is_correct' => false],
                    ['text' => 'Сверхъестественное', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя "Матрицы"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нео', 'is_correct' => true],
                    ['text' => 'Морфеус', 'is_correct' => false],
                    ['text' => 'Тринити', 'is_correct' => false],
                    ['text' => 'Смит', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал песни "Bohemian Rhapsody"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Queen (Фредди Меркьюри)', 'is_correct' => true],
                    ['text' => 'The Beatles', 'is_correct' => false],
                    ['text' => 'Pink Floyd', 'is_correct' => false],
                    ['text' => 'Led Zeppelin', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая социальная сеть известна короткими танцевальными видео?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'TikTok', 'is_correct' => true],
                    ['text' => 'Instagram', 'is_correct' => false],
                    ['text' => 'YouTube', 'is_correct' => false],
                    ['text' => 'Snapchat', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме снимался Джонни Депп в роли пирата?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пираты Карибского моря', 'is_correct' => true],
                    ['text' => 'Остров сокровищ', 'is_correct' => false],
                    ['text' => 'Капитан Крюк', 'is_correct' => false],
                    ['text' => 'Черная жемчужина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут жену Симпсонов?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мардж', 'is_correct' => true],
                    ['text' => 'Лиза', 'is_correct' => false],
                    ['text' => 'Мэгги', 'is_correct' => false],
                    ['text' => 'Барт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Тейлор Свифт?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Певица', 'is_correct' => true],
                    ['text' => 'Актриса', 'is_correct' => false],
                    ['text' => 'Телеведущая', 'is_correct' => false],
                    ['text' => 'Спортсменка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется школа чародейства и волшебства?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хогвартс', 'is_correct' => true],
                    ['text' => 'Дурмстранг', 'is_correct' => false],
                    ['text' => 'Шармбатон', 'is_correct' => false],
                    ['text' => 'Мордор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл Человека-паука (в фильмах 2000-х)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тоби Магуайр', 'is_correct' => true],
                    ['text' => 'Эндрю Гарфилд', 'is_correct' => false],
                    ['text' => 'Том Холланд', 'is_correct' => false],
                    ['text' => 'Бен Аффлек', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая корейская группа самая популярная в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'BTS', 'is_correct' => true],
                    ['text' => 'EXO', 'is_correct' => false],
                    ['text' => 'Big Bang', 'is_correct' => false],
                    ['text' => 'NCT', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Мем"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Вирусная картинка или шутка', 'is_correct' => true],
                    ['text' => 'Компьютерный вирус', 'is_correct' => false],
                    ['text' => 'Программа', 'is_correct' => false],
                    ['text' => 'Книга', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя мультфильма "Король Лев"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Симба', 'is_correct' => true],
                    ['text' => 'Муфаса', 'is_correct' => false],
                    ['text' => 'Шрам', 'is_correct' => false],
                    ['text' => 'Тимон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году вышел фильм "Аватар" (первый)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '2009', 'is_correct' => true],
                    ['text' => '2005', 'is_correct' => false],
                    ['text' => '2012', 'is_correct' => false],
                    ['text' => '2000', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Джокер"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Злодей из комиксов DC', 'is_correct' => true],
                    ['text' => 'Герой Marvel', 'is_correct' => false],
                    ['text' => 'Ведущий шоу', 'is_correct' => false],
                    ['text' => 'Персонаж из "Гарри Поттера"', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленный язык из "Игры престолов"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Валирийский (или Дотракийский)', 'is_correct' => true],
                    ['text' => 'Эльфийский', 'is_correct' => false],
                    ['text' => 'Клингонский', 'is_correct' => false],
                    ['text' => 'Мордорский', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Илон Маск?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Предприниматель (Tesla, SpaceX)', 'is_correct' => true],
                    ['text' => 'Актер', 'is_correct' => false],
                    ['text' => 'Певец', 'is_correct' => false],
                    ['text' => 'Политик', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой сериал про наркоторговца и учителя химии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Во все тяжкие', 'is_correct' => true],
                    ['text' => 'Нарко', 'is_correct' => false],
                    ['text' => 'Озарк', 'is_correct' => false],
                    ['text' => 'Клан Сопрано', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет "Baby One More Time"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Britney Spears', 'is_correct' => true],
                    ['text' => 'Madonna', 'is_correct' => false],
                    ['text' => 'Christina Aguilera', 'is_correct' => false],
                    ['text' => 'Jennifer Lopez', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут робота из "Звездных войн" (маленький, бело-синий)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'R2-D2', 'is_correct' => true],
                    ['text' => 'C-3PO', 'is_correct' => false],
                    ['text' => 'BB-8', 'is_correct' => false],
                    ['text' => 'Wall-E', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Ведьмак"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Геральт из Ривии', 'is_correct' => true],
                    ['text' => 'Арагорн', 'is_correct' => false],
                    ['text' => 'Джон Сноу', 'is_correct' => false],
                    ['text' => 'Гэндальф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой мультфильм про принцессу с длинными волосами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рапунцель', 'is_correct' => true],
                    ['text' => 'Золушка', 'is_correct' => false],
                    ['text' => 'Белоснежка', 'is_correct' => false],
                    ['text' => 'Русалочка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Дэдпул"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Антигерой в красном костюме', 'is_correct' => true],
                    ['text' => 'Злодей из Бэтмена', 'is_correct' => false],
                    ['text' => 'Герой из Лиги Справедливости', 'is_correct' => false],
                    ['text' => 'Черепашка-ниндзя', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа Джона Леннона?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'The Beatles', 'is_correct' => true],
                    ['text' => 'The Rolling Stones', 'is_correct' => false],
                    ['text' => 'Queen', 'is_correct' => false],
                    ['text' => 'Nirvana', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Пикачу?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Покемон', 'is_correct' => true],
                    ['text' => 'Дигимон', 'is_correct' => false],
                    ['text' => 'Тамагочи', 'is_correct' => false],
                    ['text' => 'Йо-кай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется королевская битва в игре Fortnite?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Королевская битва (Battle Royale)', 'is_correct' => true],
                    ['text' => 'Смертельная битва', 'is_correct' => false],
                    ['text' => 'Голодные игры', 'is_correct' => false],
                    ['text' => 'Арена', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Леди Гага?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Певица и актриса', 'is_correct' => true],
                    ['text' => 'Модель', 'is_correct' => false],
                    ['text' => 'Спортсменка', 'is_correct' => false],
                    ['text' => 'Писательница', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут Шерлока Холмса в сериале BBC?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бенедикт Камбербэтч', 'is_correct' => true],
                    ['text' => 'Роберт Дауни мл.', 'is_correct' => false],
                    ['text' => 'Джуд Лоу', 'is_correct' => false],
                    ['text' => 'Мартин Фримен', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая франшиза про гонки и семью?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Форсаж', 'is_correct' => true],
                    ['text' => 'Тачки', 'is_correct' => false],
                    ['text' => 'Need for Speed', 'is_correct' => false],
                    ['text' => 'Такси', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Хатико"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Верный пес', 'is_correct' => true],
                    ['text' => 'Кот-мем', 'is_correct' => false],
                    ['text' => 'Покемон', 'is_correct' => false],
                    ['text' => 'Робот', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой игре нужно строить из кубиков?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Minecraft', 'is_correct' => true],
                    ['text' => 'Roblox', 'is_correct' => false],
                    ['text' => 'Lego', 'is_correct' => false],
                    ['text' => 'Tetris', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто победил на Евровидении с песней "Waterloo"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'ABBA', 'is_correct' => true],
                    ['text' => 'Beatles', 'is_correct' => false],
                    ['text' => 'Queen', 'is_correct' => false],
                    ['text' => 'Boney M', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут подружку Микки Мауса?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Минни Маус', 'is_correct' => true],
                    ['text' => 'Дейзи Дак', 'is_correct' => false],
                    ['text' => 'Белль', 'is_correct' => false],
                    ['text' => 'Ариэль', 'is_correct' => false],
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
