<?php

declare(strict_types=1);

use QuizBot\Application\Services\UserService;

function handleAdminCancelDuel($container, ?array $telegramUser, int $duelId): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $duel = \QuizBot\Domain\Model\Duel::query()->find($duelId);
    
    if (!$duel) {
        jsonError('Дуэль не найдена', 404);
    }
    
    if ($duel->status === 'finished' || $duel->status === 'cancelled') {
        jsonError('Дуэль уже завершена', 400);
    }
    
    $duel->status = 'cancelled';
    $duel->finished_at = \Illuminate\Support\Carbon::now();
    $duel->save();
    
    jsonResponse(['cancelled' => true, 'duel_id' => $duelId]);
}

/**
 * Отмена всех активных дуэлей
 */
function handleAdminCancelAllDuels($container, ?array $telegramUser): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $now = \Illuminate\Support\Carbon::now();
    $cancelled = \QuizBot\Domain\Model\Duel::query()
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->update([
            'status' => 'cancelled',
            'finished_at' => $now
        ]);
    
    jsonResponse(['cancelled' => $cancelled]);
}

/**
 * Добавление нового вопроса
 */
function handleAdminAddQuestion($container, ?array $telegramUser, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $categoryId = $body['category_id'] ?? null;
    $questionText = $body['question_text'] ?? null;
    $answers = $body['answers'] ?? [];
    $correctIndex = $body['correct_answer'] ?? 0;
    
    if (!$categoryId || !$questionText || count($answers) < 2) {
        jsonError('Заполните все обязательные поля', 400);
    }
    
    $category = \QuizBot\Domain\Model\Category::query()->find($categoryId);
    if (!$category) {
        jsonError('Категория не найдена', 404);
    }
    
    // Создаём вопрос
    $question = new \QuizBot\Domain\Model\Question([
        'category_id' => $categoryId,
        'question_text' => $questionText,
        'difficulty' => 'medium',
        'time_limit' => 30,
        'is_active' => true,
    ]);
    $question->save();
    
    // Добавляем ответы
    foreach ($answers as $i => $answerText) {
        if (empty(trim($answerText))) continue;
        
        $question->answers()->create([
            'answer_text' => trim($answerText),
            'is_correct' => $i === $correctIndex,
        ]);
    }
    
    jsonResponse([
        'question_id' => $question->getKey(),
        'message' => 'Вопрос добавлен'
    ]);
}

/**
 * Проверка, является ли пользователь админом
 */

/**
 * Проверка прав админа
 */
function handleAdminCheck($container, ?array $telegramUser): void
{
    jsonResponse([
        'is_admin' => isAdmin($telegramUser, $container),
    ]);
}

/**
 * Получение статистики для админки
 */
function handleAdminStats($container, ?array $telegramUser): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $yesterday = \Illuminate\Support\Carbon::now()->subDay();

    // Получаем статистику из БД
    $totalUsers = \QuizBot\Domain\Model\User::query()->count();
    $activeToday = \QuizBot\Domain\Model\User::query()
        ->where('updated_at', '>=', $yesterday)
        ->count();
    $newUsersToday = \QuizBot\Domain\Model\User::query()
        ->where('created_at', '>=', $yesterday)
        ->count();
    
    $totalDuels = \QuizBot\Domain\Model\Duel::query()->count();
    $activeDuels = \QuizBot\Domain\Model\Duel::query()
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->count();
    $duelsToday = \QuizBot\Domain\Model\Duel::query()
        ->where('created_at', '>=', $yesterday)
        ->count();
    
    $totalQuestions = \QuizBot\Domain\Model\Question::query()->count();
    $totalFacts = \QuizBot\Domain\Model\TrueFalseFact::query()->count();
    
    // Последние пользователи
    $recentUsers = \QuizBot\Domain\Model\User::query()
        ->with('profile')
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(fn($u) => [
            'id' => $u->getKey(),
            'telegram_id' => $u->telegram_id,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'username' => $u->username,
            'rating' => $u->profile ? $u->profile->rating : 1000,
        ]);
    
    // Последние дуэли
    $recentDuels = \QuizBot\Domain\Model\Duel::query()
        ->with(['initiator', 'opponent', 'result'])
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(fn($d) => [
            'id' => $d->getKey(),
            'code' => $d->code,
            'status' => $d->status,
            'initiator_name' => $d->initiator ? $d->initiator->first_name : '???',
            'opponent_name' => $d->opponent ? $d->opponent->first_name : null,
            'initiator_score' => $d->result ? $d->result->initiator_total_score : 0,
            'opponent_score' => $d->result ? $d->result->opponent_total_score : 0,
        ]);
    
    // Категории с количеством вопросов
    $categories = \QuizBot\Domain\Model\Category::query()
        ->withCount('questions')
        ->orderByDesc('questions_count')
        ->get()
        ->map(fn($c) => [
            'id' => $c->getKey(),
            'title' => $c->title,
            'count' => $c->questions_count,
        ]);

    jsonResponse([
        'total_users' => $totalUsers,
        'active_today' => $activeToday,
        'new_users_today' => $newUsersToday,
        'total_duels' => $totalDuels,
        'active_duels' => $activeDuels,
        'duels_today' => $duelsToday,
        'total_questions' => $totalQuestions,
        'total_facts' => $totalFacts,
        'tf_games_today' => 0, // TODO: добавить подсчёт
        'recent_users' => $recentUsers,
        'recent_duels' => $recentDuels,
        'categories' => $categories,
    ]);
}

/**
 * Расширенная аналитика по категориям
 * GET /admin/analytics/categories
 */
function handleAdminCategoryAnalytics($container, ?array $telegramUser, array $query): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $days = max(1, min(365, (int) ($query['days'] ?? 30)));
    $mode = trim((string) ($query['mode'] ?? 'all'));
    $minAttempts = max(0, min(5000, (int) ($query['min_attempts'] ?? 1)));
    $from = \Illuminate\Support\Carbon::now()->subDays($days)->startOfDay();

    $analyticsQuery = \Illuminate\Database\Capsule\Manager::table('user_answer_history as h')
        ->join('questions as q', 'q.id', '=', 'h.question_id')
        ->join('categories as c', 'c.id', '=', 'q.category_id')
        ->selectRaw('
            c.id as category_id,
            c.title as category_title,
            c.icon as category_icon,
            COUNT(h.id) as attempts,
            SUM(CASE WHEN h.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
            AVG(h.time_ms) as avg_time_ms,
            COUNT(DISTINCT h.user_id) as unique_players,
            COUNT(DISTINCT h.question_id) as questions_seen
        ')
        ->whereNotNull('h.question_id')
        ->where('h.created_at', '>=', $from);

    if ($mode !== '' && $mode !== 'all') {
        $analyticsQuery->where('h.mode', $mode);
    }

    $analyticsRows = $analyticsQuery
        ->groupBy('c.id', 'c.title', 'c.icon')
        ->havingRaw('COUNT(h.id) >= ?', [$minAttempts])
        ->orderByDesc('attempts')
        ->get();

    $categoryIds = $analyticsRows
        ->map(static fn ($row): int => (int) ($row->category_id ?? 0))
        ->filter(static fn (int $id): bool => $id > 0)
        ->values()
        ->all();

    $totalsByCategory = [];
    if ($categoryIds !== []) {
        $totalsByCategory = \QuizBot\Domain\Model\Question::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->selectRaw('category_id, COUNT(*) as total_questions')
            ->groupBy('category_id')
            ->pluck('total_questions', 'category_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    $items = [];
    $summaryAttempts = 0;
    $summaryCorrect = 0;
    $summaryQuestionsSeen = 0;

    foreach ($analyticsRows as $row) {
        $attempts = (int) ($row->attempts ?? 0);
        $correct = (int) ($row->correct_answers ?? 0);
        $accuracy = $attempts > 0 ? round(($correct / $attempts) * 100, 2) : 0.0;
        $categoryId = (int) ($row->category_id ?? 0);
        $questionsSeen = (int) ($row->questions_seen ?? 0);
        $totalQuestions = (int) ($totalsByCategory[$categoryId] ?? 0);
        $coverage = $totalQuestions > 0 ? round(($questionsSeen / $totalQuestions) * 100, 2) : 0.0;
        $difficultyBand = resolveAdminDifficultyBand($accuracy, $attempts);
        $balanceFlag = resolveBalanceFlag($accuracy, $attempts);

        $items[] = [
            'category_id' => $categoryId,
            'category_title' => (string) ($row->category_title ?? 'Без категории'),
            'category_icon' => (string) ($row->category_icon ?? '❓'),
            'attempts' => $attempts,
            'correct_answers' => $correct,
            'accuracy' => $accuracy,
            'avg_time_ms' => (int) round((float) ($row->avg_time_ms ?? 0.0)),
            'avg_time_seconds' => round(((float) ($row->avg_time_ms ?? 0.0)) / 1000, 2),
            'unique_players' => (int) ($row->unique_players ?? 0),
            'questions_seen' => $questionsSeen,
            'total_questions' => $totalQuestions,
            'coverage_percent' => $coverage,
            'difficulty_band' => $difficultyBand,
            'balance_flag' => $balanceFlag,
        ];

        $summaryAttempts += $attempts;
        $summaryCorrect += $correct;
        $summaryQuestionsSeen += $questionsSeen;
    }

    $trendQuery = \Illuminate\Database\Capsule\Manager::table('user_answer_history as h')
        ->join('questions as q', 'q.id', '=', 'h.question_id')
        ->selectRaw('DATE(h.created_at) as day, COUNT(h.id) as attempts, SUM(CASE WHEN h.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
        ->whereNotNull('h.question_id')
        ->where('h.created_at', '>=', $from);

    if ($mode !== '' && $mode !== 'all') {
        $trendQuery->where('h.mode', $mode);
    }

    $dailyTrend = $trendQuery
        ->groupBy(\Illuminate\Database\Capsule\Manager::raw('DATE(h.created_at)'))
        ->orderBy('day')
        ->get()
        ->map(static function ($row): array {
            $attempts = (int) ($row->attempts ?? 0);
            $correct = (int) ($row->correct_answers ?? 0);

            return [
                'day' => (string) ($row->day ?? ''),
                'attempts' => $attempts,
                'correct_answers' => $correct,
                'accuracy' => $attempts > 0 ? round(($correct / $attempts) * 100, 2) : 0.0,
            ];
        })
        ->values()
        ->all();

    jsonResponse([
        'items' => $items,
        'summary' => [
            'categories' => count($items),
            'attempts' => $summaryAttempts,
            'correct_answers' => $summaryCorrect,
            'accuracy' => $summaryAttempts > 0 ? round(($summaryCorrect / $summaryAttempts) * 100, 2) : 0.0,
            'questions_seen' => $summaryQuestionsSeen,
        ],
        'daily_trend' => $dailyTrend,
        'filters' => [
            'days' => $days,
            'mode' => $mode === '' ? 'all' : $mode,
            'min_attempts' => $minAttempts,
            'from' => $from->toIso8601String(),
        ],
    ]);
}

/**
 * Расширенная аналитика по вопросам
 * GET /admin/analytics/questions
 */
function handleAdminQuestionAnalytics($container, ?array $telegramUser, array $query): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $limit = max(1, min(500, (int) ($query['limit'] ?? 200)));
    $days = max(1, min(365, (int) ($query['days'] ?? 30)));
    $mode = trim((string) ($query['mode'] ?? 'duel'));
    $categoryId = (int) ($query['category_id'] ?? 0);
    $minAttempts = max(1, min(5000, (int) ($query['min_attempts'] ?? 3)));
    $search = trim((string) ($query['q'] ?? ''));
    $sort = trim((string) ($query['sort'] ?? 'attempts'));
    $order = strtolower(trim((string) ($query['order'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
    $from = \Illuminate\Support\Carbon::now()->subDays($days)->startOfDay();

    $baseQuery = \Illuminate\Database\Capsule\Manager::table('user_answer_history as h')
        ->join('questions as q', 'q.id', '=', 'h.question_id')
        ->join('categories as c', 'c.id', '=', 'q.category_id')
        ->selectRaw('
            q.id as question_id,
            q.question_text as question_text,
            q.category_id as category_id,
            c.title as category_title,
            c.icon as category_icon,
            COUNT(h.id) as attempts,
            SUM(CASE WHEN h.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
            AVG(h.time_ms) as avg_time_ms,
            COUNT(DISTINCT h.user_id) as unique_players
        ')
        ->whereNotNull('h.question_id')
        ->where('h.created_at', '>=', $from);

    if ($mode !== '' && $mode !== 'all') {
        $baseQuery->where('h.mode', $mode);
    }

    if ($categoryId > 0) {
        $baseQuery->where('q.category_id', $categoryId);
    }

    if ($search !== '') {
        $baseQuery->where('q.question_text', 'like', '%' . $search . '%');
    }

    $baseQuery
        ->groupBy('q.id', 'q.question_text', 'q.category_id', 'c.title', 'c.icon')
        ->havingRaw('COUNT(h.id) >= ?', [$minAttempts]);

    switch ($sort) {
        case 'accuracy':
            $baseQuery->orderByRaw(' (SUM(CASE WHEN h.is_correct = 1 THEN 1 ELSE 0 END) * 1.0 / COUNT(h.id)) ' . $order);
            break;
        case 'avg_time':
            $baseQuery->orderByRaw('AVG(h.time_ms) ' . $order);
            break;
        case 'players':
            $baseQuery->orderByRaw('COUNT(DISTINCT h.user_id) ' . $order);
            break;
        case 'question_id':
            $baseQuery->orderBy('q.id', $order);
            break;
        case 'attempts':
        default:
            $baseQuery->orderByRaw('COUNT(h.id) ' . $order);
            break;
    }

    $rows = $baseQuery
        ->limit($limit)
        ->get();

    $items = [];
    $summaryAttempts = 0;
    $summaryCorrect = 0;
    $difficultyStats = ['easy' => 0, 'medium' => 0, 'hard' => 0];

    foreach ($rows as $row) {
        $attempts = (int) ($row->attempts ?? 0);
        $correct = (int) ($row->correct_answers ?? 0);
        $accuracy = $attempts > 0 ? round(($correct / $attempts) * 100, 2) : 0.0;
        $difficultyBand = resolveAdminDifficultyBand($accuracy, $attempts);
        $balanceFlag = resolveBalanceFlag($accuracy, $attempts);

        $items[] = [
            'question_id' => (int) ($row->question_id ?? 0),
            'question_text' => (string) ($row->question_text ?? ''),
            'category_id' => (int) ($row->category_id ?? 0),
            'category_title' => (string) ($row->category_title ?? 'Без категории'),
            'category_icon' => (string) ($row->category_icon ?? '❓'),
            'attempts' => $attempts,
            'correct_answers' => $correct,
            'accuracy' => $accuracy,
            'avg_time_ms' => (int) round((float) ($row->avg_time_ms ?? 0.0)),
            'avg_time_seconds' => round(((float) ($row->avg_time_ms ?? 0.0)) / 1000, 2),
            'unique_players' => (int) ($row->unique_players ?? 0),
            'difficulty_band' => $difficultyBand,
            'balance_flag' => $balanceFlag,
        ];

        $summaryAttempts += $attempts;
        $summaryCorrect += $correct;
        $difficultyStats[$difficultyBand] = (int) ($difficultyStats[$difficultyBand] ?? 0) + 1;
    }

    jsonResponse([
        'items' => $items,
        'summary' => [
            'questions' => count($items),
            'attempts' => $summaryAttempts,
            'correct_answers' => $summaryCorrect,
            'accuracy' => $summaryAttempts > 0 ? round(($summaryCorrect / $summaryAttempts) * 100, 2) : 0.0,
            'difficulty_distribution' => $difficultyStats,
        ],
        'filters' => [
            'limit' => $limit,
            'days' => $days,
            'mode' => $mode === '' ? 'all' : $mode,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'min_attempts' => $minAttempts,
            'q' => $search,
            'sort' => $sort,
            'order' => $order,
            'from' => $from->toIso8601String(),
        ],
    ]);
}

function resolveAdminDifficultyBand(float $accuracyPercent, int $attempts): string
{
    if ($attempts < 15) {
        return 'medium';
    }

    if ($accuracyPercent > 70.0) {
        return 'easy';
    }

    if ($accuracyPercent >= 40.0) {
        return 'medium';
    }

    return 'hard';
}

function resolveBalanceFlag(float $accuracyPercent, int $attempts): string
{
    if ($attempts < 30) {
        return 'insufficient_data';
    }

    if ($accuracyPercent < 35.0) {
        return 'too_hard';
    }

    if ($accuracyPercent > 80.0) {
        return 'too_easy';
    }

    return 'ok';
}

/**
 * Получение пользователей для админки с фильтрами
 */
function handleAdminUsers($container, ?array $telegramUser, array $query): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
    $search = trim((string) ($query['q'] ?? ''));
    $sort = (string) ($query['sort'] ?? 'updated_at');
    $order = strtolower((string) ($query['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    $allowedSort = ['updated_at', 'created_at', 'rating', 'experience', 'level'];
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'updated_at';
    }

    $usersQuery = \QuizBot\Domain\Model\User::query()->with('profile');

    if ($search !== '') {
        $usersQuery->where(function ($q) use ($search): void {
            $q->where('first_name', 'like', '%' . $search . '%')
                ->orWhere('last_name', 'like', '%' . $search . '%')
                ->orWhere('username', 'like', '%' . $search . '%')
                ->orWhere('telegram_id', 'like', '%' . $search . '%');
        });
    }

    if (in_array($sort, ['rating', 'experience', 'level'], true)) {
        $usersQuery
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->select('users.*')
            ->orderBy('user_profiles.' . $sort, $order);
    } else {
        $usersQuery->orderBy('users.' . $sort, $order);
    }

    $users = $usersQuery
        ->limit($limit)
        ->get()
        ->map(function ($u) {
            return [
                'id' => $u->getKey(),
                'telegram_id' => $u->telegram_id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'username' => $u->username,
                'created_at' => $u->created_at ? $u->created_at->toIso8601String() : null,
                'updated_at' => $u->updated_at ? $u->updated_at->toIso8601String() : null,
                'rating' => $u->profile ? (int) $u->profile->rating : 1000,
                'level' => $u->profile ? (int) $u->profile->level : 1,
                'experience' => $u->profile ? (int) $u->profile->experience : 0,
                'coins' => $u->profile ? (int) $u->profile->coins : 0,
                'gems' => $u->profile ? (int) $u->profile->gems : 0,
            ];
        })
        ->values();

    jsonResponse([
        'items' => $users,
        'count' => $users->count(),
    ]);
}

/**
 * Получение дуэлей для админки с фильтрами
 */
function handleAdminDuels($container, ?array $telegramUser, array $query): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
    $status = trim((string) ($query['status'] ?? ''));
    $search = strtoupper(trim((string) ($query['q'] ?? '')));

    $duelsQuery = \QuizBot\Domain\Model\Duel::query()
        ->with(['initiator.profile', 'opponent.profile', 'result'])
        ->orderByDesc('created_at');

    if ($status !== '' && $status !== 'all') {
        $duelsQuery->where('status', $status);
    }

    if ($search !== '') {
        $duelsQuery->where(function ($q) use ($search): void {
            $q->where('code', 'like', '%' . $search . '%')
                ->orWhere('initiator_user_id', $search)
                ->orWhere('opponent_user_id', $search);
        });
    }

    $duels = $duelsQuery
        ->limit($limit)
        ->get()
        ->map(function ($d) {
            return [
                'id' => $d->getKey(),
                'code' => $d->code,
                'status' => $d->status,
                'created_at' => $d->created_at ? $d->created_at->toIso8601String() : null,
                'updated_at' => $d->updated_at ? $d->updated_at->toIso8601String() : null,
                'initiator' => $d->initiator ? [
                    'id' => $d->initiator->getKey(),
                    'name' => $d->initiator->first_name,
                    'rating' => $d->initiator->profile ? (int) $d->initiator->profile->rating : 0,
                ] : null,
                'opponent' => $d->opponent ? [
                    'id' => $d->opponent->getKey(),
                    'name' => $d->opponent->first_name,
                    'rating' => $d->opponent->profile ? (int) $d->opponent->profile->rating : 0,
                ] : null,
                'result' => $d->result ? [
                    'initiator_score' => (int) $d->result->initiator_total_score,
                    'opponent_score' => (int) $d->result->opponent_total_score,
                ] : null,
            ];
        })
        ->values();

    $activeCount = \QuizBot\Domain\Model\Duel::query()
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->count();

    jsonResponse([
        'items' => $duels,
        'count' => $duels->count(),
        'active_duels' => $activeCount,
    ]);
}

/**
 * Админ: отменить дуэль по коду
 */
function handleAdminCancelDuelByCode($container, ?array $telegramUser, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $code = strtoupper(trim((string) ($body['code'] ?? '')));
    if ($code === '') {
        jsonError('Не указан код дуэли', 400);
    }

    $duel = \QuizBot\Domain\Model\Duel::query()
        ->where('code', $code)
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->first();

    if (!$duel) {
        jsonError('Активная дуэль с таким кодом не найдена', 404);
    }

    $duel->status = 'cancelled';
    $duel->finished_at = \Illuminate\Support\Carbon::now();
    $duel->save();

    notifyDuelRealtime($duel->getKey());
    jsonResponse([
        'cancelled' => true,
        'duel_id' => $duel->getKey(),
        'code' => $duel->code,
    ]);
}

/**
 * Получение фактов "Правда или ложь" для админки
 */
function handleAdminFacts($container, ?array $telegramUser, array $query): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $limit = max(1, min(200, (int) ($query['limit'] ?? 100)));
    $search = trim((string) ($query['q'] ?? ''));
    $truth = trim((string) ($query['truth'] ?? 'all'));
    $active = trim((string) ($query['active'] ?? 'all'));

    $factsQuery = \QuizBot\Domain\Model\TrueFalseFact::query()
        ->orderByDesc('id');

    if ($search !== '') {
        $factsQuery->where('statement', 'like', '%' . $search . '%');
    }

    if ($truth === 'true') {
        $factsQuery->where('is_true', true);
    } elseif ($truth === 'false') {
        $factsQuery->where('is_true', false);
    }

    if ($active === '1' || $active === 'true') {
        $factsQuery->where('is_active', true);
    } elseif ($active === '0' || $active === 'false') {
        $factsQuery->where('is_active', false);
    }

    $facts = $factsQuery
        ->limit($limit)
        ->get()
        ->map(function ($f) {
            return [
                'id' => $f->getKey(),
                'statement' => $f->statement,
                'explanation' => $f->explanation,
                'is_true' => (bool) $f->is_true,
                'is_active' => (bool) $f->is_active,
            ];
        })
        ->values();

    jsonResponse([
        'items' => $facts,
        'count' => $facts->count(),
    ]);
}

/**
 * Добавление факта "Правда или ложь"
 */
function handleAdminAddFact($container, ?array $telegramUser, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $statement = trim((string) ($body['statement'] ?? ''));
    $explanation = trim((string) ($body['explanation'] ?? ''));
    $isTrueRaw = $body['is_true'] ?? null;
    $isActive = array_key_exists('is_active', $body) ? (bool) $body['is_active'] : true;

    if ($statement === '' || $isTrueRaw === null) {
        jsonError('Укажите текст факта и значение правда/ложь', 400);
    }

    $fact = new \QuizBot\Domain\Model\TrueFalseFact([
        'statement' => $statement,
        'explanation' => $explanation !== '' ? $explanation : null,
        'is_true' => (bool) $isTrueRaw,
        'is_active' => $isActive,
    ]);
    $fact->save();

    jsonResponse([
        'id' => $fact->getKey(),
        'message' => 'Факт добавлен',
    ]);
}

/**
 * Переключение активности факта "Правда или ложь"
 */
function handleAdminToggleFact($container, ?array $telegramUser, int $factId, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $fact = \QuizBot\Domain\Model\TrueFalseFact::query()->find($factId);
    if (!$fact) {
        jsonError('Факт не найден', 404);
    }

    if (array_key_exists('is_active', $body)) {
        $fact->is_active = (bool) $body['is_active'];
    } else {
        $fact->is_active = !$fact->is_active;
    }
    $fact->save();

    jsonResponse([
        'id' => $fact->getKey(),
        'is_active' => (bool) $fact->is_active,
    ]);
}

// ============================================================================
// SHOP SYSTEM HANDLERS
// ============================================================================

/**
 * POST /admin/lootbox/grant - выдать лутбоксы игроку
 */
function handleAdminGrantLootbox($container, ?array $telegramUser, array $body): void
{
    if (!isAdmin($telegramUser, $container)) {
        jsonError('Доступ запрещён', 403);
    }

    $userId = (int) ($body['user_id'] ?? 0);
    $telegramId = (int) ($body['telegram_id'] ?? 0);
    $lootboxType = strtolower(trim((string) ($body['lootbox_type'] ?? '')));
    $quantity = max(1, min(999, (int) ($body['quantity'] ?? 1)));

    if ($userId <= 0 && $telegramId <= 0) {
        jsonError('Укажите user_id или telegram_id', 400);
    }

    $allowedTypes = ['bronze', 'silver', 'gold', 'legendary'];
    if (!in_array($lootboxType, $allowedTypes, true)) {
        jsonError('Некорректный тип лутбокса', 400);
    }

    /** @var UserService $userService */
    $userService = $container->get(UserService::class);

    $targetUser = null;
    if ($userId > 0) {
        $targetUser = \QuizBot\Domain\Model\User::query()->find($userId);
    } else {
        $targetUser = $userService->findByTelegramId($telegramId);
    }

    if (!$targetUser) {
        jsonError('Пользователь не найден', 404);
    }

    $existingItem = \QuizBot\Domain\Model\UserInventory::query()
        ->where('user_id', $targetUser->getKey())
        ->where('item_type', 'lootbox')
        ->where('item_key', $lootboxType)
        ->first();

    if ($existingItem) {
        $existingQuantity = (int) $existingItem->quantity;
        $existingItem->quantity = $existingQuantity + $quantity;
        $existingItem->acquired_at = \Illuminate\Support\Carbon::now();
        $existingItem->save();
        $totalQuantity = (int) $existingItem->quantity;
    } else {
        $created = \QuizBot\Domain\Model\UserInventory::query()->create([
            'user_id' => $targetUser->getKey(),
            'item_type' => 'lootbox',
            'item_id' => null,
            'item_key' => $lootboxType,
            'quantity' => $quantity,
            'expires_at' => null,
            'acquired_at' => \Illuminate\Support\Carbon::now(),
        ]);
        $totalQuantity = (int) ($created->quantity ?? $quantity);
    }

    jsonResponse([
        'message' => 'Лутбоксы выданы',
        'user_id' => (int) $targetUser->getKey(),
        'telegram_id' => (int) ($targetUser->telegram_id ?? 0),
        'lootbox_type' => $lootboxType,
        'added' => $quantity,
        'total_quantity' => $totalQuantity,
    ]);
}
