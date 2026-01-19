<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000011_add_history_questions_final';
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
                'question_text' => 'Кто был первым космонавтом, ступившим на Луну?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Нил Армстронг', 'is_correct' => true],
                    ['text' => 'Юрий Гагарин', 'is_correct' => false],
                    ['text' => 'Базз Олдрин', 'is_correct' => false],
                    ['text' => 'Майкл Коллинз', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась "непобедимая" испанская флотилия?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Непобедимая армада', 'is_correct' => true],
                    ['text' => 'Золотой флот', 'is_correct' => false],
                    ['text' => 'Испанская эскадра', 'is_correct' => false],
                    ['text' => 'Королевский флот', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был лидером кубинской революции?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Фидель Кастро', 'is_correct' => true],
                    ['text' => 'Че Гевара', 'is_correct' => false],
                    ['text' => 'Уго Чавес', 'is_correct' => false],
                    ['text' => 'Аугусто Пиночет', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая империя существовала на территории Мексики до прихода испанцев?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ацтеки', 'is_correct' => true],
                    ['text' => 'Инки', 'is_correct' => false],
                    ['text' => 'Майя (частично)', 'is_correct' => false],
                    ['text' => 'Ольмеки', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году пала Берлинская стена?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1989', 'is_correct' => true],
                    ['text' => '1991', 'is_correct' => false],
                    ['text' => '1987', 'is_correct' => false],
                    ['text' => '1990', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым президентом России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Борис Ельцин', 'is_correct' => true],
                    ['text' => 'Михаил Горбачев', 'is_correct' => false],
                    ['text' => 'Владимир Путин', 'is_correct' => false],
                    ['text' => 'Дмитрий Медведев', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как звали жену Николая II?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Александра Федоровна', 'is_correct' => true],
                    ['text' => 'Мария Федоровна', 'is_correct' => false],
                    ['text' => 'Елизавета Алексеевна', 'is_correct' => false],
                    ['text' => 'Екатерина Великая', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел динамит?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Альфред Нобель', 'is_correct' => true],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                    ['text' => 'Александр Белл', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком веке произошла Куликовская битва?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'XIV (1380)', 'is_correct' => true],
                    ['text' => 'XIII (1240)', 'is_correct' => false],
                    ['text' => 'XV (1480)', 'is_correct' => false],
                    ['text' => 'XVI (1550)', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был учителем Александра Македонского?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Аристотель', 'is_correct' => true],
                    ['text' => 'Платон', 'is_correct' => false],
                    ['text' => 'Сократ', 'is_correct' => false],
                    ['text' => 'Диоген', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как назывался первый искусственный спутник Земли?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Спутник-1', 'is_correct' => true],
                    ['text' => 'Восток-1', 'is_correct' => false],
                    ['text' => 'Союз', 'is_correct' => false],
                    ['text' => 'Аполлон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал "Войну и мир"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лев Толстой', 'is_correct' => true],
                    ['text' => 'Федор Достоевский', 'is_correct' => false],
                    ['text' => 'Александр Пушкин', 'is_correct' => false],
                    ['text' => 'Николай Гоголь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое государство возникло в 1948 году?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Израиль', 'is_correct' => true],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Пакистан', 'is_correct' => false],
                    ['text' => 'Китай (КНР)', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был последним фараоном Египта?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Клеопатра', 'is_correct' => true],
                    ['text' => 'Рамзес II', 'is_correct' => false],
                    ['text' => 'Тутанхамон', 'is_correct' => false],
                    ['text' => 'Нефертити', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась первая русская газета?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Ведомости', 'is_correct' => true],
                    ['text' => 'Правда', 'is_correct' => false],
                    ['text' => 'Искра', 'is_correct' => false],
                    ['text' => 'Куранты', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране началась Реформация?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Германия', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Англия', 'is_correct' => false],
                    ['text' => 'Италия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл Антарктиду?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Беллинсгаузен и Лазарев', 'is_correct' => true],
                    ['text' => 'Кук', 'is_correct' => false],
                    ['text' => 'Амундсен', 'is_correct' => false],
                    ['text' => 'Скотт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город был столицей Золотой Орды?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Сарай-Бату', 'is_correct' => true],
                    ['text' => 'Казань', 'is_correct' => false],
                    ['text' => 'Астрахань', 'is_correct' => false],
                    ['text' => 'Бахчисарай', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым канцлером Германии (объединителем)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Отто фон Бисмарк', 'is_correct' => true],
                    ['text' => 'Адольф Гитлер', 'is_correct' => false],
                    ['text' => 'Гельмут Коль', 'is_correct' => false],
                    ['text' => 'Конрад Аденауэр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое событие произошло в 1812 году в России?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Отечественная война (с Наполеоном)', 'is_correct' => true],
                    ['text' => 'Восстание декабристов', 'is_correct' => false],
                    ['text' => 'Отмена крепостного права', 'is_correct' => false],
                    ['text' => 'Крымская война', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто написал картину "Последний день Помпеи"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Карл Брюллов', 'is_correct' => true],
                    ['text' => 'Илья Репин', 'is_correct' => false],
                    ['text' => 'Иван Айвазовский', 'is_correct' => false],
                    ['text' => 'Василий Суриков', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году был основан Рим (по легенде)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => '753 г. до н.э.', 'is_correct' => true],
                    ['text' => '100 г. до н.э.', 'is_correct' => false],
                    ['text' => '500 г. до н.э.', 'is_correct' => false],
                    ['text' => '1 г. н.э.', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Екатерина II?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Императрица России', 'is_correct' => true],
                    ['text' => 'Королева Англии', 'is_correct' => false],
                    ['text' => 'Жена Петра I', 'is_correct' => false],
                    ['text' => 'Французская королева', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая битва положила конец наполеоновским войнам?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Битва при Ватерлоо', 'is_correct' => true],
                    ['text' => 'Бородинское сражение', 'is_correct' => false],
                    ['text' => 'Битва под Лейпцигом', 'is_correct' => false],
                    ['text' => 'Трафальгарское сражение', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был автором Декларации независимости США?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Томас Джефферсон', 'is_correct' => true],
                    ['text' => 'Джордж Вашингтон', 'is_correct' => false],
                    ['text' => 'Бенджамин Франклин', 'is_correct' => false],
                    ['text' => 'Авраам Линкольн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась тайная полиция Ивана Грозного?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Опричнина', 'is_correct' => true],
                    ['text' => 'КГБ', 'is_correct' => false],
                    ['text' => 'Жандармерия', 'is_correct' => false],
                    ['text' => 'Инквизиция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком городе проходил суд над нацистскими преступниками?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Нюрнберг', 'is_correct' => true],
                    ['text' => 'Берлин', 'is_correct' => false],
                    ['text' => 'Мюнхен', 'is_correct' => false],
                    ['text' => 'Гамбург', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел радио (в России)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Александр Попов', 'is_correct' => true],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Гульельмо Маркони', 'is_correct' => false],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна первой в мире дала женщинам право голоса?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Новая Зеландия', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Великобритания', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым президентом СССР?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Михаил Горбачев', 'is_correct' => true],
                    ['text' => 'Борис Ельцин', 'is_correct' => false],
                    ['text' => 'Леонид Брежнев', 'is_correct' => false],
                    ['text' => 'Никита Хрущев', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называлась операция по вторжению Германии в СССР?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Барбаросса', 'is_correct' => true],
                    ['text' => 'Тайфун', 'is_correct' => false],
                    ['text' => 'Цитадель', 'is_correct' => false],
                    ['text' => 'Оверлорд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл Америку для европейцев (викинги не в счет)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Христофор Колумб', 'is_correct' => true],
                    ['text' => 'Америго Веспуччи', 'is_correct' => false],
                    ['text' => 'Васко да Гама', 'is_correct' => false],
                    ['text' => 'Фернан Магеллан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году произошла отмена крепостного права в России?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '1861', 'is_correct' => true],
                    ['text' => '1812', 'is_correct' => false],
                    ['text' => '1905', 'is_correct' => false],
                    ['text' => '1825', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто был первым царем из династии Романовых?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Михаил Федорович', 'is_correct' => true],
                    ['text' => 'Алексей Михайлович', 'is_correct' => false],
                    ['text' => 'Петр I', 'is_correct' => false],
                    ['text' => 'Иван IV', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое чудо света находилось в Вавилоне?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Висячие сады Семирамиды', 'is_correct' => true],
                    ['text' => 'Пирамида Хеопса', 'is_correct' => false],
                    ['text' => 'Статуя Зевса', 'is_correct' => false],
                    ['text' => 'Колосс Родосский', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто командовал советскими войсками в битве за Берлин?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Георгий Жуков', 'is_correct' => true],
                    ['text' => 'Константин Рокоссовский', 'is_correct' => false],
                    ['text' => 'Иван Конев', 'is_correct' => false],
                    ['text' => 'Александр Василевский', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как назывался древнерусский свод законов?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Русская Правда', 'is_correct' => true],
                    ['text' => 'Судебник', 'is_correct' => false],
                    ['text' => 'Соборное уложение', 'is_correct' => false],
                    ['text' => 'Домострой', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такая Мата Хари?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Танцовщица и шпионка', 'is_correct' => true],
                    ['text' => 'Певица', 'is_correct' => false],
                    ['text' => 'Политик', 'is_correct' => false],
                    ['text' => 'Ученая', 'is_correct' => false],
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
