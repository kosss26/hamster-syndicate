<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000006_add_pop_culture_questions_part1';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        $categoryId = 3; // Поп-культура

        $questions = [
            [
                'question_text' => 'Кто является создателем вселенной "Звездных войн"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джордж Лукас', 'is_correct' => true],
                    ['text' => 'Стивен Спилберг', 'is_correct' => false],
                    ['text' => 'Джеймс Кэмерон', 'is_correct' => false],
                    ['text' => 'Кристофер Нолан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя серии книг о Гарри Поттере?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гарри Поттер', 'is_correct' => true],
                    ['text' => 'Рон Уизли', 'is_correct' => false],
                    ['text' => 'Драко Малфой', 'is_correct' => false],
                    ['text' => 'Северус Снейп', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Bohemian Rhapsody"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Queen', 'is_correct' => true],
                    ['text' => 'The Beatles', 'is_correct' => false],
                    ['text' => 'Led Zeppelin', 'is_correct' => false],
                    ['text' => 'Pink Floyd', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Джека Воробья в фильмах "Пираты Карибского моря"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джонни Депп', 'is_correct' => true],
                    ['text' => 'Орландо Блум', 'is_correct' => false],
                    ['text' => 'Брэд Питт', 'is_correct' => false],
                    ['text' => 'Том Круз', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленный город, в котором живет Бэтмен?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Готэм', 'is_correct' => true],
                    ['text' => 'Метрополис', 'is_correct' => false],
                    ['text' => 'Стар-Сити', 'is_correct' => false],
                    ['text' => 'Централ-Сити', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая певица известна как "Королева поп-музыки"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Мадонна', 'is_correct' => true],
                    ['text' => 'Бритни Спирс', 'is_correct' => false],
                    ['text' => 'Леди Гага', 'is_correct' => false],
                    ['text' => 'Уитни Хьюстон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале главные герои — физики Шелдон и Леонард?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Теория большого взрыва', 'is_correct' => true],
                    ['text' => 'Друзья', 'is_correct' => false],
                    ['text' => 'Как я встретил вашу маму', 'is_correct' => false],
                    ['text' => 'Кремниевая долина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал песню "Shape of You"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ed Sheeran', 'is_correct' => true],
                    ['text' => 'Justin Bieber', 'is_correct' => false],
                    ['text' => 'Shawn Mendes', 'is_correct' => false],
                    ['text' => 'Sam Smith', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут дракона Дейенерис Таргариен, на котором она летала?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Дрогон', 'is_correct' => true],
                    ['text' => 'Рейгаль', 'is_correct' => false],
                    ['text' => 'Визерион', 'is_correct' => false],
                    ['text' => 'Балерион', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой актер получил Оскар за роль Джокера в фильме "Темный рыцарь"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Хит Леджер', 'is_correct' => true],
                    ['text' => 'Хоакин Феникс', 'is_correct' => false],
                    ['text' => 'Джаред Лето', 'is_correct' => false],
                    ['text' => 'Джек Николсон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа, в которой начинала карьеру Бейонсе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Destiny\'s Child', 'is_correct' => true],
                    ['text' => 'Spice Girls', 'is_correct' => false],
                    ['text' => 'TLC', 'is_correct' => false],
                    ['text' => 'The Pussycat Dolls', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой фильм получил премию Оскар как "Лучший фильм" в 2020 году (первый неанглоязычный фильм)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Паразиты', 'is_correct' => true],
                    ['text' => 'Рома', 'is_correct' => false],
                    ['text' => 'Жизнь прекрасна', 'is_correct' => false],
                    ['text' => 'Амели', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является автором серии книг "Песнь Льда и Огня"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Джордж Р. Р. Мартин', 'is_correct' => true],
                    ['text' => 'Дж. Р. Р. Толкин', 'is_correct' => false],
                    ['text' => 'Стивен Кинг', 'is_correct' => false],
                    ['text' => 'Нил Гейман', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленная страна из фильма "Черная Пантера"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ваканда', 'is_correct' => true],
                    ['text' => 'Заковия', 'is_correct' => false],
                    ['text' => 'Геноша', 'is_correct' => false],
                    ['text' => 'Латверия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил роль Железного человека в киновселенной Marvel?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Роберт Дауни-младший', 'is_correct' => true],
                    ['text' => 'Крис Эванс', 'is_correct' => false],
                    ['text' => 'Крис Хемсворт', 'is_correct' => false],
                    ['text' => 'Марк Руффало', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая социальная сеть имеет логотип в виде птички?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Twitter (X)', 'is_correct' => true],
                    ['text' => 'Facebook', 'is_correct' => false],
                    ['text' => 'Instagram', 'is_correct' => false],
                    ['text' => 'Snapchat', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного злодея в серии фильмов о Гарри Поттере?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Волан-де-Морт', 'is_correct' => true],
                    ['text' => 'Грин-де-Вальд', 'is_correct' => false],
                    ['text' => 'Люциус Малфой', 'is_correct' => false],
                    ['text' => 'Беллатриса Лестрейндж', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто спел хит "Hello"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Adele', 'is_correct' => true],
                    ['text' => 'Beyonce', 'is_correct' => false],
                    ['text' => 'Rihanna', 'is_correct' => false],
                    ['text' => 'Sia', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой мультфильм рассказывает о приключениях огра и осла?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Шрек', 'is_correct' => true],
                    ['text' => 'Ледниковый период', 'is_correct' => false],
                    ['text' => 'Кунг-фу Панда', 'is_correct' => false],
                    ['text' => 'Как приручить дракона', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале действие происходит в Вестеросе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Игра престолов', 'is_correct' => true],
                    ['text' => 'Властелин колец', 'is_correct' => false],
                    ['text' => 'Ведьмак', 'is_correct' => false],
                    ['text' => 'Викинги', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является режиссером фильма "Аватар"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Джеймс Кэмерон', 'is_correct' => true],
                    ['text' => 'Ридли Скотт', 'is_correct' => false],
                    ['text' => 'Стивен Спилберг', 'is_correct' => false],
                    ['text' => 'Питер Джексон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется корабль Хана Соло?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тысячелетний сокол', 'is_correct' => true],
                    ['text' => 'Звезда смерти', 'is_correct' => false],
                    ['text' => 'X-Wing', 'is_correct' => false],
                    ['text' => 'Энтерпрайз', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая певица известна хитом "Bad Guy"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Billie Eilish', 'is_correct' => true],
                    ['text' => 'Ariana Grande', 'is_correct' => false],
                    ['text' => 'Dua Lipa', 'is_correct' => false],
                    ['text' => 'Selena Gomez', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году вышел первый фильм о Гарри Поттере?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '2001', 'is_correct' => true],
                    ['text' => '2000', 'is_correct' => false],
                    ['text' => '2002', 'is_correct' => false],
                    ['text' => '1999', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл главную роль в фильме "Форрест Гамп"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Том Хэнкс', 'is_correct' => true],
                    ['text' => 'Леонардо ДиКаприо', 'is_correct' => false],
                    ['text' => 'Брэд Питт', 'is_correct' => false],
                    ['text' => 'Джонни Депп', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя мультсериала "Симпсоны"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гомер', 'is_correct' => true],
                    ['text' => 'Барт', 'is_correct' => false],
                    ['text' => 'Нед', 'is_correct' => false],
                    ['text' => 'Мо', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа выпустила альбом "The Dark Side of the Moon"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Pink Floyd', 'is_correct' => true],
                    ['text' => 'The Beatles', 'is_correct' => false],
                    ['text' => 'Queen', 'is_correct' => false],
                    ['text' => 'The Rolling Stones', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является автором комиксов о Человеке-пауке?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Стэн Ли', 'is_correct' => true],
                    ['text' => 'Джек Кирби', 'is_correct' => false],
                    ['text' => 'Стив Дитко', 'is_correct' => false],
                    ['text' => 'Боб Кейн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется планета, с которой прилетел Супермен?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Криптон', 'is_correct' => true],
                    ['text' => 'Асгард', 'is_correct' => false],
                    ['text' => 'Вулкан', 'is_correct' => false],
                    ['text' => 'Татуин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Нео в трилогии "Матрица"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Киану Ривз', 'is_correct' => true],
                    ['text' => 'Хьюго Уивинг', 'is_correct' => false],
                    ['text' => 'Лоуренс Фишберн', 'is_correct' => false],
                    ['text' => 'Кэрри-Энн Мосс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая актриса сыграла Гермиону Грейнджер?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Эмма Уотсон', 'is_correct' => true],
                    ['text' => 'Бонни Райт', 'is_correct' => false],
                    ['text' => 'Эванна Линч', 'is_correct' => false],
                    ['text' => 'Кэти Льюнг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе происходят события сериала "Друзья"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нью-Йорк', 'is_correct' => true],
                    ['text' => 'Лос-Анджелес', 'is_correct' => false],
                    ['text' => 'Чикаго', 'is_correct' => false],
                    ['text' => 'Сан-Франциско', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является режиссером фильма "Криминальное чтиво"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Квентин Тарантино', 'is_correct' => true],
                    ['text' => 'Мартин Скорсезе', 'is_correct' => false],
                    ['text' => 'Гай Ричи', 'is_correct' => false],
                    ['text' => 'Фрэнсис Форд Коппола', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа, в которой поет Крис Мартин?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Coldplay', 'is_correct' => true],
                    ['text' => 'Imagine Dragons', 'is_correct' => false],
                    ['text' => 'OneRepublic', 'is_correct' => false],
                    ['text' => 'Maroon 5', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой фильм является самым кассовым в истории (на 2023 год)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Аватар', 'is_correct' => true],
                    ['text' => 'Мстители: Финал', 'is_correct' => false],
                    ['text' => 'Титаник', 'is_correct' => false],
                    ['text' => 'Звездные войны: Пробуждение силы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал музыку к фильму "Король Лев"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Ханс Циммер', 'is_correct' => true],
                    ['text' => 'Джон Уильямс', 'is_correct' => false],
                    ['text' => 'Алан Менкен', 'is_correct' => false],
                    ['text' => 'Элтон Джон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя мультфильма "Тачки"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Молния Маккуин', 'is_correct' => true],
                    ['text' => 'Мэтр', 'is_correct' => false],
                    ['text' => 'Салли', 'is_correct' => false],
                    ['text' => 'Док Хадсон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Росомахи в фильмах "Люди Икс"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хью Джекман', 'is_correct' => true],
                    ['text' => 'Патрик Стюарт', 'is_correct' => false],
                    ['text' => 'Иэн Маккеллен', 'is_correct' => false],
                    ['text' => 'Райан Рейнольдс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая видеоигра является самой продаваемой в мире?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Minecraft', 'is_correct' => true],
                    ['text' => 'GTA V', 'is_correct' => false],
                    ['text' => 'Tetris', 'is_correct' => false],
                    ['text' => 'Wii Sports', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленный континент в мире "Властелина колец"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Средиземье', 'is_correct' => true],
                    ['text' => 'Вестерос', 'is_correct' => false],
                    ['text' => 'Нарния', 'is_correct' => false],
                    ['text' => 'Тамриэль', 'is_correct' => false],
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
