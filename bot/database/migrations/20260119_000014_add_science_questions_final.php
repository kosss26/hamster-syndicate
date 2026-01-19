<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000014_add_science_questions_final';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'Наука и технологии')->first();
        
        if (!$category) {
            echo "Category 'Наука и технологии' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Что измеряется в герцах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Частота', 'is_correct' => true],
                    ['text' => 'Сила тока', 'is_correct' => false],
                    ['text' => 'Напряжение', 'is_correct' => false],
                    ['text' => 'Мощность', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ используют в шариках, чтобы они летали?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гелий', 'is_correct' => true],
                    ['text' => 'Водород', 'is_correct' => false],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Кислород', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл закон всемирного тяготения?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Исаак Ньютон', 'is_correct' => true],
                    ['text' => 'Альберт Эйнштейн', 'is_correct' => false],
                    ['text' => 'Галилео Галилей', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется самая маленькая частица вещества, сохраняющая его свойства?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Молекула', 'is_correct' => true],
                    ['text' => 'Атом', 'is_correct' => false],
                    ['text' => 'Протон', 'is_correct' => false],
                    ['text' => 'Электрон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой элемент обозначается символом Fe?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Железо', 'is_correct' => true],
                    ['text' => 'Фтор', 'is_correct' => false],
                    ['text' => 'Фосфор', 'is_correct' => false],
                    ['text' => 'Франций', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "URL"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Адрес веб-страницы', 'is_correct' => true],
                    ['text' => 'Язык программирования', 'is_correct' => false],
                    ['text' => 'Оперативная память', 'is_correct' => false],
                    ['text' => 'Тип файла', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган управляет всем телом человека?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Мозг', 'is_correct' => true],
                    ['text' => 'Сердце', 'is_correct' => false],
                    ['text' => 'Желудок', 'is_correct' => false],
                    ['text' => 'Печень', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'В каком году был запущен первый iPhone?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '2007', 'is_correct' => true],
                    ['text' => '2005', 'is_correct' => false],
                    ['text' => '2009', 'is_correct' => false],
                    ['text' => '2003', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "CPU" в компьютере?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Процессор', 'is_correct' => true],
                    ['text' => 'Память', 'is_correct' => false],
                    ['text' => 'Видеокарта', 'is_correct' => false],
                    ['text' => 'Жесткий диск', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая температура кипения воды (при нормальном давлении)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '100°C', 'is_correct' => true],
                    ['text' => '90°C', 'is_correct' => false],
                    ['text' => '80°C', 'is_correct' => false],
                    ['text' => '110°C', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел лампу накаливания (коммерчески успешную)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Томас Эдисон', 'is_correct' => true],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Александр Белл', 'is_correct' => false],
                    ['text' => 'Джеймс Ватт', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется наука о звездах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Астрономия', 'is_correct' => true],
                    ['text' => 'Астрология', 'is_correct' => false],
                    ['text' => 'Космология', 'is_correct' => false],
                    ['text' => 'Геология', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой металл самый дорогой (из широко известных)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Платина (или Родий)', 'is_correct' => true],
                    ['text' => 'Золото', 'is_correct' => false],
                    ['text' => 'Серебро', 'is_correct' => false],
                    ['text' => 'Медь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Bluetooth"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Технология беспроводной связи', 'is_correct' => true],
                    ['text' => 'Синий зуб', 'is_correct' => false],
                    ['text' => 'Марка телефона', 'is_correct' => false],
                    ['text' => 'Компьютерный вирус', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ мы выдыхаем?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Углекислый газ', 'is_correct' => true],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Гелий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто основал компанию Tesla?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Илон Маск (сооснователь)', 'is_correct' => true],
                    ['text' => 'Стив Джобс', 'is_correct' => false],
                    ['text' => 'Билл Гейтс', 'is_correct' => false],
                    ['text' => 'Джефф Безос', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Android"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Операционная система', 'is_correct' => true],
                    ['text' => 'Робот', 'is_correct' => false],
                    ['text' => 'Компьютер', 'is_correct' => false],
                    ['text' => 'Браузер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета находится ближе всего к Земле?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Венера', 'is_correct' => true],
                    ['text' => 'Марс', 'is_correct' => false],
                    ['text' => 'Меркурий', 'is_correct' => false],
                    ['text' => 'Юпитер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что изучает наука химия?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Вещества и их превращения', 'is_correct' => true],
                    ['text' => 'Живые организмы', 'is_correct' => false],
                    ['text' => 'Звезды', 'is_correct' => false],
                    ['text' => 'Числа', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется прибор для измерения температуры?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Термометр', 'is_correct' => true],
                    ['text' => 'Барометр', 'is_correct' => false],
                    ['text' => 'Спидометр', 'is_correct' => false],
                    ['text' => 'Весы', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой элемент самый распространенный во Вселенной?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Водород', 'is_correct' => true],
                    ['text' => 'Гелий', 'is_correct' => false],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Углерод', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "PDF"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Формат документа', 'is_correct' => true],
                    ['text' => 'Программа для рисования', 'is_correct' => false],
                    ['text' => 'Видеофайл', 'is_correct' => false],
                    ['text' => 'Архив', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто придумал периодическую таблицу элементов?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дмитрий Менделеев', 'is_correct' => true],
                    ['text' => 'Альберт Эйнштейн', 'is_correct' => false],
                    ['text' => 'Нильс Бор', 'is_correct' => false],
                    ['text' => 'Мария Кюри', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган перекачивает кровь?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Сердце', 'is_correct' => true],
                    ['text' => 'Легкие', 'is_correct' => false],
                    ['text' => 'Желудок', 'is_correct' => false],
                    ['text' => 'Печень', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "SSD"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Твердотельный накопитель', 'is_correct' => true],
                    ['text' => 'Экран', 'is_correct' => false],
                    ['text' => 'Звуковая карта', 'is_correct' => false],
                    ['text' => 'Блок питания', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая наука изучает погоду?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Метеорология', 'is_correct' => true],
                    ['text' => 'Геология', 'is_correct' => false],
                    ['text' => 'Биология', 'is_correct' => false],
                    ['text' => 'Астрономия', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько зубов у взрослого человека (обычно)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '32', 'is_correct' => true],
                    ['text' => '28', 'is_correct' => false],
                    ['text' => '30', 'is_correct' => false],
                    ['text' => '36', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой металл ржавеет?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Железо', 'is_correct' => true],
                    ['text' => 'Золото', 'is_correct' => false],
                    ['text' => 'Алюминий (окисляется, но не ржавеет)', 'is_correct' => false],
                    ['text' => 'Медь', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "GPS"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Система навигации', 'is_correct' => true],
                    ['text' => 'Игровая приставка', 'is_correct' => false],
                    ['text' => 'Формат видео', 'is_correct' => false],
                    ['text' => 'Банковская система', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета была лишена статуса планеты?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Плутон', 'is_correct' => true],
                    ['text' => 'Марс', 'is_correct' => false],
                    ['text' => 'Нептун', 'is_correct' => false],
                    ['text' => 'Меркурий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "биткоин"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Криптовалюта', 'is_correct' => true],
                    ['text' => 'Компьютерная игра', 'is_correct' => false],
                    ['text' => 'Банк', 'is_correct' => false],
                    ['text' => 'Социальная сеть', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой витамин содержится в цитрусовых?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Витамин C', 'is_correct' => true],
                    ['text' => 'Витамин A', 'is_correct' => false],
                    ['text' => 'Витамин D', 'is_correct' => false],
                    ['text' => 'Витамин B', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто изобрел World Wide Web (WWW)?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Тим Бернерс-Ли', 'is_correct' => true],
                    ['text' => 'Билл Гейтс', 'is_correct' => false],
                    ['text' => 'Стив Джобс', 'is_correct' => false],
                    ['text' => 'Марк Цукерберг', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется прибор для наблюдения за микробами?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Микроскоп', 'is_correct' => true],
                    ['text' => 'Телескоп', 'is_correct' => false],
                    ['text' => 'Бинокль', 'is_correct' => false],
                    ['text' => 'Лупа', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой элемент используется в карандашах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Графит', 'is_correct' => true],
                    ['text' => 'Свинец', 'is_correct' => false],
                    ['text' => 'Уголь', 'is_correct' => false],
                    ['text' => 'Железо', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "RAM"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Оперативная память', 'is_correct' => true],
                    ['text' => 'Жесткий диск', 'is_correct' => false],
                    ['text' => 'Процессор', 'is_correct' => false],
                    ['text' => 'Монитор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая сила удерживает нас на Земле?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Гравитация', 'is_correct' => true],
                    ['text' => 'Магнетизм', 'is_correct' => false],
                    ['text' => 'Трение', 'is_correct' => false],
                    ['text' => 'Инерция', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Сколько планет в Солнечной системе?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => '8', 'is_correct' => true],
                    ['text' => '9', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false],
                    ['text' => '10', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "AI"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Искусственный интеллект', 'is_correct' => true],
                    ['text' => 'Интернет', 'is_correct' => false],
                    ['text' => 'Антивирус', 'is_correct' => false],
                    ['text' => 'Приложение', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой орган чувств воспринимает свет?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Глаз', 'is_correct' => true],
                    ['text' => 'Ухо', 'is_correct' => false],
                    ['text' => 'Нос', 'is_correct' => false],
                    ['text' => 'Кожа', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета вращается быстрее всех?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Юпитер', 'is_correct' => true],
                    ['text' => 'Меркурий', 'is_correct' => false],
                    ['text' => 'Земля', 'is_correct' => false],
                    ['text' => 'Сатурн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "браузер"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Программа для просмотра сайтов', 'is_correct' => true],
                    ['text' => 'Поисковая система', 'is_correct' => false],
                    ['text' => 'Вирус', 'is_correct' => false],
                    ['text' => 'Социальная сеть', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто открыл рентгеновские лучи?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Вильгельм Рентген', 'is_correct' => true],
                    ['text' => 'Мария Кюри', 'is_correct' => false],
                    ['text' => 'Никола Тесла', 'is_correct' => false],
                    ['text' => 'Томас Эдисон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой материал лучше всего проводит электричество?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Серебро', 'is_correct' => true],
                    ['text' => 'Медь', 'is_correct' => false],
                    ['text' => 'Золото', 'is_correct' => false],
                    ['text' => 'Алюминий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "пиксель"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Точка на экране', 'is_correct' => true],
                    ['text' => 'Единица памяти', 'is_correct' => false],
                    ['text' => 'Тип кабеля', 'is_correct' => false],
                    ['text' => 'Звуковой файл', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется наука о числах?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Математика', 'is_correct' => true],
                    ['text' => 'Физика', 'is_correct' => false],
                    ['text' => 'Химия', 'is_correct' => false],
                    ['text' => 'История', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "облако" (в IT)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Удаленное хранилище данных', 'is_correct' => true],
                    ['text' => 'Погода', 'is_correct' => false],
                    ['text' => 'Беспроводная сеть', 'is_correct' => false],
                    ['text' => 'Вирус', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой газ делает газировку газированной?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Углекислый газ', 'is_correct' => true],
                    ['text' => 'Кислород', 'is_correct' => false],
                    ['text' => 'Азот', 'is_correct' => false],
                    ['text' => 'Гелий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "хакер"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Взломщик компьютерных систем', 'is_correct' => true],
                    ['text' => 'Программист', 'is_correct' => false],
                    ['text' => 'Геймер', 'is_correct' => false],
                    ['text' => 'Блогер', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая планета красного цвета?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марс', 'is_correct' => true],
                    ['text' => 'Юпитер', 'is_correct' => false],
                    ['text' => 'Венера', 'is_correct' => false],
                    ['text' => 'Сатурн', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "дрон"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Беспилотный летательный аппарат', 'is_correct' => true],
                    ['text' => 'Робот-пылесос', 'is_correct' => false],
                    ['text' => 'Игровая приставка', 'is_correct' => false],
                    ['text' => 'Смартфон', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется единица информации (0 или 1)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Бит', 'is_correct' => true],
                    ['text' => 'Байт', 'is_correct' => false],
                    ['text' => 'Мегабайт', 'is_correct' => false],
                    ['text' => 'Гигабайт', 'is_correct' => false],
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
