<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000008_add_science_questions_part1';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        $categoryId = 9; // Наука и технологии

        $questions = [
            [
                'question_text' => 'Какая планета Солнечной системы является самой большой?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Юпитер', 'is_correct' => true],
                    ['text' => 'Сатурн', 'is_correct' => false],
                    ['text' => 'Земля', 'is_correct' => false],
                    ['text' => 'Нептун', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой химический элемент имеет символ "O"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кислород', 'is_correct' => true],
                    ['text' => 'Золото', 'is_correct' => false],
                    ['text' => 'Осмий', 'is_correct' => false],
                    ['text' => 'Олово', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто сформулировал теорию относительности?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Альберт Эйнштейн', 'is_correct' => true],
                    ['text' => 'Исаак Ньютон', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Стивен Хокинг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что измеряется в вольтах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Электрическое напряжение', 'is_correct' => true],
                    ['text' => 'Сила тока', 'is_correct' => false],
                    ['text' => 'Сопротивление', 'is_correct' => false],
                    ['text' => 'Мощность', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета известна как "Красная планета"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марс', 'is_correct' => true],
                    ['text' => 'Венера', 'is_correct' => false],
                    ['text' => 'Меркурий', 'is_correct' => false],
                    ['text' => 'Юпитер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что является структурной единицей живого организма?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Клетка', 'is_correct' => true],
                    ['text' => 'Атом', 'is_correct' => false],
                    ['text' => 'Молекула', 'is_correct' => false],
                    ['text' => 'Орган', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ преобладает в атмосфере Земли?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Азот', 'is_correct' => true],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Углекислый газ', 'is_correct' => false],
                    ['text' => 'Водород', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая формула воды?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'H2O', 'is_correct' => true],
                    ['text' => 'CO2', 'is_correct' => false],
                    ['text' => 'O2', 'is_correct' => false],
                    ['text' => 'H2SO4', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел телефон?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Александр Белл', 'is_correct' => true],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Гульельмо Маркони', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько хромосом у здорового человека?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '46', 'is_correct' => true],
                    ['text' => '42', 'is_correct' => false],
                    ['text' => '48', 'is_correct' => false],
                    ['text' => '23', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая скорость света в вакууме (приблизительно)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '300 000 км/с', 'is_correct' => true],
                    ['text' => '150 000 км/с', 'is_correct' => false],
                    ['text' => '1 000 000 км/с', 'is_correct' => false],
                    ['text' => '330 м/с', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что изучает наука ботаника?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Растения', 'is_correct' => true],
                    ['text' => 'Животных', 'is_correct' => false],
                    ['text' => 'Грибы', 'is_correct' => false],
                    ['text' => 'Бактерии', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой металл является самым легким?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Литий', 'is_correct' => true],
                    ['text' => 'Алюминий', 'is_correct' => false],
                    ['text' => 'Титан', 'is_correct' => false],
                    ['text' => 'Магний', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется процесс превращения воды в пар?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Испарение', 'is_correct' => true],
                    ['text' => 'Конденсация', 'is_correct' => false],
                    ['text' => 'Замерзание', 'is_correct' => false],
                    ['text' => 'Сублимация', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета вращается «лежа на боку»?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Уран', 'is_correct' => true],
                    ['text' => 'Нептун', 'is_correct' => false],
                    ['text' => 'Сатурн', 'is_correct' => false],
                    ['text' => 'Плутон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что является носителем генетической информации?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'ДНК', 'is_correct' => true],
                    ['text' => 'РНК', 'is_correct' => false],
                    ['text' => 'Белок', 'is_correct' => false],
                    ['text' => 'АТФ', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой закон гласит: «На всякое действие есть противодействие»?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Третий закон Ньютона', 'is_correct' => true],
                    ['text' => 'Первый закон Ньютона', 'is_correct' => false],
                    ['text' => 'Второй закон Ньютона', 'is_correct' => false],
                    ['text' => 'Закон Архимеда', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл пенициллин?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Александр Флеминг', 'is_correct' => true],
                    ['text' => 'Луи Пастер', 'is_correct' => false],
                    ['text' => 'Роберт Кох', 'is_correct' => false],
                    ['text' => 'Илья Мечников', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется ближайшая к Солнцу звезда (после Солнца)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Проксима Центавра', 'is_correct' => true],
                    ['text' => 'Сириус', 'is_correct' => false],
                    ['text' => 'Альфа Центавра А', 'is_correct' => false],
                    ['text' => 'Бетельгейзе', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган у человека фильтрует кровь?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Почки', 'is_correct' => true],
                    ['text' => 'Печень', 'is_correct' => false],
                    ['text' => 'Сердце', 'is_correct' => false],
                    ['text' => 'Легкие', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что изучает наука орнитология?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Птиц', 'is_correct' => true],
                    ['text' => 'Насекомых', 'is_correct' => false],
                    ['text' => 'Рыб', 'is_correct' => false],
                    ['text' => 'Рептилий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой металл является жидким при комнатной температуре?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ртуть', 'is_correct' => true],
                    ['text' => 'Галлий', 'is_correct' => false],
                    ['text' => 'Цезий', 'is_correct' => false],
                    ['text' => 'Бром', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется центральная часть атома?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ядро', 'is_correct' => true],
                    ['text' => 'Протон', 'is_correct' => false],
                    ['text' => 'Электрон', 'is_correct' => false],
                    ['text' => 'Нейтрон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета имеет знаменитые кольца?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сатурн', 'is_correct' => true],
                    ['text' => 'Юпитер', 'is_correct' => false],
                    ['text' => 'Уран', 'is_correct' => false],
                    ['text' => 'Марс', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое HTML?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Язык гипертекстовой разметки', 'is_correct' => true],
                    ['text' => 'Протокол передачи данных', 'is_correct' => false],
                    ['text' => 'Язык программирования', 'is_correct' => false],
                    ['text' => 'Операционная система', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто основал компанию Microsoft?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Билл Гейтс', 'is_correct' => true],
                    ['text' => 'Стив Джобс', 'is_correct' => false],
                    ['text' => 'Марк Цукерберг', 'is_correct' => false],
                    ['text' => 'Илон Маск', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой прибор используется для измерения атмосферного давления?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Барометр', 'is_correct' => true],
                    ['text' => 'Термометр', 'is_correct' => false],
                    ['text' => 'Гигрометр', 'is_correct' => false],
                    ['text' => 'Манометр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько бит в одном байте?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '8', 'is_correct' => true],
                    ['text' => '10', 'is_correct' => false],
                    ['text' => '4', 'is_correct' => false],
                    ['text' => '16', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая часть растения отвечает за фотосинтез?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лист', 'is_correct' => true],
                    ['text' => 'Корень', 'is_correct' => false],
                    ['text' => 'Стебель', 'is_correct' => false],
                    ['text' => 'Цветок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой элемент необходим для горения?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Кислород', 'is_correct' => true],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Водород', 'is_correct' => false],
                    ['text' => 'Гелий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел радио?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Александр Попов (или Маркони)', 'is_correct' => true],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                    ['text' => 'Александр Белл', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется наука о грибах?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Микология', 'is_correct' => true],
                    ['text' => 'Ботаника', 'is_correct' => false],
                    ['text' => 'Зоология', 'is_correct' => false],
                    ['text' => 'Экология', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой самый твердый природный материал?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Алмаз', 'is_correct' => true],
                    ['text' => 'Графит', 'is_correct' => false],
                    ['text' => 'Кварц', 'is_correct' => false],
                    ['text' => 'Сталь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета самая горячая в Солнечной системе?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Венера', 'is_correct' => true],
                    ['text' => 'Меркурий', 'is_correct' => false],
                    ['text' => 'Марс', 'is_correct' => false],
                    ['text' => 'Юпитер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что измеряет спидометр?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Скорость', 'is_correct' => true],
                    ['text' => 'Расстояние', 'is_correct' => false],
                    ['text' => 'Время', 'is_correct' => false],
                    ['text' => 'Ускорение', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой витамин вырабатывается в коже под действием солнца?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Витамин D', 'is_correct' => true],
                    ['text' => 'Витамин C', 'is_correct' => false],
                    ['text' => 'Витамин A', 'is_correct' => false],
                    ['text' => 'Витамин B', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Wi-Fi"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Беспроводная сеть', 'is_correct' => true],
                    ['text' => 'Мобильный интернет', 'is_correct' => false],
                    ['text' => 'Кабельный интернет', 'is_correct' => false],
                    ['text' => 'Спутниковая связь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто предложил гелиоцентрическую систему мира?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Николай Коперник', 'is_correct' => true],
                    ['text' => 'Клавдий Птолемей', 'is_correct' => false],
                    ['text' => 'Джордано Бруно', 'is_correct' => false],
                    ['text' => 'Галилео Галилей', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган у человека самый большой?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кожа', 'is_correct' => true],
                    ['text' => 'Печень', 'is_correct' => false],
                    ['text' => 'Мозг', 'is_correct' => false],
                    ['text' => 'Кишечник', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется единица измерения силы тока?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ампер', 'is_correct' => true],
                    ['text' => 'Вольт', 'is_correct' => false],
                    ['text' => 'Ом', 'is_correct' => false],
                    ['text' => 'Ватт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая кислота содержится в желудке человека?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Соляная', 'is_correct' => true],
                    ['text' => 'Серная', 'is_correct' => false],
                    ['text' => 'Уксусная', 'is_correct' => false],
                    ['text' => 'Лимонная', 'is_correct' => false],
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
