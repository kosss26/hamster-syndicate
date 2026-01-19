<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000022_add_extra_questions_history';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'История')->first();
        
        if (!$category) {
            echo "Category 'История' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Кто был первым президентом США?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Джордж Вашингтон', 'is_correct' => true],
                    ['text' => 'Томас Джефферсон', 'is_correct' => false],
                    ['text' => 'Авраам Линкольн', 'is_correct' => false],
                    ['text' => 'Джон Адамс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году началась Вторая мировая война?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '1939', 'is_correct' => true],
                    ['text' => '1941', 'is_correct' => false],
                    ['text' => '1945', 'is_correct' => false],
                    ['text' => '1914', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая империя построила Колизей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Римская империя', 'is_correct' => true],
                    ['text' => 'Греческая империя', 'is_correct' => false],
                    ['text' => 'Османская империя', 'is_correct' => false],
                    ['text' => 'Персидская империя', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл Америку в 1492 году?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Христофор Колумб', 'is_correct' => true],
                    ['text' => 'Васко да Гама', 'is_correct' => false],
                    ['text' => 'Фернан Магеллан', 'is_correct' => false],
                    ['text' => 'Америго Веспуччи', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как звали последнего царя России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Николай II', 'is_correct' => true],
                    ['text' => 'Александр III', 'is_correct' => false],
                    ['text' => 'Петр I', 'is_correct' => false],
                    ['text' => 'Иван Грозный', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Мону Лизу"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Леонардо да Винчи', 'is_correct' => true],
                    ['text' => 'Микеланджело', 'is_correct' => false],
                    ['text' => 'Рафаэль', 'is_correct' => false],
                    ['text' => 'Ван Гог', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году Гагарин полетел в космос?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '1961', 'is_correct' => true],
                    ['text' => '1957', 'is_correct' => false],
                    ['text' => '1969', 'is_correct' => false],
                    ['text' => '1975', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна подарила США Статую Свободы?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Франция', 'is_correct' => true],
                    ['text' => 'Англия', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым императором Рима?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Октавиан Август', 'is_correct' => true],
                    ['text' => 'Юлий Цезарь', 'is_correct' => false],
                    ['text' => 'Нерон', 'is_correct' => false],
                    ['text' => 'Траян', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году распался СССР?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '1991', 'is_correct' => true],
                    ['text' => '1989', 'is_correct' => false],
                    ['text' => '1993', 'is_correct' => false],
                    ['text' => '1985', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел лампочку (коммерческую)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Томас Эдисон', 'is_correct' => true],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Александр Белл', 'is_correct' => false],
                    ['text' => 'Майкл Фарадей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как назывался корабль Титаник?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Титаник', 'is_correct' => true],
                    ['text' => 'Олимпик', 'is_correct' => false],
                    ['text' => 'Британик', 'is_correct' => false],
                    ['text' => 'Лузитания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто командовал французской армией в 1812 году?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Наполеон Бонапарт', 'is_correct' => true],
                    ['text' => 'Людовик XVI', 'is_correct' => false],
                    ['text' => 'Шарль де Голль', 'is_correct' => false],
                    ['text' => 'Жанна д\'Арк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая война длилась 100 лет?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Столетняя война', 'is_correct' => true],
                    ['text' => 'Тридцатилетняя война', 'is_correct' => false],
                    ['text' => 'Семилетняя война', 'is_correct' => false],
                    ['text' => 'Война Алой и Белой розы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто основал Санкт-Петербург?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Петр I', 'is_correct' => true],
                    ['text' => 'Екатерина II', 'is_correct' => false],
                    ['text' => 'Александр I', 'is_correct' => false],
                    ['text' => 'Николай I', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году произошло Крещение Руси?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '988', 'is_correct' => true],
                    ['text' => '1000', 'is_correct' => false],
                    ['text' => '862', 'is_correct' => false],
                    ['text' => '1147', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто придумал теорию относительности?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Альберт Эйнштейн', 'is_correct' => true],
                    ['text' => 'Исаак Ньютон', 'is_correct' => false],
                    ['text' => 'Стивен Хокинг', 'is_correct' => false],
                    ['text' => 'Нильс Бор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая цивилизация построила пирамиды?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Древний Египет', 'is_correct' => true],
                    ['text' => 'Майя', 'is_correct' => false], // Тоже строили, но Египет каноничнее для "пирамид" без уточнения
                    ['text' => 'Ацтеки', 'is_correct' => false],
                    ['text' => 'Шумеры', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым человеком на Луне?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нил Армстронг', 'is_correct' => true],
                    ['text' => 'Юрий Гагарин', 'is_correct' => false],
                    ['text' => 'Базз Олдрин', 'is_correct' => false],
                    ['text' => 'Майкл Коллинз', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране произошла Чернобыльская катастрофа?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'СССР (Украина)', 'is_correct' => true],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'Беларусь', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Войну и мир"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лев Толстой', 'is_correct' => true],
                    ['text' => 'Федор Достоевский', 'is_correct' => false],
                    ['text' => 'Александр Пушкин', 'is_correct' => false],
                    ['text' => 'Антон Чехов', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город был столицей Римской империи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рим', 'is_correct' => true],
                    ['text' => 'Афины', 'is_correct' => false],
                    ['text' => 'Константинополь', 'is_correct' => false],
                    ['text' => 'Карфаген', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел печатный станок?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Иоганн Гутенберг', 'is_correct' => true],
                    ['text' => 'Леонардо да Винчи', 'is_correct' => false],
                    ['text' => 'Иван Федоров', 'is_correct' => false],
                    ['text' => 'Мартин Лютер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году закончилась Первая мировая война?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1918', 'is_correct' => true],
                    ['text' => '1917', 'is_correct' => false],
                    ['text' => '1920', 'is_correct' => false],
                    ['text' => '1914', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Клеопатра?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Царица Египта', 'is_correct' => true],
                    ['text' => 'Богиня Греции', 'is_correct' => false],
                    ['text' => 'Императрица Рима', 'is_correct' => false],
                    ['text' => 'Жена Цезаря', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая стена разделяла Германию?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Берлинская стена', 'is_correct' => true],
                    ['text' => 'Великая Китайская стена', 'is_correct' => false],
                    ['text' => 'Стена Плача', 'is_correct' => false],
                    ['text' => 'Кремлевская стена', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто победил в битве при Ватерлоо?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Коалиция (Веллингтон)', 'is_correct' => true],
                    ['text' => 'Наполеон', 'is_correct' => false],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'Пруссия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году основана Москва?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1147', 'is_correct' => true],
                    ['text' => '862', 'is_correct' => false],
                    ['text' => '1242', 'is_correct' => false],
                    ['text' => '1703', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Чингисхан?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Основатель Монгольской империи', 'is_correct' => true],
                    ['text' => 'Китайский император', 'is_correct' => false],
                    ['text' => 'Японский сёгун', 'is_correct' => false],
                    ['text' => 'Турецкий султан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая религия возникла в Индии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Буддизм (и Индуизм)', 'is_correct' => true],
                    ['text' => 'Ислам', 'is_correct' => false],
                    ['text' => 'Христианство', 'is_correct' => false],
                    ['text' => 'Иудаизм', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сказал "Пришел, увидел, победил"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Юлий Цезарь', 'is_correct' => true],
                    ['text' => 'Александр Македонский', 'is_correct' => false],
                    ['text' => 'Ганнибал', 'is_correct' => false],
                    ['text' => 'Спартак', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году отменили крепостное право в России?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '1861', 'is_correct' => true],
                    ['text' => '1812', 'is_correct' => false],
                    ['text' => '1917', 'is_correct' => false],
                    ['text' => '1703', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был лидером СССР во время ВОВ?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Иосиф Сталин', 'is_correct' => true],
                    ['text' => 'Владимир Ленин', 'is_correct' => false],
                    ['text' => 'Никита Хрущев', 'is_correct' => false],
                    ['text' => 'Леонид Брежнев', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город называли "Вечным городом"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Рим', 'is_correct' => true],
                    ['text' => 'Афины', 'is_correct' => false],
                    ['text' => 'Иерусалим', 'is_correct' => false],
                    ['text' => 'Каир', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел телефон?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Александр Белл', 'is_correct' => true],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Гульельмо Маркони', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое событие произошло 11 сентября 2001 года?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Теракты в США', 'is_correct' => true],
                    ['text' => 'Начало войны в Ираке', 'is_correct' => false],
                    ['text' => 'Падение Берлинской стены', 'is_correct' => false],
                    ['text' => 'Ураган Катрина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто была "Железной леди"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Маргарет Тэтчер', 'is_correct' => true],
                    ['text' => 'Елизавета II', 'is_correct' => false],
                    ['text' => 'Ангела Меркель', 'is_correct' => false],
                    ['text' => 'Индира Ганди', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком веке жил Шекспир?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '16-17 век', 'is_correct' => true],
                    ['text' => '14-15 век', 'is_correct' => false],
                    ['text' => '18-19 век', 'is_correct' => false],
                    ['text' => '12-13 век', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто совершил первое кругосветное путешествие?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Экспедиция Магеллана', 'is_correct' => true],
                    ['text' => 'Христофор Колумб', 'is_correct' => false],
                    ['text' => 'Васко да Гама', 'is_correct' => false],
                    ['text' => 'Фрэнсис Дрейк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась древнегреческая площадь?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Агора', 'is_correct' => true],
                    ['text' => 'Форум', 'is_correct' => false],
                    ['text' => 'Акрополь', 'is_correct' => false],
                    ['text' => 'Колизей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Спартак?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Гладиатор, лидер восстания', 'is_correct' => true],
                    ['text' => 'Римский император', 'is_correct' => false],
                    ['text' => 'Греческий философ', 'is_correct' => false],
                    ['text' => 'Бог войны', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году был Карибский кризис?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '1962', 'is_correct' => true],
                    ['text' => '1950', 'is_correct' => false],
                    ['text' => '1975', 'is_correct' => false],
                    ['text' => '1980', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Мартин Лютер Кинг?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Борец за права чернокожих', 'is_correct' => true],
                    ['text' => 'Президент США', 'is_correct' => false],
                    ['text' => 'Известный певец', 'is_correct' => false],
                    ['text' => 'Основатель религии', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город был столицей Византии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Константинополь', 'is_correct' => true],
                    ['text' => 'Рим', 'is_correct' => false],
                    ['text' => 'Афины', 'is_correct' => false],
                    ['text' => 'Иерусалим', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Юрий Гагарин?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Первый космонавт', 'is_correct' => true],
                    ['text' => 'Советский генсек', 'is_correct' => false],
                    ['text' => 'Великий хоккеист', 'is_correct' => false],
                    ['text' => 'Изобретатель радио', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году Америка объявила независимость?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1776', 'is_correct' => true],
                    ['text' => '1492', 'is_correct' => false],
                    ['text' => '1812', 'is_correct' => false],
                    ['text' => '1865', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто была Жанна д\'Арк?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Героиня Франции', 'is_correct' => true],
                    ['text' => 'Королева Англии', 'is_correct' => false],
                    ['text' => 'Испанская принцесса', 'is_correct' => false],
                    ['text' => 'Жена Наполеона', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая болезнь унесла жизни миллионов в Европе в 14 веке?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Чума (Черная смерть)', 'is_correct' => true],
                    ['text' => 'Холера', 'is_correct' => false],
                    ['text' => 'Оспа', 'is_correct' => false],
                    ['text' => 'Грипп', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такие викинги?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Скандинавские мореплаватели', 'is_correct' => true],
                    ['text' => 'Римские легионеры', 'is_correct' => false],
                    ['text' => 'Монгольские кочевники', 'is_correct' => false],
                    ['text' => 'Американские индейцы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой президент США изображен на 100 долларах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бенджамин Франклин (не президент)', 'is_correct' => true],
                    ['text' => 'Джордж Вашингтон', 'is_correct' => false],
                    ['text' => 'Авраам Линкольн', 'is_correct' => false],
                    ['text' => 'Томас Джефферсон', 'is_correct' => false],
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
