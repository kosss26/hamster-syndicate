<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000013_add_pop_culture_questions_part2';
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
                'question_text' => 'Как зовут главного героя в сериале "Во все тяжкие"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Уолтер Уайт', 'is_correct' => true],
                    ['text' => 'Джесси Пинкман', 'is_correct' => false],
                    ['text' => 'Сол Гудман', 'is_correct' => false],
                    ['text' => 'Гус Фринг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Тора в киновселенной Marvel?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Крис Хемсворт', 'is_correct' => true],
                    ['text' => 'Крис Эванс', 'is_correct' => false],
                    ['text' => 'Крис Пратт', 'is_correct' => false],
                    ['text' => 'Том Хиддлстон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Smells Like Teen Spirit"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Nirvana', 'is_correct' => true],
                    ['text' => 'Pearl Jam', 'is_correct' => false],
                    ['text' => 'Soundgarden', 'is_correct' => false],
                    ['text' => 'Metallica', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является режиссером фильма "Интерстеллар"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кристофер Нолан', 'is_correct' => true],
                    ['text' => 'Стивен Спилберг', 'is_correct' => false],
                    ['text' => 'Дени Вильнев', 'is_correct' => false],
                    ['text' => 'Джеймс Кэмерон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется школа чародейства и волшебства в Гарри Поттере?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хогвартс', 'is_correct' => true],
                    ['text' => 'Дурмстранг', 'is_correct' => false],
                    ['text' => 'Шармбатон', 'is_correct' => false],
                    ['text' => 'Ильверморни', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл главную роль в фильме "Титаник"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Леонардо ДиКаприо', 'is_correct' => true],
                    ['text' => 'Брэд Питт', 'is_correct' => false],
                    ['text' => 'Джонни Депп', 'is_correct' => false],
                    ['text' => 'Том Круз', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая певица известна хитом "Shake It Off"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Taylor Swift', 'is_correct' => true],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                    ['text' => 'Lady Gaga', 'is_correct' => false],
                    ['text' => 'Miley Cyrus', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя мультфильма "История игрушек"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Вуди', 'is_correct' => true],
                    ['text' => 'Базз Лайтер', 'is_correct' => false],
                    ['text' => 'Энди', 'is_correct' => false],
                    ['text' => 'Слинки', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал серию книг "Голодные игры"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Сьюзен Коллинз', 'is_correct' => true],
                    ['text' => 'Вероника Рот', 'is_correct' => false],
                    ['text' => 'Стефани Майер', 'is_correct' => false],
                    ['text' => 'Джоан Роулинг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой актер сыграл Дэдпула?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Райан Рейнольдс', 'is_correct' => true],
                    ['text' => 'Хью Джекман', 'is_correct' => false],
                    ['text' => 'Крис Эванс', 'is_correct' => false],
                    ['text' => 'Бен Аффлек', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленный язык в "Игре престолов"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Валирийский (или Дотракийский)', 'is_correct' => true],
                    ['text' => 'Эльфийский', 'is_correct' => false],
                    ['text' => 'Клингонский', 'is_correct' => false],
                    ['text' => 'Мордорский', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Rolling in the Deep"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Adele', 'is_correct' => true],
                    ['text' => 'Amy Winehouse', 'is_correct' => false],
                    ['text' => 'Sia', 'is_correct' => false],
                    ['text' => 'Beyonce', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме звучит фраза "Да пребудет с тобой Сила"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Звездные войны', 'is_correct' => true],
                    ['text' => 'Звездный путь', 'is_correct' => false],
                    ['text' => 'Стражи Галактики', 'is_correct' => false],
                    ['text' => 'Матрица', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут сестру Эльзы в мультфильме "Холодное сердце"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Анна', 'is_correct' => true],
                    ['text' => 'Рапунцель', 'is_correct' => false],
                    ['text' => 'Мерида', 'is_correct' => false],
                    ['text' => 'Моана', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является автором книг о Шерлоке Холмсе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Артур Конан Дойл', 'is_correct' => true],
                    ['text' => 'Агата Кристи', 'is_correct' => false],
                    ['text' => 'Жюль Верн', 'is_correct' => false],
                    ['text' => 'Эдгар Аллан По', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой рэпер известен как Slim Shady?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Eminem', 'is_correct' => true],
                    ['text' => '50 Cent', 'is_correct' => false],
                    ['text' => 'Snoop Dogg', 'is_correct' => false],
                    ['text' => 'Jay-Z', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется город, где живет Спанч Боб?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бикини Боттом', 'is_correct' => true],
                    ['text' => 'Атлантида', 'is_correct' => false],
                    ['text' => 'Спрингфилд', 'is_correct' => false],
                    ['text' => 'Южный Парк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Ведьмака в сериале Netflix (1 сезон)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Генри Кавилл', 'is_correct' => true],
                    ['text' => 'Лиам Хемсворт', 'is_correct' => false],
                    ['text' => 'Джейсон Момоа', 'is_correct' => false],
                    ['text' => 'Кит Харингтон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Yellow Submarine"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'The Beatles', 'is_correct' => true],
                    ['text' => 'The Rolling Stones', 'is_correct' => false],
                    ['text' => 'Pink Floyd', 'is_correct' => false],
                    ['text' => 'Led Zeppelin', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме Леонардо ДиКаприо наконец получил Оскар?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Выживший', 'is_correct' => true],
                    ['text' => 'Волк с Уолл-стрит', 'is_correct' => false],
                    ['text' => 'Титаник', 'is_correct' => false],
                    ['text' => 'Начало', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут робота из фильма "ВАЛЛ-И"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'ВАЛЛ-И', 'is_correct' => true],
                    ['text' => 'ЕВА', 'is_correct' => false],
                    ['text' => 'Р2-Д2', 'is_correct' => false],
                    ['text' => 'ББ-8', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Властелин колец"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дж. Р. Р. Толкин', 'is_correct' => true],
                    ['text' => 'К. С. Льюис', 'is_correct' => false],
                    ['text' => 'Джордж Р. Р. Мартин', 'is_correct' => false],
                    ['text' => 'Джоан Роулинг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая актриса сыграла Черную Вдову в фильмах Marvel?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Скарлетт Йоханссон', 'is_correct' => true],
                    ['text' => 'Элизабет Олсен', 'is_correct' => false],
                    ['text' => 'Натали Портман', 'is_correct' => false],
                    ['text' => 'Дженнифер Лоуренс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа, исполнившая "Highway to Hell"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'AC/DC', 'is_correct' => true],
                    ['text' => 'Guns N\' Roses', 'is_correct' => false],
                    ['text' => 'Kiss', 'is_correct' => false],
                    ['text' => 'Aerosmith', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является режиссером фильма "Начало"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кристофер Нолан', 'is_correct' => true],
                    ['text' => 'Дэвид Финчер', 'is_correct' => false],
                    ['text' => 'Квентин Тарантино', 'is_correct' => false],
                    ['text' => 'Ридли Скотт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного злодея в фильме "Мстители: Война бесконечности"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Танос', 'is_correct' => true],
                    ['text' => 'Локи', 'is_correct' => false],
                    ['text' => 'Альтрон', 'is_correct' => false],
                    ['text' => 'Галактус', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть "Ходячие мертвецы"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ходячие мертвецы', 'is_correct' => true],
                    ['text' => 'Игра престолов', 'is_correct' => false],
                    ['text' => 'Во все тяжкие', 'is_correct' => false],
                    ['text' => 'Остаться в живых', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто спел "I Will Always Love You"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Уитни Хьюстон', 'is_correct' => true],
                    ['text' => 'Селин Дион', 'is_correct' => false],
                    ['text' => 'Мэрайя Кэри', 'is_correct' => false],
                    ['text' => 'Тина Тернер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется медведь в "Книге джунглей"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Балу', 'is_correct' => true],
                    ['text' => 'Багира', 'is_correct' => false],
                    ['text' => 'Шерхан', 'is_correct' => false],
                    ['text' => 'Каа', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году вышел первый iPhone?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '2007', 'is_correct' => true],
                    ['text' => '2005', 'is_correct' => false],
                    ['text' => '2008', 'is_correct' => false],
                    ['text' => '2006', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл капитана Джека Воробья?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джонни Депп', 'is_correct' => true],
                    ['text' => 'Орландо Блум', 'is_correct' => false],
                    ['text' => 'Джеффри Раш', 'is_correct' => false],
                    ['text' => 'Хавьер Бардем', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется планета в фильме "Дюна"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Арракис', 'is_correct' => true],
                    ['text' => 'Татуин', 'is_correct' => false],
                    ['text' => 'Пандора', 'is_correct' => false],
                    ['text' => 'Вулкан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Thriller"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Майкл Джексон', 'is_correct' => true],
                    ['text' => 'Принс', 'is_correct' => false],
                    ['text' => 'Элвис Пресли', 'is_correct' => false],
                    ['text' => 'Дэвид Боуи', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть персонаж Голлум?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Властелин колец', 'is_correct' => true],
                    ['text' => 'Гарри Поттер', 'is_correct' => false],
                    ['text' => 'Звездные войны', 'is_correct' => false],
                    ['text' => 'Хроники Нарнии', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут жену Гомера Симпсона?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мардж', 'is_correct' => true],
                    ['text' => 'Лиза', 'is_correct' => false],
                    ['text' => 'Мэгги', 'is_correct' => false],
                    ['text' => 'Пэтти', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа записала альбом "Abbey Road"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'The Beatles', 'is_correct' => true],
                    ['text' => 'The Rolling Stones', 'is_correct' => false],
                    ['text' => 'Queen', 'is_correct' => false],
                    ['text' => 'Pink Floyd', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Чудо-женщины?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Галь Гадот', 'is_correct' => true],
                    ['text' => 'Марго Робби', 'is_correct' => false],
                    ['text' => 'Эми Адамс', 'is_correct' => false],
                    ['text' => 'Бри Ларсон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленный металл в Ваканде?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Вибраниум', 'is_correct' => true],
                    ['text' => 'Адамантий', 'is_correct' => false],
                    ['text' => 'Мифрил', 'is_correct' => false],
                    ['text' => 'Криптонит', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Мастер и Маргарита"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Михаил Булгаков', 'is_correct' => true],
                    ['text' => 'Федор Достоевский', 'is_correct' => false],
                    ['text' => 'Лев Толстой', 'is_correct' => false],
                    ['text' => 'Николай Гоголь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой актер озвучил Джинна в мультфильме "Аладдин" (1992)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Робин Уильямс', 'is_correct' => true],
                    ['text' => 'Уилл Смит', 'is_correct' => false],
                    ['text' => 'Эдди Мерфи', 'is_correct' => false],
                    ['text' => 'Джим Керри', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть персонаж Одиннадцать (Eleven)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Очень странные дела', 'is_correct' => true],
                    ['text' => 'Тьма', 'is_correct' => false],
                    ['text' => 'Черное зеркало', 'is_correct' => false],
                    ['text' => 'Ривердейл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Wrecking Ball"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Miley Cyrus', 'is_correct' => true],
                    ['text' => 'Demi Lovato', 'is_correct' => false],
                    ['text' => 'Selena Gomez', 'is_correct' => false],
                    ['text' => 'Ariana Grande', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется корабль в фильме "Чужой"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Ностромо', 'is_correct' => true],
                    ['text' => 'Прометей', 'is_correct' => false],
                    ['text' => 'Завет', 'is_correct' => false],
                    ['text' => 'Сулако', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является автором "Звездного пути" (Star Trek)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Джин Родденберри', 'is_correct' => true],
                    ['text' => 'Джордж Лукас', 'is_correct' => false],
                    ['text' => 'Стэн Ли', 'is_correct' => false],
                    ['text' => 'Ридли Скотт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме Брэд Питт играет Тайлера Дердена?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Бойцовский клуб', 'is_correct' => true],
                    ['text' => 'Семь', 'is_correct' => false],
                    ['text' => 'Большой куш', 'is_correct' => false],
                    ['text' => 'Троя', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя в фильме "Маска"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Стэнли Ипкисс', 'is_correct' => true],
                    ['text' => 'Ллойд Кристмас', 'is_correct' => false],
                    ['text' => 'Эйс Вентура', 'is_correct' => false],
                    ['text' => 'Брюс Нолан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет песню "Havana"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Camila Cabello', 'is_correct' => true],
                    ['text' => 'Jennifer Lopez', 'is_correct' => false],
                    ['text' => 'Shakira', 'is_correct' => false],
                    ['text' => 'Cardi B', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая актриса сыграла Барби в фильме 2023 года?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марго Робби', 'is_correct' => true],
                    ['text' => 'Эмма Стоун', 'is_correct' => false],
                    ['text' => 'Дженнифер Лоуренс', 'is_correct' => false],
                    ['text' => 'Флоренс Пью', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется город в сериале "Сверхъестественное"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Лоуренс (Канзас)', 'is_correct' => true],
                    ['text' => 'Мистик Фоллс', 'is_correct' => false],
                    ['text' => 'Бикон Хиллс', 'is_correct' => false],
                    ['text' => 'Саннидейл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто выиграл Евровидение 2021?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Måneskin', 'is_correct' => true],
                    ['text' => 'Duncan Laurence', 'is_correct' => false],
                    ['text' => 'Netta', 'is_correct' => false],
                    ['text' => 'Loreen', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме звучит песня "My Heart Will Go On"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Титаник', 'is_correct' => true],
                    ['text' => 'Телохранитель', 'is_correct' => false],
                    ['text' => 'Красотка', 'is_correct' => false],
                    ['text' => 'Привидение', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вселенная, в которой живут Супермен и Бэтмен?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'DC', 'is_correct' => true],
                    ['text' => 'Marvel', 'is_correct' => false],
                    ['text' => 'Dark Horse', 'is_correct' => false],
                    ['text' => 'Image', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Гарри Поттера?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Дэниел Рэдклифф', 'is_correct' => true],
                    ['text' => 'Руперт Гринт', 'is_correct' => false],
                    ['text' => 'Том Фелтон', 'is_correct' => false],
                    ['text' => 'Роберт Паттинсон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Stairway to Heaven"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Led Zeppelin', 'is_correct' => true],
                    ['text' => 'Deep Purple', 'is_correct' => false],
                    ['text' => 'Black Sabbath', 'is_correct' => false],
                    ['text' => 'The Doors', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме главную роль сыграл Киллиан Мерфи (2023)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Оппенгеймер', 'is_correct' => true],
                    ['text' => 'Барби', 'is_correct' => false],
                    ['text' => 'Дюна', 'is_correct' => false],
                    ['text' => 'Наполеон', 'is_correct' => false],
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
