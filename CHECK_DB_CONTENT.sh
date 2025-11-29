#!/bin/bash
# Проверка содержимого базы данных

set -e

echo "🔍 Проверка содержимого базы данных..."

cd /var/www/quiz-bot/bot

# Проверяем количество категорий
echo "📊 Категории:"
php -r "
require 'vendor/autoload.php';
\$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
\$categories = QuizBot\Domain\Model\Category::where('is_active', true)->get();
echo 'Всего категорий: ' . \$categories->count() . PHP_EOL;
foreach (\$categories as \$cat) {
    echo '  - ' . \$cat->title . ' (' . \$cat->code . ')' . PHP_EOL;
}
"

# Проверяем количество вопросов
echo ""
echo "📊 Вопросы:"
php -r "
require 'vendor/autoload.php';
\$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
\$questions = QuizBot\Domain\Model\Question::where('is_active', true)->get();
echo 'Всего вопросов: ' . \$questions->count() . PHP_EOL;
\$byCategory = \$questions->groupBy('category_id');
foreach (\$byCategory as \$catId => \$catQuestions) {
    \$category = QuizBot\Domain\Model\Category::find(\$catId);
    echo '  - ' . (\$category ? \$category->title : 'Unknown') . ': ' . \$catQuestions->count() . ' вопросов' . PHP_EOL;
}
"

# Проверяем количество ответов
echo ""
echo "📊 Ответы:"
php -r "
require 'vendor/autoload.php';
\$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
\$answers = QuizBot\Domain\Model\Answer::all();
echo 'Всего ответов: ' . \$answers->count() . PHP_EOL;
\$correct = \$answers->where('is_correct', true)->count();
echo 'Правильных ответов: ' . \$correct . PHP_EOL;
"

# Проверяем сюжетные главы
echo ""
echo "📊 Сюжетные главы:"
php -r "
require 'vendor/autoload.php';
\$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
\$chapters = QuizBot\Domain\Model\StoryChapter::where('is_active', true)->get();
echo 'Всего глав: ' . \$chapters->count() . PHP_EOL;
foreach (\$chapters as \$chapter) {
    \$steps = \$chapter->steps()->count();
    echo '  - ' . \$chapter->title . ': ' . \$steps . ' шагов' . PHP_EOL;
}
"

# Проверяем пример вопроса
echo ""
echo "📊 Пример вопроса:"
php -r "
require 'vendor/autoload.php';
\$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
\$question = QuizBot\Domain\Model\Question::with('answers')->where('is_active', true)->first();
if (\$question) {
    echo 'Вопрос: ' . \$question->question_text . PHP_EOL;
    echo 'Категория: ' . \$question->category->title . PHP_EOL;
    echo 'Ответов: ' . \$question->answers->count() . PHP_EOL;
    foreach (\$question->answers as \$answer) {
        echo '  ' . (\$answer->is_correct ? '✓' : ' ') . ' ' . \$answer->answer_text . PHP_EOL;
    }
} else {
    echo 'Вопросы не найдены!' . PHP_EOL;
}
"

echo ""
echo "✅ Проверка завершена!"

