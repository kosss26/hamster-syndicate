<?php

namespace QuizBot\Database\Seeders;

use QuizBot\Domain\Model\Collection;
use QuizBot\Domain\Model\CollectionItem;

class CollectionsSeeder
{
    public function seed(): void
    {
        // === КОЛЛЕКЦИЯ 1: Учёные (20 карточек) ===
        $scientists = Collection::updateOrCreate(
            ['key' => 'scientists'],
            [
                'title' => 'Великие учёные',
                'description' => 'Коллекция карточек знаменитых учёных и изобретателей',
                'icon' => '🔬',
                'total_items' => 20,
                'rarity' => 'epic',
                'reward_coins' => 5000,
                'reward_gems' => 30,
            ]
        );

        $scientistsData = [
            ['key' => 'einstein', 'name' => 'Альберт Эйнштейн', 'description' => 'Создатель теории относительности', 'rarity' => 'legendary', 'drop_chance' => 0.05],
            ['key' => 'newton', 'name' => 'Исаак Ньютон', 'description' => 'Открыл законы гравитации', 'rarity' => 'legendary', 'drop_chance' => 0.05],
            ['key' => 'curie', 'name' => 'Мария Кюри', 'description' => 'Дважды лауреат Нобелевской премии', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'hawking', 'name' => 'Стивен Хокинг', 'description' => 'Теоретик чёрных дыр', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'tesla', 'name' => 'Никола Тесла', 'description' => 'Пионер электротехники', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'galileo', 'name' => 'Галилео Галилей', 'description' => 'Отец наблюдательной астрономии', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'mendeleev', 'name' => 'Дмитрий Менделеев', 'description' => 'Создатель периодической таблицы', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'darwin', 'name' => 'Чарльз Дарвин', 'description' => 'Теория эволюции', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'lomonosov', 'name' => 'Михаил Ломоносов', 'description' => 'Универсальный русский учёный', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'pasteur', 'name' => 'Луи Пастер', 'description' => 'Основатель микробиологии', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'pavlov', 'name' => 'Иван Павлов', 'description' => 'Исследователь условных рефлексов', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'copernicus', 'name' => 'Николай Коперник', 'description' => 'Гелиоцентрическая система', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'archimedes', 'name' => 'Архимед', 'description' => 'Великий математик древности', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'faraday', 'name' => 'Майкл Фарадей', 'description' => 'Открыл электромагнитную индукцию', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'bohr', 'name' => 'Нильс Бор', 'description' => 'Квантовая физика', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'edison', 'name' => 'Томас Эдисон', 'description' => 'Изобретатель лампочки', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'schrodinger', 'name' => 'Эрвин Шрёдингер', 'description' => 'Кот Шрёдингера', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'franklin', 'name' => 'Розалинд Франклин', 'description' => 'Структура ДНК', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'rutherford', 'name' => 'Эрнест Резерфорд', 'description' => 'Ядерная физика', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'maxwell', 'name' => 'Джеймс Максвелл', 'description' => 'Теория электромагнетизма', 'rarity' => 'common', 'drop_chance' => 0.2],
        ];

        foreach ($scientistsData as $idx => $item) {
            CollectionItem::updateOrCreate(
                ['collection_id' => $scientists->id, 'key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'image_url' => null,
                    'rarity' => $item['rarity'],
                    'drop_chance' => $item['drop_chance'],
                    'sort_order' => $idx + 1,
                ]
            );
        }

        // === КОЛЛЕКЦИЯ 2: Художники (15 карточек) ===
        $artists = Collection::updateOrCreate(
            ['key' => 'artists'],
            [
                'title' => 'Великие художники',
                'description' => 'Коллекция карточек знаменитых художников и скульпторов',
                'icon' => '🎨',
                'total_items' => 15,
                'rarity' => 'rare',
                'reward_coins' => 3000,
                'reward_gems' => 20,
            ]
        );

        $artistsData = [
            ['key' => 'davinci', 'name' => 'Леонардо да Винчи', 'description' => 'Мона Лиза', 'rarity' => 'legendary', 'drop_chance' => 0.05],
            ['key' => 'picasso', 'name' => 'Пабло Пикассо', 'description' => 'Основатель кубизма', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'vangogh', 'name' => 'Винсент Ван Гог', 'description' => 'Звёздная ночь', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'michelangelo', 'name' => 'Микеланджело', 'description' => 'Сикстинская капелла', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'rembrandt', 'name' => 'Рембрандт', 'description' => 'Мастер светотени', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'monet', 'name' => 'Клод Моне', 'description' => 'Основатель импрессионизма', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'dali', 'name' => 'Сальвадор Дали', 'description' => 'Сюрреализм', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'raphael', 'name' => 'Рафаэль', 'description' => 'Мастер Возрождения', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'repin', 'name' => 'Илья Репин', 'description' => 'Бурлаки на Волге', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'kandinsky', 'name' => 'Василий Кандинский', 'description' => 'Абстрактное искусство', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'malevich', 'name' => 'Казимир Малевич', 'description' => 'Чёрный квадрат', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'shishkin', 'name' => 'Иван Шишкин', 'description' => 'Утро в сосновом лесу', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'renoir', 'name' => 'Огюст Ренуар', 'description' => 'Импрессионист', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'goya', 'name' => 'Франсиско Гойя', 'description' => 'Испанский романтизм', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'matisse', 'name' => 'Анри Матисс', 'description' => 'Фовизм', 'rarity' => 'common', 'drop_chance' => 0.2],
        ];

        foreach ($artistsData as $idx => $item) {
            CollectionItem::updateOrCreate(
                ['collection_id' => $artists->id, 'key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'image_url' => null,
                    'rarity' => $item['rarity'],
                    'drop_chance' => $item['drop_chance'],
                    'sort_order' => $idx + 1,
                ]
            );
        }

        // === КОЛЛЕКЦИЯ 3: Исторические личности (25 карточек) ===
        $history = Collection::updateOrCreate(
            ['key' => 'historical_figures'],
            [
                'title' => 'Исторические личности',
                'description' => 'Коллекция карточек великих правителей и полководцев',
                'icon' => '🏛️',
                'total_items' => 25,
                'rarity' => 'epic',
                'reward_coins' => 7000,
                'reward_gems' => 50,
            ]
        );

        $historyData = [
            ['key' => 'napoleon', 'name' => 'Наполеон Бонапарт', 'description' => 'Император Франции', 'rarity' => 'legendary', 'drop_chance' => 0.04],
            ['key' => 'peter1', 'name' => 'Пётр I', 'description' => 'Первый российский император', 'rarity' => 'legendary', 'drop_chance' => 0.04],
            ['key' => 'alexander', 'name' => 'Александр Македонский', 'description' => 'Великий полководец', 'rarity' => 'legendary', 'drop_chance' => 0.04],
            ['key' => 'caesar', 'name' => 'Юлий Цезарь', 'description' => 'Диктатор Рима', 'rarity' => 'epic', 'drop_chance' => 0.08],
            ['key' => 'catherine2', 'name' => 'Екатерина II', 'description' => 'Великая императрица', 'rarity' => 'epic', 'drop_chance' => 0.08],
            ['key' => 'genghis', 'name' => 'Чингисхан', 'description' => 'Основатель Монгольской империи', 'rarity' => 'epic', 'drop_chance' => 0.08],
            ['key' => 'cleopatra', 'name' => 'Клеопатра', 'description' => 'Последняя царица Египта', 'rarity' => 'rare', 'drop_chance' => 0.12],
            ['key' => 'ivan4', 'name' => 'Иван Грозный', 'description' => 'Первый русский царь', 'rarity' => 'rare', 'drop_chance' => 0.12],
            ['key' => 'columbus', 'name' => 'Христофор Колумб', 'description' => 'Открыл Америку', 'rarity' => 'rare', 'drop_chance' => 0.12],
            ['key' => 'lenin', 'name' => 'Владимир Ленин', 'description' => 'Вождь революции', 'rarity' => 'rare', 'drop_chance' => 0.12],
            ['key' => 'churchill', 'name' => 'Уинстон Черчилль', 'description' => 'Премьер-министр Великобритании', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'washington', 'name' => 'Джордж Вашингтон', 'description' => 'Первый президент США', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'lincoln', 'name' => 'Авраам Линкольн', 'description' => '16-й президент США', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'charlemagne', 'name' => 'Карл Великий', 'description' => 'Император франков', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'spartacus', 'name' => 'Спартак', 'description' => 'Вождь восстания рабов', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'saladin', 'name' => 'Саладин', 'description' => 'Султан Египта', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'suvorov', 'name' => 'Александр Суворов', 'description' => 'Великий полководец', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'kutuzov', 'name' => 'Михаил Кутузов', 'description' => 'Победитель Наполеона', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'gandhi', 'name' => 'Махатма Ганди', 'description' => 'Лидер ненасильственного сопротивления', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'mandela', 'name' => 'Нельсон Мандела', 'description' => 'Первый чёрный президент ЮАР', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'joan', 'name' => 'Жанна д\'Арк', 'description' => 'Орлеанская дева', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'attila', 'name' => 'Аттила', 'description' => 'Вождь гуннов', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'hammurabi', 'name' => 'Хаммурапи', 'description' => 'Создатель первого свода законов', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'elizabeth1', 'name' => 'Елизавета I', 'description' => 'Королева Англии', 'rarity' => 'common', 'drop_chance' => 0.15],
            ['key' => 'victoria', 'name' => 'Королева Виктория', 'description' => 'Викторианская эпоха', 'rarity' => 'common', 'drop_chance' => 0.15],
        ];

        foreach ($historyData as $idx => $item) {
            CollectionItem::updateOrCreate(
                ['collection_id' => $history->id, 'key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'image_url' => null,
                    'rarity' => $item['rarity'],
                    'drop_chance' => $item['drop_chance'],
                    'sort_order' => $idx + 1,
                ]
            );
        }

        // === КОЛЛЕКЦИЯ 4: Страны мира (30 карточек) ===
        $countries = Collection::updateOrCreate(
            ['key' => 'countries'],
            [
                'title' => 'Страны мира',
                'description' => 'Коллекция карточек стран со всех континентов',
                'icon' => '🌍',
                'total_items' => 30,
                'rarity' => 'rare',
                'reward_coins' => 4000,
                'reward_gems' => 25,
            ]
        );

        $countriesData = [
            ['key' => 'russia', 'name' => 'Россия', 'description' => 'Самая большая страна мира', 'rarity' => 'epic', 'drop_chance' => 0.08],
            ['key' => 'usa', 'name' => 'США', 'description' => 'Соединённые Штаты Америки', 'rarity' => 'epic', 'drop_chance' => 0.08],
            ['key' => 'china', 'name' => 'Китай', 'description' => 'Самая населённая страна', 'rarity' => 'epic', 'drop_chance' => 0.08],
            ['key' => 'india', 'name' => 'Индия', 'description' => 'Страна контрастов', 'rarity' => 'rare', 'drop_chance' => 0.1],
            ['key' => 'brazil', 'name' => 'Бразилия', 'description' => 'Страна карнавалов', 'rarity' => 'rare', 'drop_chance' => 0.1],
            ['key' => 'japan', 'name' => 'Япония', 'description' => 'Страна восходящего солнца', 'rarity' => 'rare', 'drop_chance' => 0.1],
            ['key' => 'germany', 'name' => 'Германия', 'description' => 'Сердце Европы', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'france', 'name' => 'Франция', 'description' => 'Родина моды', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'uk', 'name' => 'Великобритания', 'description' => 'Туманный Альбион', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'italy', 'name' => 'Италия', 'description' => 'Колыбель цивилизации', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'spain', 'name' => 'Испания', 'description' => 'Страна фламенко', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'canada', 'name' => 'Канада', 'description' => 'Страна кленовых листьев', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'australia', 'name' => 'Австралия', 'description' => 'Континент-страна', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'mexico', 'name' => 'Мексика', 'description' => 'Страна текилы', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'argentina', 'name' => 'Аргентина', 'description' => 'Страна танго', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'egypt', 'name' => 'Египет', 'description' => 'Страна пирамид', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'turkey', 'name' => 'Турция', 'description' => 'Мост между Европой и Азией', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'greece', 'name' => 'Греция', 'description' => 'Родина Олимпийских игр', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'norway', 'name' => 'Норвегия', 'description' => 'Страна фьордов', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'sweden', 'name' => 'Швеция', 'description' => 'Страна IKEA', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'switzerland', 'name' => 'Швейцария', 'description' => 'Страна банков и часов', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'netherlands', 'name' => 'Нидерланды', 'description' => 'Страна тюльпанов', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'poland', 'name' => 'Польша', 'description' => 'Сердце Восточной Европы', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'thailand', 'name' => 'Таиланд', 'description' => 'Страна улыбок', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'vietnam', 'name' => 'Вьетнам', 'description' => 'Страна рисовых полей', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'south_korea', 'name' => 'Южная Корея', 'description' => 'Страна K-pop', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'uae', 'name' => 'ОАЭ', 'description' => 'Страна небоскрёбов', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'south_africa', 'name' => 'ЮАР', 'description' => 'Радужная нация', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'new_zealand', 'name' => 'Новая Зеландия', 'description' => 'Страна киви', 'rarity' => 'common', 'drop_chance' => 0.12],
            ['key' => 'iceland', 'name' => 'Исландия', 'description' => 'Страна льда и пламени', 'rarity' => 'common', 'drop_chance' => 0.12],
        ];

        foreach ($countriesData as $idx => $item) {
            CollectionItem::updateOrCreate(
                ['collection_id' => $countries->id, 'key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'image_url' => null,
                    'rarity' => $item['rarity'],
                    'drop_chance' => $item['drop_chance'],
                    'sort_order' => $idx + 1,
                ]
            );
        }

        // === КОЛЛЕКЦИЯ 5: Мифология (20 карточек) ===
        $mythology = Collection::updateOrCreate(
            ['key' => 'mythology'],
            [
                'title' => 'Мифология',
                'description' => 'Коллекция карточек богов и героев разных культур',
                'icon' => '⚡',
                'total_items' => 20,
                'rarity' => 'epic',
                'reward_coins' => 5000,
                'reward_gems' => 35,
            ]
        );

        $mythologyData = [
            ['key' => 'zeus', 'name' => 'Зевс', 'description' => 'Главный бог Олимпа', 'rarity' => 'legendary', 'drop_chance' => 0.05],
            ['key' => 'odin', 'name' => 'Один', 'description' => 'Верховный бог скандинавов', 'rarity' => 'legendary', 'drop_chance' => 0.05],
            ['key' => 'anubis', 'name' => 'Анубис', 'description' => 'Египетский бог мёртвых', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'poseidon', 'name' => 'Посейдон', 'description' => 'Бог морей', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'thor', 'name' => 'Тор', 'description' => 'Бог грома', 'rarity' => 'epic', 'drop_chance' => 0.1],
            ['key' => 'athena', 'name' => 'Афина', 'description' => 'Богиня мудрости', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'hades', 'name' => 'Аид', 'description' => 'Бог подземного мира', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'loki', 'name' => 'Локи', 'description' => 'Бог хитрости', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'ra', 'name' => 'Ра', 'description' => 'Египетский бог солнца', 'rarity' => 'rare', 'drop_chance' => 0.15],
            ['key' => 'apollo', 'name' => 'Аполлон', 'description' => 'Бог света и искусств', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'artemis', 'name' => 'Артемида', 'description' => 'Богиня охоты', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'ares', 'name' => 'Арес', 'description' => 'Бог войны', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'aphrodite', 'name' => 'Афродита', 'description' => 'Богиня любви', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'hermes', 'name' => 'Гермес', 'description' => 'Вестник богов', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'hercules', 'name' => 'Геракл', 'description' => 'Величайший герой', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'perseus', 'name' => 'Персей', 'description' => 'Победитель Медузы', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'achilles', 'name' => 'Ахиллес', 'description' => 'Величайший воин Трои', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'odysseus', 'name' => 'Одиссей', 'description' => 'Хитроумный герой', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'freya', 'name' => 'Фрейя', 'description' => 'Скандинавская богиня любви', 'rarity' => 'common', 'drop_chance' => 0.2],
            ['key' => 'valkyr', 'name' => 'Валькирии', 'description' => 'Воительницы Одина', 'rarity' => 'common', 'drop_chance' => 0.2],
        ];

        foreach ($mythologyData as $idx => $item) {
            CollectionItem::updateOrCreate(
                ['collection_id' => $mythology->id, 'key' => $item['key']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'image_url' => null,
                    'rarity' => $item['rarity'],
                    'drop_chance' => $item['drop_chance'],
                    'sort_order' => $idx + 1,
                ]
            );
        }

        echo "✅ Создано 5 коллекций с 110 карточками\n";
    }
}

