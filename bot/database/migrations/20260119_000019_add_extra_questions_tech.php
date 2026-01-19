<?php

use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260119_000019_add_extra_questions_tech';
    }

    public function up(Builder $schema): void
    {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');
        
        $category = $db->table('categories')->where('title', 'Современные технологии')->first();
        
        if (!$category) {
            echo "Category 'Современные технологии' not found. Skipping questions import.\n";
            return;
        }
        
        $categoryId = $category->id;

        $questions = [
            [
                'question_text' => 'Что такое NFT?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Невзаимозаменяемый токен', 'is_correct' => true],
                    ['text' => 'Новая финансовая технология', 'is_correct' => false],
                    ['text' => 'Сетевой протокол передачи', 'is_correct' => false],
                    ['text' => 'Национальный фонд технологий', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания создала ChatGPT?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'OpenAI', 'is_correct' => true],
                    ['text' => 'Google', 'is_correct' => false],
                    ['text' => 'Microsoft', 'is_correct' => false],
                    ['text' => 'Meta', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "VPN"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Виртуальная частная сеть', 'is_correct' => true],
                    ['text' => 'Визуальный протокол новостей', 'is_correct' => false],
                    ['text' => 'Верификация пользователей сети', 'is_correct' => false],
                    ['text' => 'Видео по запросу', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой язык программирования используют для создания смарт-контрактов на Ethereum?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Solidity', 'is_correct' => true],
                    ['text' => 'Python', 'is_correct' => false],
                    ['text' => 'Java', 'is_correct' => false],
                    ['text' => 'C++', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Фишинг"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Интернет-мошенничество', 'is_correct' => true],
                    ['text' => 'Ловля рыбы онлайн', 'is_correct' => false],
                    ['text' => 'Тестирование программ', 'is_correct' => false],
                    ['text' => 'Поиск информации', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая социальная сеть сменила название на "X"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Twitter', 'is_correct' => true],
                    ['text' => 'Facebook', 'is_correct' => false],
                    ['text' => 'Instagram', 'is_correct' => false],
                    ['text' => 'Snapchat', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что означает "IoT"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Интернет вещей', 'is_correct' => true],
                    ['text' => 'Внутренняя операционная технология', 'is_correct' => false],
                    ['text' => 'Интеграция офисных технологий', 'is_correct' => false],
                    ['text' => 'Интерфейс обмена текстом', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания производит процессоры Ryzen?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'AMD', 'is_correct' => true],
                    ['text' => 'Intel', 'is_correct' => false],
                    ['text' => 'NVIDIA', 'is_correct' => false],
                    ['text' => 'Qualcomm', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Deepfake"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Подмена лица/голоса с помощью ИИ', 'is_correct' => true],
                    ['text' => 'Глубокий анализ данных', 'is_correct' => false],
                    ['text' => 'Хакерская атака', 'is_correct' => false],
                    ['text' => 'Скрытая сеть', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой мессенджер создал Павел Дуров?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Telegram', 'is_correct' => true],
                    ['text' => 'WhatsApp', 'is_correct' => false],
                    ['text' => 'Viber', 'is_correct' => false],
                    ['text' => 'Signal', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "5G"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Пятое поколение мобильной связи', 'is_correct' => true],
                    ['text' => '5 гигабайт памяти', 'is_correct' => false],
                    ['text' => 'Модель процессора', 'is_correct' => false],
                    ['text' => 'Космическая программа', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой формат видео поддерживает разрешение 3840x2160?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => '4K (Ultra HD)', 'is_correct' => true],
                    ['text' => 'Full HD', 'is_correct' => false],
                    ['text' => 'HD Ready', 'is_correct' => false],
                    ['text' => '8K', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Капча" (CAPTCHA)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Тест для различения людей и роботов', 'is_correct' => true],
                    ['text' => 'Вирус-вымогатель', 'is_correct' => false],
                    ['text' => 'Программа для захвата экрана', 'is_correct' => false],
                    ['text' => 'Устройство ввода', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания владеет YouTube?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Google (Alphabet)', 'is_correct' => true],
                    ['text' => 'Meta', 'is_correct' => false],
                    ['text' => 'Amazon', 'is_correct' => false],
                    ['text' => 'Microsoft', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Big Data"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Большие данные', 'is_correct' => true],
                    ['text' => 'Большой диск', 'is_correct' => false],
                    ['text' => 'Крупная компания', 'is_correct' => false],
                    ['text' => 'Сложный алгоритм', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой порт стал стандартом для зарядки в ЕС (и iPhone 15)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'USB-C', 'is_correct' => true],
                    ['text' => 'Lightning', 'is_correct' => false],
                    ['text' => 'Micro-USB', 'is_correct' => false],
                    ['text' => 'Mini-USB', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "DDoS-атака"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Атака "Отказ в обслуживании"', 'is_correct' => true],
                    ['text' => 'Кража паролей', 'is_correct' => false],
                    ['text' => 'Взлом Wi-Fi', 'is_correct' => false],
                    ['text' => 'Удаление данных', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая технология лежит в основе биткоина?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Блокчейн', 'is_correct' => true],
                    ['text' => 'Облачные вычисления', 'is_correct' => false],
                    ['text' => 'Нейросети', 'is_correct' => false],
                    ['text' => 'Квантовая криптография', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто является CEO компании Meta (Facebook)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Марк Цукерберг', 'is_correct' => true],
                    ['text' => 'Илон Маск', 'is_correct' => false],
                    ['text' => 'Джефф Безос', 'is_correct' => false],
                    ['text' => 'Тим Кук', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Стрим"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Прямая трансляция в интернете', 'is_correct' => true],
                    ['text' => 'Быстрая загрузка файла', 'is_correct' => false],
                    ['text' => 'Тип видеоигры', 'is_correct' => false],
                    ['text' => 'Социальная сеть', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой браузер разработан компанией Apple?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Safari', 'is_correct' => true],
                    ['text' => 'Chrome', 'is_correct' => false],
                    ['text' => 'Edge', 'is_correct' => false],
                    ['text' => 'Firefox', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "QR-код"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Двумерный штрих-код', 'is_correct' => true],
                    ['text' => 'Секретный шифр', 'is_correct' => false],
                    ['text' => 'Логотип компании', 'is_correct' => false],
                    ['text' => 'Цифровая подпись', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая страна первой легализовала биткоин как платежное средство?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'Сальвадор', 'is_correct' => true],
                    ['text' => 'США', 'is_correct' => false],
                    ['text' => 'Япония', 'is_correct' => false],
                    ['text' => 'Швейцария', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Cookies" (в браузере)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Данные, сохраняемые сайтами', 'is_correct' => true],
                    ['text' => 'Вирусные программы', 'is_correct' => false],
                    ['text' => 'Рекламные баннеры', 'is_correct' => false],
                    ['text' => 'Настройки экрана', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания производит игровые консоли PlayStation?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Sony', 'is_correct' => true],
                    ['text' => 'Microsoft', 'is_correct' => false],
                    ['text' => 'Nintendo', 'is_correct' => false],
                    ['text' => 'Sega', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Краудфандинг"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Народное финансирование', 'is_correct' => true],
                    ['text' => 'Облачное хранение', 'is_correct' => false],
                    ['text' => 'Поиск сотрудников', 'is_correct' => false],
                    ['text' => 'Биржевая торговля', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой сервис используется для совместной разработки кода (на базе Git)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'GitHub', 'is_correct' => true],
                    ['text' => 'Photoshop', 'is_correct' => false],
                    ['text' => 'Word', 'is_correct' => false],
                    ['text' => 'Skype', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "VR"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Виртуальная реальность', 'is_correct' => true],
                    ['text' => 'Дополненная реальность', 'is_correct' => false],
                    ['text' => 'Видеорегистратор', 'is_correct' => false],
                    ['text' => 'Голосовой помощник', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется голосовой помощник от Amazon?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Alexa', 'is_correct' => true],
                    ['text' => 'Siri', 'is_correct' => false],
                    ['text' => 'Cortana', 'is_correct' => false],
                    ['text' => 'Alice', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Таргетинг"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Настройка рекламы на целевую аудиторию', 'is_correct' => true],
                    ['text' => 'Взлом аккаунта', 'is_correct' => false],
                    ['text' => 'Создание сайта', 'is_correct' => false],
                    ['text' => 'Тестирование игр', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой язык программирования самый популярный для Data Science?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Python', 'is_correct' => true],
                    ['text' => 'Java', 'is_correct' => false],
                    ['text' => 'C#', 'is_correct' => false],
                    ['text' => 'PHP', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Майнинг"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Добыча криптовалюты', 'is_correct' => true],
                    ['text' => 'Создание вирусов', 'is_correct' => false],
                    ['text' => 'Разработка игр', 'is_correct' => false],
                    ['text' => 'Установка обновлений', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания создала Windows?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Microsoft', 'is_correct' => true],
                    ['text' => 'Apple', 'is_correct' => false],
                    ['text' => 'IBM', 'is_correct' => false],
                    ['text' => 'Google', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что означает "HTTP"?',
                'difficulty' => 'hard',
                'answers' => [
                    ['text' => 'HyperText Transfer Protocol', 'is_correct' => true],
                    ['text' => 'High Tech Transfer Protocol', 'is_correct' => false],
                    ['text' => 'Home Transfer Text Protocol', 'is_correct' => false],
                    ['text' => 'HyperText Technical Program', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой мессенджер принадлежит Meta?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'WhatsApp', 'is_correct' => true],
                    ['text' => 'Telegram', 'is_correct' => false],
                    ['text' => 'Viber', 'is_correct' => false],
                    ['text' => 'Signal', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Фриланс"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Удаленная работа без штата', 'is_correct' => true],
                    ['text' => 'Бесплатная работа', 'is_correct' => false],
                    ['text' => 'Работа в офисе', 'is_correct' => false],
                    ['text' => 'Волонтерство', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания производит видеокарты GeForce?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'NVIDIA', 'is_correct' => true],
                    ['text' => 'AMD', 'is_correct' => false],
                    ['text' => 'Intel', 'is_correct' => false],
                    ['text' => 'ASUS', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Двухфакторная аутентификация"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Вход с подтверждением (напр. СМС)', 'is_correct' => true],
                    ['text' => 'Вход с двух устройств', 'is_correct' => false],
                    ['text' => 'Вход без пароля', 'is_correct' => false],
                    ['text' => 'Двойной клик мышью', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой самый популярный интернет-поисковик в мире?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Google', 'is_correct' => true],
                    ['text' => 'Bing', 'is_correct' => false],
                    ['text' => 'Yahoo', 'is_correct' => false],
                    ['text' => 'Baidu', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "AR"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Дополненная реальность', 'is_correct' => true],
                    ['text' => 'Виртуальная реальность', 'is_correct' => false],
                    ['text' => 'Искусственный разум', 'is_correct' => false],
                    ['text' => 'Автоматический режим', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания выпускает iPhone?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Apple', 'is_correct' => true],
                    ['text' => 'Samsung', 'is_correct' => false],
                    ['text' => 'Xiaomi', 'is_correct' => false],
                    ['text' => 'Nokia', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Спам"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Массовая рассылка рекламы', 'is_correct' => true],
                    ['text' => 'Вкусная еда', 'is_correct' => false],
                    ['text' => 'Полезная рассылка', 'is_correct' => false],
                    ['text' => 'Компьютерная игра', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какой сервис позволяет смотреть сериалы и фильмы онлайн (лидер рынка)?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Netflix', 'is_correct' => true],
                    ['text' => 'Twitch', 'is_correct' => false],
                    ['text' => 'Spotify', 'is_correct' => false],
                    ['text' => 'TikTok', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Баг"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Ошибка в программе', 'is_correct' => true],
                    ['text' => 'Фича', 'is_correct' => false],
                    ['text' => 'Компьютерная мышь', 'is_correct' => false],
                    ['text' => 'Жесткий диск', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Как называется магазин приложений на Android?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Google Play', 'is_correct' => true],
                    ['text' => 'App Store', 'is_correct' => false],
                    ['text' => 'Microsoft Store', 'is_correct' => false],
                    ['text' => 'Galaxy Store', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Маркетплейс"?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Платформа для продавцов и покупателей', 'is_correct' => true],
                    ['text' => 'Супермаркет', 'is_correct' => false],
                    ['text' => 'Социальная сеть', 'is_correct' => false],
                    ['text' => 'Платежная система', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания создала Android?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Android Inc. (куплена Google)', 'is_correct' => true],
                    ['text' => 'Apple', 'is_correct' => false],
                    ['text' => 'Microsoft', 'is_correct' => false],
                    ['text' => 'Samsung', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Что такое "Кэш" (Cache)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Временное хранилище данных', 'is_correct' => true],
                    ['text' => 'Наличные деньги', 'is_correct' => false],
                    ['text' => 'Удаленный сервер', 'is_correct' => false],
                    ['text' => 'Корзина', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Кто такой "Инфлюенсер"?',
                'difficulty' => 'easy',
                'answers' => [
                    ['text' => 'Лидер мнений в соцсетях', 'is_correct' => true],
                    ['text' => 'Разработчик', 'is_correct' => false],
                    ['text' => 'Хакер', 'is_correct' => false],
                    ['text' => 'Инвестор', 'is_correct' => false],
                ]
            ],
            [
                'question_text' => 'Какая компания самая дорогая в мире (по капитализации)?',
                'difficulty' => 'medium',
                'answers' => [
                    ['text' => 'Apple (или Microsoft, меняется)', 'is_correct' => true],
                    ['text' => 'Coca-Cola', 'is_correct' => false],
                    ['text' => 'Toyota', 'is_correct' => false],
                    ['text' => 'McDonald\'s', 'is_correct' => false],
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
