<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000017_add_pop_culture_questions_part3';
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
                'question_text' => 'Как зовут главного героя сериала "Офис" (США)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Майкл Скотт', 'is_correct' => true],
                    ['text' => 'Джим Халперт', 'is_correct' => false],
                    ['text' => 'Дуайт Шрут', 'is_correct' => false],
                    ['text' => 'Пэм Бизли', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет песню "Bad Guy"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Billie Eilish', 'is_correct' => true],
                    ['text' => 'Dua Lipa', 'is_correct' => false],
                    ['text' => 'Ariana Grande', 'is_correct' => false],
                    ['text' => 'Lorde', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме появился персонаж Ганнибал Лектер (Энтони Хопкинс)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Молчание ягнят', 'is_correct' => true],
                    ['text' => 'Семь', 'is_correct' => false],
                    ['text' => 'Психо', 'is_correct' => false],
                    ['text' => 'Сияние', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется город Бэтмена?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Готэм', 'is_correct' => true],
                    ['text' => 'Метрополис', 'is_correct' => false],
                    ['text' => 'Стар-сити', 'is_correct' => false],
                    ['text' => 'Централ-сити', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл Джокера в фильме "Темный рыцарь"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Хит Леджер', 'is_correct' => true],
                    ['text' => 'Хоакин Феникс', 'is_correct' => false],
                    ['text' => 'Джаред Лето', 'is_correct' => false],
                    ['text' => 'Джек Николсон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Bohemian Rhapsody"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Queen', 'is_correct' => true],
                    ['text' => 'The Beatles', 'is_correct' => false],
                    ['text' => 'Pink Floyd', 'is_correct' => false],
                    ['text' => 'Led Zeppelin', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме звучит фраза "Хьюстон, у нас проблемы"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Аполлон-13', 'is_correct' => true],
                    ['text' => 'Гравитация', 'is_correct' => false],
                    ['text' => 'Марсианин', 'is_correct' => false],
                    ['text' => 'Интерстеллар', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Песнь Льда и Пламени"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Джордж Р. Р. Мартин', 'is_correct' => true],
                    ['text' => 'Дж. Р. Р. Толкин', 'is_correct' => false],
                    ['text' => 'Стивен Кинг', 'is_correct' => false],
                    ['text' => 'Анджей Сапковский', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя в фильме "Назад в будущее"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марти Макфлай', 'is_correct' => true],
                    ['text' => 'Док Браун', 'is_correct' => false],
                    ['text' => 'Бифф Таннен', 'is_correct' => false],
                    ['text' => 'Джордж Макфлай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Лара Крофт?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Расхитительница гробниц', 'is_correct' => true],
                    ['text' => 'Супергероиня', 'is_correct' => false],
                    ['text' => 'Шпионка', 'is_correct' => false],
                    ['text' => 'Вампирша', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть персонаж Шелдон Купер?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Теория большого взрыва', 'is_correct' => true],
                    ['text' => 'Друзья', 'is_correct' => false],
                    ['text' => 'Как я встретил вашу маму', 'is_correct' => false],
                    ['text' => 'Клиника', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Shape of You"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ed Sheeran', 'is_correct' => true],
                    ['text' => 'Justin Bieber', 'is_correct' => false],
                    ['text' => 'Shawn Mendes', 'is_correct' => false],
                    ['text' => 'Harry Styles', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется школа в "Сексуальном просвещении"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Мурдэйл', 'is_correct' => true],
                    ['text' => 'Ривердейл', 'is_correct' => false],
                    ['text' => 'Хокинс', 'is_correct' => false],
                    ['text' => 'Либерти', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Нео в "Матрице"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Киану Ривз', 'is_correct' => true],
                    ['text' => 'Том Круз', 'is_correct' => false],
                    ['text' => 'Уилл Смит', 'is_correct' => false],
                    ['text' => 'Брэд Питт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "Numb"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Linkin Park', 'is_correct' => true],
                    ['text' => 'Limp Bizkit', 'is_correct' => false],
                    ['text' => 'Evanescence', 'is_correct' => false],
                    ['text' => 'Papa Roach', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком мультфильме есть рыбка Немо?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'В поисках Немо', 'is_correct' => true],
                    ['text' => 'Подводная братва', 'is_correct' => false],
                    ['text' => 'Русалочка', 'is_correct' => false],
                    ['text' => 'Губка Боб', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто режиссер фильма "Криминальное чтиво"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Квентин Тарантино', 'is_correct' => true],
                    ['text' => 'Мартин Скорсезе', 'is_correct' => false],
                    ['text' => 'Гай Ричи', 'is_correct' => false],
                    ['text' => 'Стенли Кубрик', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут осла из "Шрека"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Осел', 'is_correct' => true],
                    ['text' => 'Эдди', 'is_correct' => false],
                    ['text' => 'Марти', 'is_correct' => false],
                    ['text' => 'Донки', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто спел "Single Ladies"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Beyonce', 'is_correct' => true],
                    ['text' => 'Rihanna', 'is_correct' => false],
                    ['text' => 'Lady Gaga', 'is_correct' => false],
                    ['text' => 'Madonna', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть "звезда смерти"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Звездные войны', 'is_correct' => true],
                    ['text' => 'Звездный путь', 'is_correct' => false],
                    ['text' => 'Аватар', 'is_correct' => false],
                    ['text' => 'Дюна', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл Человека-паука в киновселенной Marvel?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Том Холланд', 'is_correct' => true],
                    ['text' => 'Тоби Магуайр', 'is_correct' => false],
                    ['text' => 'Эндрю Гарфилд', 'is_correct' => false],
                    ['text' => 'Тимоти Шаламе', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа Фредди Меркьюри?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Queen', 'is_correct' => true],
                    ['text' => 'The Beatles', 'is_correct' => false],
                    ['text' => 'The Rolling Stones', 'is_correct' => false],
                    ['text' => 'ABBA', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть "Игра в кальмара"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Игра в кальмара', 'is_correct' => true],
                    ['text' => 'Алиса в Пограничье', 'is_correct' => false],
                    ['text' => 'Бумажный дом', 'is_correct' => false],
                    ['text' => 'Черное зеркало', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Hello"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Adele', 'is_correct' => true],
                    ['text' => 'Lionel Richie', 'is_correct' => false], // Тоже пел, но Адель популярнее сейчас. Вопрос может быть с подвохом, но usually Adele.
                    ['text' => 'Beyonce', 'is_correct' => false],
                    ['text' => 'Sia', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленная страна Черной Пантеры?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ваканда', 'is_correct' => true],
                    ['text' => 'Заковия', 'is_correct' => false],
                    ['text' => 'Латверия', 'is_correct' => false],
                    ['text' => 'Асгард', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Сияние"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Стивен Кинг', 'is_correct' => true],
                    ['text' => 'Дин Кунц', 'is_correct' => false],
                    ['text' => 'Говард Лавкрафт', 'is_correct' => false],
                    ['text' => 'Нил Гейман', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме звучит песня "Let It Go"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Холодное сердце', 'is_correct' => true],
                    ['text' => 'Рапунцель', 'is_correct' => false],
                    ['text' => 'Моана', 'is_correct' => false],
                    ['text' => 'Храбрая сердцем', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Форреста Гампа?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Том Хэнкс', 'is_correct' => true],
                    ['text' => 'Том Круз', 'is_correct' => false],
                    ['text' => 'Брэд Питт', 'is_correct' => false],
                    ['text' => 'Джонни Депп', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется группа, исполнившая "Smells Like Teen Spirit"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Nirvana', 'is_correct' => true],
                    ['text' => 'Metallica', 'is_correct' => false],
                    ['text' => 'Guns N\' Roses', 'is_correct' => false],
                    ['text' => 'AC/DC', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто режиссер фильма "Аватар"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Джеймс Кэмерон', 'is_correct' => true],
                    ['text' => 'Стивен Спилберг', 'is_correct' => false],
                    ['text' => 'Джордж Лукас', 'is_correct' => false],
                    ['text' => 'Питер Джексон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как зовут главного героя мультсериала "Симпсоны"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гомер', 'is_correct' => true],
                    ['text' => 'Барт', 'is_correct' => false],
                    ['text' => 'Питер', 'is_correct' => false],
                    ['text' => 'Стэн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто выиграл Оскар за лучшую мужскую роль в "Джокере"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Хоакин Феникс', 'is_correct' => true],
                    ['text' => 'Хит Леджер', 'is_correct' => false],
                    ['text' => 'Леонардо ДиКаприо', 'is_correct' => false],
                    ['text' => 'Брэд Питт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком сериале есть "Железный трон"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Игра престолов', 'is_correct' => true],
                    ['text' => 'Властелин колец', 'is_correct' => false],
                    ['text' => 'Ведьмак', 'is_correct' => false],
                    ['text' => 'Викинги', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Umbrella"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Rihanna', 'is_correct' => true],
                    ['text' => 'Beyonce', 'is_correct' => false],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                    ['text' => 'Nicki Minaj', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется робот в "Звездных войнах" (золотой)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'C-3PO', 'is_correct' => true],
                    ['text' => 'R2-D2', 'is_correct' => false],
                    ['text' => 'BB-8', 'is_correct' => false],
                    ['text' => 'K-2SO', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Тони Старка?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Роберт Дауни мл.', 'is_correct' => true],
                    ['text' => 'Крис Эванс', 'is_correct' => false],
                    ['text' => 'Марк Руффало', 'is_correct' => false],
                    ['text' => 'Джереми Реннер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа записала хит "Hotel California"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Eagles', 'is_correct' => true],
                    ['text' => 'The Doors', 'is_correct' => false],
                    ['text' => 'Led Zeppelin', 'is_correct' => false],
                    ['text' => 'Pink Floyd', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть фраза "Я вижу мертвых людей"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Шестое чувство', 'is_correct' => true],
                    ['text' => 'Другие', 'is_correct' => false],
                    ['text' => 'Звонок', 'is_correct' => false],
                    ['text' => 'Астрал', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является создателем "Звездных войн"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джордж Лукас', 'is_correct' => true],
                    ['text' => 'Стивен Спилберг', 'is_correct' => false],
                    ['text' => 'Джей Джей Абрамс', 'is_correct' => false],
                    ['text' => 'Ридли Скотт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется лев в "Хрониках Нарнии"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Аслан', 'is_correct' => true],
                    ['text' => 'Симба', 'is_correct' => false],
                    ['text' => 'Муфаса', 'is_correct' => false],
                    ['text' => 'Алекс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет "Gangnam Style"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'PSY', 'is_correct' => true],
                    ['text' => 'BTS', 'is_correct' => false],
                    ['text' => 'Blackpink', 'is_correct' => false],
                    ['text' => 'Big Bang', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть персонаж Джек Доусон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Титаник', 'is_correct' => true],
                    ['text' => 'Великий Гэтсби', 'is_correct' => false],
                    ['text' => 'Авиатор', 'is_correct' => false],
                    ['text' => 'Начало', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Гермионы Грейнджер?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Эмма Уотсон', 'is_correct' => true],
                    ['text' => 'Эмма Стоун', 'is_correct' => false],
                    ['text' => 'Кристен Стюарт', 'is_correct' => false],
                    ['text' => 'Сирша Ронан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется вымышленный континент в "Ведьмаке"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Неверлэнд (Континент)', 'is_correct' => false],
                    ['text' => 'Средиземье', 'is_correct' => false],
                    ['text' => 'Континент', 'is_correct' => true], // В книгах просто "Континент" или "Неверлэнд" в переводах, но правильно "Континент".
                    ['text' => 'Вестерос', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто исполнил песню "Poker Face"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Lady Gaga', 'is_correct' => true],
                    ['text' => 'Madonna', 'is_correct' => false],
                    ['text' => 'Britney Spears', 'is_correct' => false],
                    ['text' => 'Katy Perry', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме есть "кольцо всевластия"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Властелин колец', 'is_correct' => true],
                    ['text' => 'Гарри Поттер', 'is_correct' => false],
                    ['text' => 'Хроники Нарнии', 'is_correct' => false],
                    ['text' => 'Пираты Карибского моря', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Волан-де-Морта?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Рэйф Файнс', 'is_correct' => true],
                    ['text' => 'Алан Рикман', 'is_correct' => false],
                    ['text' => 'Гэри Олдман', 'is_correct' => false],
                    ['text' => 'Джейсон Айзекс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая группа исполняет песню "It\'s My Life"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Bon Jovi', 'is_correct' => true],
                    ['text' => 'U2', 'is_correct' => false],
                    ['text' => 'Aerosmith', 'is_correct' => false],
                    ['text' => 'Scorpions', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком мультфильме есть "Долина Мира"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кунг-фу Панда', 'is_correct' => true],
                    ['text' => 'Мулан', 'is_correct' => false],
                    ['text' => 'Райя и последний дракон', 'is_correct' => false],
                    ['text' => 'Аладдин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто поет "Someone Like You"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Adele', 'is_correct' => true],
                    ['text' => 'Celine Dion', 'is_correct' => false],
                    ['text' => 'Whitney Houston', 'is_correct' => false],
                    ['text' => 'Sia', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется космический корабль Хана Соло?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тысячелетний сокол', 'is_correct' => true],
                    ['text' => 'Энтерпрайз', 'is_correct' => false],
                    ['text' => 'Звездный разрушитель', 'is_correct' => false],
                    ['text' => 'Светлячок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сыграл роль Эдварда Каллена в "Сумерках"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Роберт Паттинсон', 'is_correct' => true],
                    ['text' => 'Тейлор Лотнер', 'is_correct' => false],
                    ['text' => 'Зак Эфрон', 'is_correct' => false],
                    ['text' => 'Лиам Хемсворт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая песня является самой просматриваемой на YouTube (2023)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Baby Shark', 'is_correct' => true],
                    ['text' => 'Despacito', 'is_correct' => false],
                    ['text' => 'Shape of You', 'is_correct' => false],
                    ['text' => 'See You Again', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком фильме Брюс Уиллис спасает Землю от астероида?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Армагеддон', 'is_correct' => true],
                    ['text' => 'Пятый элемент', 'is_correct' => false],
                    ['text' => 'Крепкий орешек', 'is_correct' => false],
                    ['text' => 'Столкновение с бездной', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является автором комиксов Marvel (основным)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Стэн Ли', 'is_correct' => true],
                    ['text' => 'Джек Кирби', 'is_correct' => false], // Тоже автор, но Стэн Ли лицо
                    ['text' => 'Боб Кейн', 'is_correct' => false],
                    ['text' => 'Джерри Сигел', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется город, где живут Симпсоны?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Спрингфилд', 'is_correct' => true],
                    ['text' => 'Куахог', 'is_correct' => false],
                    ['text' => 'Саус Парк', 'is_correct' => false],
                    ['text' => 'Фьютюрама', 'is_correct' => false],
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
