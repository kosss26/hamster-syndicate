<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000007_add_history_questions_part1';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        $categoryId = 1; // История

        $questions = [
            [
                'question_text' => 'В каком году началась Вторая мировая война?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '1939', 'is_correct' => true],
                    ['text' => '1941', 'is_correct' => false],
                    ['text' => '1914', 'is_correct' => false],
                    ['text' => '1945', 'is_correct' => false],
                ]
            ],
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
                'question_text' => 'Как назывался корабль, на котором Колумб открыл Америку (флагман)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Санта-Мария', 'is_correct' => true],
                    ['text' => 'Пинта', 'is_correct' => false],
                    ['text' => 'Нинья', 'is_correct' => false],
                    ['text' => 'Виктория', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая империя построила Колизей?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Римская', 'is_correct' => true],
                    ['text' => 'Греческая', 'is_correct' => false],
                    ['text' => 'Османская', 'is_correct' => false],
                    ['text' => 'Византийская', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Манифест Коммунистической партии"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Карл Маркс и Фридрих Энгельс', 'is_correct' => true],
                    ['text' => 'Владимир Ленин', 'is_correct' => false],
                    ['text' => 'Адам Смит', 'is_correct' => false],
                    ['text' => 'Иосиф Сталин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году произошла Октябрьская революция в России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '1917', 'is_correct' => true],
                    ['text' => '1905', 'is_correct' => false],
                    ['text' => '1918', 'is_correct' => false],
                    ['text' => '1922', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был последним царем России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Николай II', 'is_correct' => true],
                    ['text' => 'Александр III', 'is_correct' => false],
                    ['text' => 'Петр I', 'is_correct' => false],
                    ['text' => 'Иван Грозный', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна подарила США Статую Свободы?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Франция', 'is_correct' => true],
                    ['text' => 'Великобритания', 'is_correct' => false],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл Америку в 1492 году?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Христофор Колумб', 'is_correct' => true],
                    ['text' => 'Америго Веспуччи', 'is_correct' => false],
                    ['text' => 'Васко да Гама', 'is_correct' => false],
                    ['text' => 'Фернан Магеллан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году распался СССР?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1991', 'is_correct' => true],
                    ['text' => '1989', 'is_correct' => false],
                    ['text' => '1993', 'is_correct' => false],
                    ['text' => '1990', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым человеком в космосе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Юрий Гагарин', 'is_correct' => true],
                    ['text' => 'Нил Армстронг', 'is_correct' => false],
                    ['text' => 'Алексей Леонов', 'is_correct' => false],
                    ['text' => 'Джон Гленн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как звали коня Александра Македонского?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Буцефал', 'is_correct' => true],
                    ['text' => 'Росинант', 'is_correct' => false],
                    ['text' => 'Инцитат', 'is_correct' => false],
                    ['text' => 'Пегас', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая битва считается переломным моментом в Великой Отечественной войне?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Сталинградская битва', 'is_correct' => true],
                    ['text' => 'Битва за Москву', 'is_correct' => false],
                    ['text' => 'Курская дуга', 'is_correct' => false],
                    ['text' => 'Блокада Ленинграда', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Мону Лизу"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Леонардо да Винчи', 'is_correct' => true],
                    ['text' => 'Микеланджело', 'is_correct' => false],
                    ['text' => 'Рафаэль', 'is_correct' => false],
                    ['text' => 'Донателло', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году произошла авария на Чернобыльской АЭС?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1986', 'is_correct' => true],
                    ['text' => '1985', 'is_correct' => false],
                    ['text' => '1987', 'is_correct' => false],
                    ['text' => '1989', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто командовал французской армией в битве при Ватерлоо?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Наполеон Бонапарт', 'is_correct' => true],
                    ['text' => 'Людовик XIV', 'is_correct' => false],
                    ['text' => 'Шарль де Голль', 'is_correct' => false],
                    ['text' => 'Жанна д\'Арк', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое древнее чудо света находилось в Александрии?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Маяк', 'is_correct' => true],
                    ['text' => 'Пирамида', 'is_correct' => false],
                    ['text' => 'Сады Семирамиды', 'is_correct' => false],
                    ['text' => 'Колосс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто отменил крепостное право в России?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Александр II', 'is_correct' => true],
                    ['text' => 'Петр I', 'is_correct' => false],
                    ['text' => 'Екатерина II', 'is_correct' => false],
                    ['text' => 'Николай I', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась первая атомная бомба, сброшенная на Хиросиму?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Малыш', 'is_correct' => true],
                    ['text' => 'Толстяк', 'is_correct' => false],
                    ['text' => 'Царь-бомба', 'is_correct' => false],
                    ['text' => 'Тринити', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство первым в мире приняло христианство как государственную религию?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Армения', 'is_correct' => true],
                    ['text' => 'Грузия', 'is_correct' => false],
                    ['text' => 'Римская империя', 'is_correct' => false],
                    ['text' => 'Греция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто основал город Санкт-Петербург?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Петр I', 'is_correct' => true],
                    ['text' => 'Екатерина II', 'is_correct' => false],
                    ['text' => 'Павел I', 'is_correct' => false],
                    ['text' => 'Николай I', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком веке жил Чингисхан?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'XII-XIII', 'is_correct' => true],
                    ['text' => 'X-XI', 'is_correct' => false],
                    ['text' => 'XIV-XV', 'is_correct' => false],
                    ['text' => 'XVI-XVII', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое событие положило начало Первой мировой войне?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Убийство эрцгерцога Фердинанда', 'is_correct' => true],
                    ['text' => 'Нападение на Польшу', 'is_correct' => false],
                    ['text' => 'Бомбардировка Перл-Харбора', 'is_correct' => false],
                    ['text' => 'Октябрьская революция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым императором Рима?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Октавиан Август', 'is_correct' => true],
                    ['text' => 'Юлий Цезарь', 'is_correct' => false],
                    ['text' => 'Нерон', 'is_correct' => false],
                    ['text' => 'Калигула', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая война длилась 100 лет?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Столетняя война', 'is_correct' => true],
                    ['text' => 'Тридцатилетняя война', 'is_correct' => false],
                    ['text' => 'Семилетняя война', 'is_correct' => false],
                    ['text' => 'Война Роз', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Между какими странами шла Столетняя война?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Англия и Франция', 'is_correct' => true],
                    ['text' => 'Франция и Германия', 'is_correct' => false],
                    ['text' => 'Испания и Англия', 'is_correct' => false],
                    ['text' => 'Россия и Швеция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел печатный станок в Европе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Иоганн Гутенберг', 'is_correct' => true],
                    ['text' => 'Леонардо да Винчи', 'is_correct' => false],
                    ['text' => 'Мартин Лютер', 'is_correct' => false],
                    ['text' => 'Николай Коперник', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город был столицей Российской империи до 1918 года?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Петроград (Санкт-Петербург)', 'is_correct' => true],
                    ['text' => 'Москва', 'is_correct' => false],
                    ['text' => 'Киев', 'is_correct' => false],
                    ['text' => 'Новгород', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Жанна д\'Арк?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Национальная героиня Франции', 'is_correct' => true],
                    ['text' => 'Королева Англии', 'is_correct' => false],
                    ['text' => 'Французская писательница', 'is_correct' => false],
                    ['text' => 'Жена Наполеона', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году человек впервые высадился на Луну?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1969', 'is_correct' => true],
                    ['text' => '1961', 'is_correct' => false],
                    ['text' => '1975', 'is_correct' => false],
                    ['text' => '1957', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась древняя египетская письменность?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Иероглифы', 'is_correct' => true],
                    ['text' => 'Клинопись', 'is_correct' => false],
                    ['text' => 'Руны', 'is_correct' => false],
                    ['text' => 'Кириллица', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был лидером нацистской Германии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Адольф Гитлер', 'is_correct' => true],
                    ['text' => 'Бенито Муссолини', 'is_correct' => false],
                    ['text' => 'Уинстон Черчилль', 'is_correct' => false],
                    ['text' => 'Иосиф Сталин', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое событие произошло 12 апреля 1961 года?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Полет Гагарина в космос', 'is_correct' => true],
                    ['text' => 'Высадка на Луну', 'is_correct' => false],
                    ['text' => 'Запуск первого спутника', 'is_correct' => false],
                    ['text' => 'Окончание Второй мировой войны', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой Спартак?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Предводитель восстания рабов', 'is_correct' => true],
                    ['text' => 'Римский император', 'is_correct' => false],
                    ['text' => 'Греческий философ', 'is_correct' => false],
                    ['text' => 'Бог войны', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая династия правила Россией более 300 лет?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Романовы', 'is_correct' => true],
                    ['text' => 'Рюриковичи', 'is_correct' => false],
                    ['text' => 'Бурбоны', 'is_correct' => false],
                    ['text' => 'Габсбурги', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где проходили первые современные Олимпийские игры?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Афины', 'is_correct' => true],
                    ['text' => 'Париж', 'is_correct' => false],
                    ['text' => 'Лондон', 'is_correct' => false],
                    ['text' => 'Рим', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл путь в Индию вокруг Африки?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Васко да Гама', 'is_correct' => true],
                    ['text' => 'Христофор Колумб', 'is_correct' => false],
                    ['text' => 'Фернан Магеллан', 'is_correct' => false],
                    ['text' => 'Бартоломеу Диаш', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась политика расовой сегрегации в ЮАР?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Апартеид', 'is_correct' => true],
                    ['text' => 'Холокост', 'is_correct' => false],
                    ['text' => 'Геноцид', 'is_correct' => false],
                    ['text' => 'Колониализм', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым космонавтом, вышедшим в открытый космос?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Алексей Леонов', 'is_correct' => true],
                    ['text' => 'Юрий Гагарин', 'is_correct' => false],
                    ['text' => 'Нил Армстронг', 'is_correct' => false],
                    ['text' => 'Валентина Терешкова', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое сражение называют "Ледовое побоище"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Битва на Чудском озере', 'is_correct' => true],
                    ['text' => 'Куликовская битва', 'is_correct' => false],
                    ['text' => 'Полтавская битва', 'is_correct' => false],
                    ['text' => 'Бородинское сражения', 'is_correct' => false],
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
