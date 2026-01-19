<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000018_add_pop_culture_questions_final';
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
                'question_text' => 'Кто сыграл роль Дарта Вейдера (голос)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Джеймс Эрл Джонс', 'is_correct' => true],
                    ['text' => 'Дэвид Проуз', 'is_correct' => false],
                    ['text' => 'Хейден Кристенсен', 'is_correct' => false],
                    ['text' => 'Себастьян Шоу', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая песня принесла популярность Бритни Спирс?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '...Baby One More Time', 'is_correct' => true],
                    ['text' => 'Toxic', 'is_correct' => false],
                    ['text' => 'Oops!... I Did It Again', 'is_correct' => false],
                    ['text' => 'Gimme More', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме звучит фраза "Hasta la vista, baby"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Терминатор 2', 'is_correct' => true],
                    ['text' => 'Терминатор', 'is_correct' => false],
                    ['text' => 'Вспомнить все', 'is_correct' => false],
                    ['text' => 'Хищник', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Гарри Поттер"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джоан Роулинг', 'is_correct' => true],
                    ['text' => 'Стефани Майер', 'is_correct' => false],
                    ['text' => 'Сьюзен Коллинз', 'is_correct' => false],
                    ['text' => 'Вероника Рот', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут дракона Дейенерис (самого большого)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дрогон', 'is_correct' => true],
                    ['text' => 'Рейгаль', 'is_correct' => false],
                    ['text' => 'Визерион', 'is_correct' => false],
                    ['text' => 'Балерион', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Waka Waka"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Shakira', 'is_correct' => true],
                    ['text' => 'Jennifer Lopez', 'is_correct' => false],
                    ['text' => 'Beyonce', 'is_correct' => false],
                    ['text' => 'Rihanna', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком мультфильме есть "Радиатор-Спрингс"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Тачки', 'is_correct' => true],
                    ['text' => 'Самолеты', 'is_correct' => false],
                    ['text' => 'История игрушек', 'is_correct' => false],
                    ['text' => 'Суперсемейка', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Росомахи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хью Джекман', 'is_correct' => true],
                    ['text' => 'Райан Рейнольдс', 'is_correct' => false],
                    ['text' => 'Крис Хемсворт', 'is_correct' => false],
                    ['text' => 'Том Харди', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа, исполнившая "Wonderwall"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Oasis', 'is_correct' => true],
                    ['text' => 'Blur', 'is_correct' => false],
                    ['text' => 'Radiohead', 'is_correct' => false],
                    ['text' => 'Coldplay', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме ДиКаприо играет мошенника (летчика/врача)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Поймай меня, если сможешь', 'is_correct' => true],
                    ['text' => 'Волк с Уолл-стрит', 'is_correct' => false],
                    ['text' => 'Авиатор', 'is_correct' => false],
                    ['text' => 'Остров проклятых', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Мандалорец"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дин Джарин', 'is_correct' => true],
                    ['text' => 'Боба Фетт', 'is_correct' => false],
                    ['text' => 'Джанго Фетт', 'is_correct' => false],
                    ['text' => 'Люк Скайуокер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая актриса сыграла Китнисс Эвердин?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дженнифер Лоуренс', 'is_correct' => true],
                    ['text' => 'Шейлин Вудли', 'is_correct' => false],
                    ['text' => 'Эмма Стоун', 'is_correct' => false],
                    ['text' => 'Кристен Стюарт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет песню "Thinking Out Loud"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ed Sheeran', 'is_correct' => true],
                    ['text' => 'Sam Smith', 'is_correct' => false],
                    ['text' => 'Justin Timberlake', 'is_correct' => false],
                    ['text' => 'Bruno Mars', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется меч джедая?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Световой меч', 'is_correct' => true],
                    ['text' => 'Лазерный меч', 'is_correct' => false],
                    ['text' => 'Плазменный меч', 'is_correct' => false],
                    ['text' => 'Энергетический меч', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Шерлока в сериале BBC?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бенедикт Камбербэтч', 'is_correct' => true],
                    ['text' => 'Роберт Дауни мл.', 'is_correct' => false],
                    ['text' => 'Джонни Ли Миллер', 'is_correct' => false],
                    ['text' => 'Мартин Фримен', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа записала альбом "The Dark Side of the Moon"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Pink Floyd', 'is_correct' => true],
                    ['text' => 'The Beatles', 'is_correct' => false],
                    ['text' => 'Led Zeppelin', 'is_correct' => false],
                    ['text' => 'Queen', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть персонаж "Миньон"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гадкий я', 'is_correct' => true],
                    ['text' => 'Мегамозг', 'is_correct' => false],
                    ['text' => 'Монстры на каникулах', 'is_correct' => false],
                    ['text' => 'Шрек', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Diamonds"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Rihanna', 'is_correct' => true],
                    ['text' => 'Sia', 'is_correct' => false],
                    ['text' => 'Beyonce', 'is_correct' => false],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется кафе в сериале "Друзья"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Central Perk', 'is_correct' => true],
                    ['text' => 'Starbucks', 'is_correct' => false],
                    ['text' => 'MacLaren\'s', 'is_correct' => false],
                    ['text' => 'Monk\'s', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Бэтмена в трилогии Нолана?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кристиан Бэйл', 'is_correct' => true],
                    ['text' => 'Бен Аффлек', 'is_correct' => false],
                    ['text' => 'Роберт Паттинсон', 'is_correct' => false],
                    ['text' => 'Майкл Китон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая песня "Короля и Шута" самая популярная?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кукла колдуна', 'is_correct' => true],
                    ['text' => 'Лесник', 'is_correct' => false],
                    ['text' => 'Прыгну со скалы', 'is_correct' => false],
                    ['text' => 'Ели мясо мужики', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме Киану Ривз мстит за собаку?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джон Уик', 'is_correct' => true],
                    ['text' => 'Матрица', 'is_correct' => false],
                    ['text' => 'Скорость', 'is_correct' => false],
                    ['text' => 'Константин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет песню "Believer"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Imagine Dragons', 'is_correct' => true],
                    ['text' => 'Coldplay', 'is_correct' => false],
                    ['text' => 'OneRepublic', 'is_correct' => false],
                    ['text' => 'Maroon 5', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется школа мутантов в "Людях Икс"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Институт Ксавьера', 'is_correct' => true],
                    ['text' => 'Хогвартс', 'is_correct' => false],
                    ['text' => 'Академия Амбрелла', 'is_correct' => false],
                    ['text' => 'Школа Сальваторе', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Ведьмака"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Анджей Сапковский', 'is_correct' => true],
                    ['text' => 'Джордж Мартин', 'is_correct' => false],
                    ['text' => 'Стивен Кинг', 'is_correct' => false],
                    ['text' => 'Нил Гейман', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть "Шляпник"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Алиса в Стране чудес', 'is_correct' => true],
                    ['text' => 'Чарли и шоколадная фабрика', 'is_correct' => false],
                    ['text' => 'Волшебник страны Оз', 'is_correct' => false],
                    ['text' => 'Питер Пэн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Тора (бога грома)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Крис Хемсворт', 'is_correct' => true],
                    ['text' => 'Том Хиддлстон', 'is_correct' => false],
                    ['text' => 'Крис Эванс', 'is_correct' => false],
                    ['text' => 'Крис Пратт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется песня из "Титаника"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'My Heart Will Go On', 'is_correct' => true],
                    ['text' => 'I Will Always Love You', 'is_correct' => false],
                    ['text' => 'All By Myself', 'is_correct' => false],
                    ['text' => 'Because You Loved Me', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто режиссер "Парка Юрского периода"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Стивен Спилберг', 'is_correct' => true],
                    ['text' => 'Джордж Лукас', 'is_correct' => false],
                    ['text' => 'Джеймс Кэмерон', 'is_correct' => false],
                    ['text' => 'Ридли Скотт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть "Деймон Сальваторе"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дневники вампира', 'is_correct' => true],
                    ['text' => 'Древние', 'is_correct' => false],
                    ['text' => 'Наследие', 'is_correct' => false],
                    ['text' => 'Сверхъестественное', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Chandelier"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Sia', 'is_correct' => true],
                    ['text' => 'Rihanna', 'is_correct' => false],
                    ['text' => 'Adele', 'is_correct' => false],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется город в "Гриффинах"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Куахог', 'is_correct' => true],
                    ['text' => 'Спрингфилд', 'is_correct' => false],
                    ['text' => 'Лэнгли Фолс', 'is_correct' => false],
                    ['text' => 'Южный Парк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Джеймса Бонда (последний)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дэниел Крейг', 'is_correct' => true],
                    ['text' => 'Пирс Броснан', 'is_correct' => false],
                    ['text' => 'Шон Коннери', 'is_correct' => false],
                    ['text' => 'Роджер Мур', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Wind of Change"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Scorpions', 'is_correct' => true],
                    ['text' => 'Europe', 'is_correct' => false],
                    ['text' => 'Bon Jovi', 'is_correct' => false],
                    ['text' => 'Guns N\' Roses', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме герой застрял в одном дне?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'День сурка', 'is_correct' => true],
                    ['text' => 'Грань будущего', 'is_correct' => false],
                    ['text' => 'Исходный код', 'is_correct' => false],
                    ['text' => 'Патруль времени', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Оно"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Стивен Кинг', 'is_correct' => true],
                    ['text' => 'Дин Кунц', 'is_correct' => false],
                    ['text' => 'Р. Л. Стайн', 'is_correct' => false],
                    ['text' => 'Клайв Баркер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется машина времени в "Докторе Кто"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'ТАРДИС', 'is_correct' => true],
                    ['text' => 'Делориан', 'is_correct' => false],
                    ['text' => 'Энтерпрайз', 'is_correct' => false],
                    ['text' => 'Светлячок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет "Blinding Lights"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'The Weeknd', 'is_correct' => true],
                    ['text' => 'Drake', 'is_correct' => false],
                    ['text' => 'Bruno Mars', 'is_correct' => false],
                    ['text' => 'Post Malone', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть "мальчик, который выжил"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гарри Поттер', 'is_correct' => true],
                    ['text' => 'Перси Джексон', 'is_correct' => false],
                    ['text' => 'Эрагон', 'is_correct' => false],
                    ['text' => 'Хроники Нарнии', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Аквамена?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джейсон Момоа', 'is_correct' => true],
                    ['text' => 'Дуэйн Джонсон', 'is_correct' => false],
                    ['text' => 'Вин Дизель', 'is_correct' => false],
                    ['text' => 'Генри Кавилл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Thunder"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Imagine Dragons', 'is_correct' => true],
                    ['text' => 'AC/DC', 'is_correct' => false], // У них Thunderstruck
                    ['text' => 'Coldplay', 'is_correct' => false],
                    ['text' => 'OneRepublic', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале действие происходит в Чернобыле (HBO)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Чернобыль', 'is_correct' => true],
                    ['text' => 'Сталкер', 'is_correct' => false],
                    ['text' => 'Зона', 'is_correct' => false],
                    ['text' => 'Радиация', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Rolling in the Deep"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Adele', 'is_correct' => true],
                    ['text' => 'Amy Winehouse', 'is_correct' => false],
                    ['text' => 'Duffy', 'is_correct' => false],
                    ['text' => 'Sia', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется королевство Эльзы и Анны?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Эренделл', 'is_correct' => true],
                    ['text' => 'Корона', 'is_correct' => false],
                    ['text' => 'Атлантика', 'is_correct' => false],
                    ['text' => 'Аграба', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Доктора Стрэнджа?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бенедикт Камбербэтч', 'is_correct' => true],
                    ['text' => 'Мадс Миккельсен', 'is_correct' => false],
                    ['text' => 'Том Хиддлстон', 'is_correct' => false],
                    ['text' => 'Джуд Лоу', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая песня у Nirvana самая известная?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Smells Like Teen Spirit', 'is_correct' => true],
                    ['text' => 'Come As You Are', 'is_correct' => false],
                    ['text' => 'Lithium', 'is_correct' => false],
                    ['text' => 'Heart-Shaped Box', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме Том Хэнкс разговаривает с мячом?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Изгой', 'is_correct' => true],
                    ['text' => 'Терминал', 'is_correct' => false],
                    ['text' => 'Капитан Филлипс', 'is_correct' => false],
                    ['text' => 'Спасти рядового Райана', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Гордость и предубеждение"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Джейн Остин', 'is_correct' => true],
                    ['text' => 'Шарлотта Бронте', 'is_correct' => false],
                    ['text' => 'Эмили Бронте', 'is_correct' => false],
                    ['text' => 'Вирджиния Вулф', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вселенная "Звездных войн"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Далекая-далекая галактика', 'is_correct' => true],
                    ['text' => 'Млечный путь', 'is_correct' => false],
                    ['text' => 'Андромеда', 'is_correct' => false],
                    ['text' => 'Федерация', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет "Bad Romance"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Lady Gaga', 'is_correct' => true],
                    ['text' => 'Madonna', 'is_correct' => false],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                    ['text' => 'Britney Spears', 'is_correct' => false],
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
