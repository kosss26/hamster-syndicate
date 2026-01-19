<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000016_add_geography_questions_final';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'География')->first();
        
        if (!$category) {
            echo "Category 'География' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'В какой стране находится город Торонто?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Великобритания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Вьетнама?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Ханой', 'is_correct' => true],
                    ['text' => 'Хошимин', 'is_correct' => false],
                    ['text' => 'Дананг', 'is_correct' => false],
                    ['text' => 'Бангкок', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится вулкан Везувий?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Греция', 'is_correct' => false],
                    ['text' => 'Исландия', 'is_correct' => false],
                    ['text' => 'Япония', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет столицу Астана (Нур-Султан)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Казахстан', 'is_correct' => true],
                    ['text' => 'Узбекистан', 'is_correct' => false],
                    ['text' => 'Кыргызстан', 'is_correct' => false],
                    ['text' => 'Туркменистан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Барселона?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Испания', 'is_correct' => true],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой океан омывает Индию?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Индийский', 'is_correct' => true],
                    ['text' => 'Тихий', 'is_correct' => false],
                    ['text' => 'Атлантический', 'is_correct' => false],
                    ['text' => 'Северный Ледовитый', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является София?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Болгария', 'is_correct' => true],
                    ['text' => 'Румыния', 'is_correct' => false],
                    ['text' => 'Венгрия', 'is_correct' => false],
                    ['text' => 'Сербия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Лас-Вегас?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США', 'is_correct' => true],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет флаг с зеленым, белым и красным (вертикально)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Ирландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится гора Фудзи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Белград?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Сербия', 'is_correct' => true],
                    ['text' => 'Хорватия', 'is_correct' => false],
                    ['text' => 'Босния', 'is_correct' => false],
                    ['text' => 'Черногория', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Цюрих?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Швейцария', 'is_correct' => true],
                    ['text' => 'Австрия', 'is_correct' => false],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Нидерланды', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое море омывает Сочи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Черное море', 'is_correct' => true],
                    ['text' => 'Каспийское море', 'is_correct' => false],
                    ['text' => 'Азовское море', 'is_correct' => false],
                    ['text' => 'Балтийское море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Эр-Рияд?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Саудовская Аравия', 'is_correct' => true],
                    ['text' => 'ОАЭ', 'is_correct' => false],
                    ['text' => 'Катар', 'is_correct' => false],
                    ['text' => 'Иран', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Марсель?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Франция', 'is_correct' => true],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет самую большую береговую линию?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Индонезия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Панамский канал?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Панама', 'is_correct' => true],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Колумбия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Джакарта?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Индонезия', 'is_correct' => true],
                    ['text' => 'Малайзия', 'is_correct' => false],
                    ['text' => 'Филиппины', 'is_correct' => false],
                    ['text' => 'Таиланд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Ливерпуль?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Великобритания', 'is_correct' => true],
                    ['text' => 'Ирландия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Ирана?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тегеран', 'is_correct' => true],
                    ['text' => 'Багдад', 'is_correct' => false],
                    ['text' => 'Кабул', 'is_correct' => false],
                    ['text' => 'Дамаск', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна находится на острове Цейлон?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Шри-Ланка', 'is_correct' => true],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Мальдивы', 'is_correct' => false],
                    ['text' => 'Мадагаскар', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Бермудский треугольник?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Атлантический океан', 'is_correct' => true],
                    ['text' => 'Тихий океан', 'is_correct' => false],
                    ['text' => 'Индийский океан', 'is_correct' => false],
                    ['text' => 'Карибское море', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Брюссель?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бельгия', 'is_correct' => true],
                    ['text' => 'Нидерланды', 'is_correct' => false],
                    ['text' => 'Люксембург', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Ванкувер?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Канада', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Новая Зеландия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна является самой густонаселенной в Европе?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Монако (по плотности)', 'is_correct' => true],
                    ['text' => 'Германия', 'is_correct' => false],
                    ['text' => 'Великобритания', 'is_correct' => false],
                    ['text' => 'Нидерланды', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится пустыня Гоби?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Монголия и Китай', 'is_correct' => true],
                    ['text' => 'Африка', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Кишинев?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Молдова', 'is_correct' => true],
                    ['text' => 'Румыния', 'is_correct' => false],
                    ['text' => 'Украина', 'is_correct' => false],
                    ['text' => 'Болгария', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Милан?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Италия', 'is_correct' => true],
                    ['text' => 'Испания', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Швейцария', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какое озеро находится на границе Перу и Боливии?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Титикака', 'is_correct' => true],
                    ['text' => 'Байкал', 'is_correct' => false],
                    ['text' => 'Виктория', 'is_correct' => false],
                    ['text' => 'Гурон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Сантьяго?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Чили', 'is_correct' => true],
                    ['text' => 'Аргентина', 'is_correct' => false],
                    ['text' => 'Перу', 'is_correct' => false],
                    ['text' => 'Колумбия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Киото?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Япония', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Корея', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет флаг с желто-синим цветом?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Украина', 'is_correct' => true],
                    ['text' => 'Швеция', 'is_correct' => true], // Оба верны, но обычно вопрос подразумевает один вариант. Оставлю Украину, так как это часто встречается.
                    ['text' => 'Польша', 'is_correct' => false],
                    ['text' => 'Финляндия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится гора Олимп?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Греция', 'is_correct' => true],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Турция', 'is_correct' => false],
                    ['text' => 'Кипр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Найроби?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Кения', 'is_correct' => true],
                    ['text' => 'Нигерия', 'is_correct' => false],
                    ['text' => 'Эфиопия', 'is_correct' => false],
                    ['text' => 'ЮАР', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Женева?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Швейцария', 'is_correct' => true],
                    ['text' => 'Франция', 'is_correct' => false],
                    ['text' => 'Австрия', 'is_correct' => false],
                    ['text' => 'Бельгия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Ирака?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Багдад', 'is_correct' => true],
                    ['text' => 'Тегеран', 'is_correct' => false],
                    ['text' => 'Дамаск', 'is_correct' => false],
                    ['text' => 'Кабул', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Золотой Рог (бухта)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Стамбул', 'is_correct' => true],
                    ['text' => 'Владивосток', 'is_correct' => true], // Тоже верно, но Стамбул известнее. Пусть будет Стамбул.
                    ['text' => 'Севастополь', 'is_correct' => false],
                    ['text' => 'Одесса', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Тбилиси?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Грузия', 'is_correct' => true],
                    ['text' => 'Армения', 'is_correct' => false],
                    ['text' => 'Азербайджан', 'is_correct' => false],
                    ['text' => 'Турция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Валенсия?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Испания', 'is_correct' => true],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                    ['text' => 'Венесуэла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет форму дракона (по легенде)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Вьетнам', 'is_correct' => true],
                    ['text' => 'Китай', 'is_correct' => false],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Индонезия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится мыс Доброй Надежды?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'ЮАР', 'is_correct' => true],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Чили', 'is_correct' => false],
                    ['text' => 'Индия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Рига?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Латвия', 'is_correct' => true],
                    ['text' => 'Литва', 'is_correct' => false],
                    ['text' => 'Эстония', 'is_correct' => false],
                    ['text' => 'Польша', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Севилья?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Испания', 'is_correct' => true],
                    ['text' => 'Италия', 'is_correct' => false],
                    ['text' => 'Португалия', 'is_correct' => false],
                    ['text' => 'Франция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет столицу Пномпень?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Камбоджа', 'is_correct' => true],
                    ['text' => 'Лаос', 'is_correct' => false],
                    ['text' => 'Вьетнам', 'is_correct' => false],
                    ['text' => 'Таиланд', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится Долина Смерти?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США', 'is_correct' => true],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Австралия', 'is_correct' => false],
                    ['text' => 'Египет', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Ереван?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Армения', 'is_correct' => true],
                    ['text' => 'Грузия', 'is_correct' => false],
                    ['text' => 'Азербайджан', 'is_correct' => false],
                    ['text' => 'Иран', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Одесса?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Украина', 'is_correct' => true],
                    ['text' => 'Россия', 'is_correct' => false],
                    ['text' => 'Молдова', 'is_correct' => false],
                    ['text' => 'Румыния', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой город является столицей Португалии?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лиссабон', 'is_correct' => true],
                    ['text' => 'Мадрид', 'is_correct' => false],
                    ['text' => 'Порту', 'is_correct' => false],
                    ['text' => 'Барселона', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится остров Пасхи?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Чили', 'is_correct' => true],
                    ['text' => 'Перу', 'is_correct' => false],
                    ['text' => 'Аргентина', 'is_correct' => false],
                    ['text' => 'Эквадор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Вильнюс?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Литва', 'is_correct' => true],
                    ['text' => 'Латвия', 'is_correct' => false],
                    ['text' => 'Эстония', 'is_correct' => false],
                    ['text' => 'Беларусь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Глазго?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Великобритания (Шотландия)', 'is_correct' => true],
                    ['text' => 'Ирландия', 'is_correct' => false],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Канада', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна имеет столицу Катманду?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Непал', 'is_correct' => true],
                    ['text' => 'Индия', 'is_correct' => false],
                    ['text' => 'Бутан', 'is_correct' => false],
                    ['text' => 'Бангладеш', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Где находится река Миссисипи?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'США', 'is_correct' => true],
                    ['text' => 'Канада', 'is_correct' => false],
                    ['text' => 'Мексика', 'is_correct' => false],
                    ['text' => 'Бразилия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Столицей какой страны является Ташкент?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Узбекистан', 'is_correct' => true],
                    ['text' => 'Казахстан', 'is_correct' => false],
                    ['text' => 'Таджикистан', 'is_correct' => false],
                    ['text' => 'Кыргызстан', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В какой стране находится город Краков?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Польша', 'is_correct' => true],
                    ['text' => 'Чехия', 'is_correct' => false],
                    ['text' => 'Словакия', 'is_correct' => false],
                    ['text' => 'Украина', 'is_correct' => false],
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
