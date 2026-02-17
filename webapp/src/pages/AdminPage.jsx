import { useEffect, useMemo, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useNavigate } from 'react-router-dom'
import api from '../api/client'
import { hapticFeedback, showBackButton } from '../hooks/useTelegram'

const TABS = [
  { id: 'overview', label: 'Обзор' },
  { id: 'analytics', label: 'Аналитика' },
  { id: 'users', label: 'Игроки' },
  { id: 'duels', label: 'Дуэли' },
  { id: 'questions', label: 'Вопросы' },
  { id: 'facts', label: 'П/Л факты' },
  { id: 'lootboxes', label: 'Лутбоксы' },
  { id: 'broadcast', label: 'Рассылка' },
]

function AdminPage() {
  const navigate = useNavigate()

  const [activeTab, setActiveTab] = useState('overview')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [actionLoading, setActionLoading] = useState(false)
  const [confirm, setConfirm] = useState(null)

  const [autoRefresh, setAutoRefresh] = useState(true)

  const [stats, setStats] = useState(null)
  const [categoryAnalytics, setCategoryAnalytics] = useState([])
  const [analyticsTrend, setAnalyticsTrend] = useState([])
  const [questionAnalytics, setQuestionAnalytics] = useState([])
  const [analyticsSummary, setAnalyticsSummary] = useState(null)
  const [users, setUsers] = useState([])
  const [duels, setDuels] = useState([])
  const [facts, setFacts] = useState([])
  const [adminNotifications, setAdminNotifications] = useState([])

  const [userQuery, setUserQuery] = useState('')
  const [duelQuery, setDuelQuery] = useState('')
  const [duelStatus, setDuelStatus] = useState('all')
  const [duelDateFrom, setDuelDateFrom] = useState('')
  const [duelDateTo, setDuelDateTo] = useState('')
  const [duelDetailsOpen, setDuelDetailsOpen] = useState(false)
  const [duelDetailsLoading, setDuelDetailsLoading] = useState(false)
  const [duelDetails, setDuelDetails] = useState(null)
  const [duelDetailsError, setDuelDetailsError] = useState('')
  const [factQuery, setFactQuery] = useState('')
  const [factTruth, setFactTruth] = useState('all')
  const [analyticsDays, setAnalyticsDays] = useState(30)
  const [analyticsMode, setAnalyticsMode] = useState('duel')
  const [analyticsMinAttempts, setAnalyticsMinAttempts] = useState(3)
  const [analyticsCategoryId, setAnalyticsCategoryId] = useState('all')
  const [analyticsQuestionSort, setAnalyticsQuestionSort] = useState('attempts')
  const [analyticsQuestionOrder, setAnalyticsQuestionOrder] = useState('desc')
  const [analyticsQuestionQuery, setAnalyticsQuestionQuery] = useState('')

  const [questionForm, setQuestionForm] = useState({
    category_id: '',
    question_text: '',
    answers: ['', '', '', ''],
    correct_answer: 0,
  })
  const [factForm, setFactForm] = useState({
    statement: '',
    explanation: '',
    is_true: true,
    is_active: true,
  })
  const [duelCodeToCancel, setDuelCodeToCancel] = useState('')
  const [broadcastForm, setBroadcastForm] = useState({
    title: '',
    message: '',
  })
  const [grantForm, setGrantForm] = useState({
    target: '',
    target_type: 'telegram_id',
    lootbox_type: 'bronze',
    quantity: 1,
  })
  const [grantResult, setGrantResult] = useState(null)

  useEffect(() => {
    showBackButton(true)
    loadAll()
  }, [])

  useEffect(() => {
    if (!autoRefresh) return undefined
    const interval = setInterval(() => {
      if (activeTab === 'overview') {
        loadStats()
      } else if (activeTab === 'analytics') {
        loadAnalytics()
      } else if (activeTab === 'users') {
        loadUsers()
      } else if (activeTab === 'duels') {
        loadDuels()
      } else if (activeTab === 'facts') {
        loadFacts()
      } else if (activeTab === 'broadcast') {
        loadAdminNotifications()
      }
    }, 10000)
    return () => clearInterval(interval)
  }, [autoRefresh, activeTab, userQuery, duelQuery, duelStatus, duelDateFrom, duelDateTo, factQuery, factTruth, analyticsDays, analyticsMode, analyticsMinAttempts, analyticsCategoryId, analyticsQuestionSort, analyticsQuestionOrder, analyticsQuestionQuery])

  const loadAll = async () => {
    setLoading(true)
    setError(null)
    try {
      await Promise.all([loadStats(), loadAnalytics(), loadUsers(), loadDuels(), loadFacts(), loadAdminNotifications()])
    } catch (e) {
      setError(e.message || 'Ошибка загрузки админки')
    } finally {
      setLoading(false)
    }
  }

  const loadStats = async () => {
    const res = await api.getAdminStats()
    if (res.success) setStats(res.data)
  }

  const loadAnalytics = async () => {
    const categoryId = analyticsCategoryId === 'all' ? undefined : Number(analyticsCategoryId)
    const [categoryRes, questionRes] = await Promise.all([
      api.getAdminCategoryAnalytics({
        days: analyticsDays,
        mode: analyticsMode,
        min_attempts: Math.max(1, analyticsMinAttempts),
      }),
      api.getAdminQuestionAnalytics({
        days: analyticsDays,
        mode: analyticsMode,
        min_attempts: Math.max(1, analyticsMinAttempts),
        category_id: categoryId,
        sort: analyticsQuestionSort,
        order: analyticsQuestionOrder,
        q: analyticsQuestionQuery,
        limit: 200,
      }),
    ])

    if (categoryRes.success) {
      setCategoryAnalytics(categoryRes.data.items || [])
      setAnalyticsTrend(categoryRes.data.daily_trend || [])
    }

    if (questionRes.success) {
      setQuestionAnalytics(questionRes.data.items || [])
      setAnalyticsSummary(questionRes.data.summary || null)
    }
  }

  const loadUsers = async () => {
    const res = await api.getAdminUsers({
      q: userQuery,
      limit: 50,
      sort: 'updated_at',
      order: 'desc',
    })
    if (res.success) setUsers(res.data.items || [])
  }

  const loadDuels = async () => {
    const res = await api.getAdminDuels({
      q: duelQuery,
      status: duelStatus,
      date_from: duelDateFrom || undefined,
      date_to: duelDateTo || undefined,
      limit: 60,
    })
    if (res.success) setDuels(res.data.items || [])
  }

  const openDuelDetails = async (duelId) => {
    setDuelDetailsOpen(true)
    setDuelDetailsLoading(true)
    setDuelDetailsError('')
    setDuelDetails(null)
    try {
      const res = await api.getAdminDuelDetails(duelId)
      if (!res.success) throw new Error(res.error || 'Не удалось загрузить детали дуэли')
      setDuelDetails(res.data || null)
    } catch (e) {
      setDuelDetailsError(e.message || 'Не удалось загрузить детали дуэли')
    } finally {
      setDuelDetailsLoading(false)
    }
  }

  const formatDateTime = (value) => {
    if (!value) return '—'
    try {
      return new Date(value).toLocaleString('ru-RU')
    } catch (_) {
      return String(value)
    }
  }

  const formatDuration = (seconds) => {
    const total = Number(seconds)
    if (!Number.isFinite(total) || total < 0) return '—'
    const h = Math.floor(total / 3600)
    const m = Math.floor((total % 3600) / 60)
    const s = total % 60
    if (h > 0) return `${h}ч ${m}м ${s}с`
    if (m > 0) return `${m}м ${s}с`
    return `${s}с`
  }

  const loadFacts = async () => {
    const res = await api.getAdminFacts({
      q: factQuery,
      truth: factTruth,
      limit: 100,
    })
    if (res.success) setFacts(res.data.items || [])
  }

  const loadAdminNotifications = async () => {
    const res = await api.getAdminNotifications({ limit: 30 })
    if (res.success) setAdminNotifications(res.data.items || [])
  }

  const activeDuelsCount = useMemo(
    () => duels.filter((d) => ['waiting', 'matched', 'in_progress'].includes(d.status)).length,
    [duels]
  )

  const balanceFlagLabel = (flag) => {
    if (flag === 'too_hard') return 'Слишком сложно'
    if (flag === 'too_easy') return 'Слишком легко'
    if (flag === 'insufficient_data') return 'Мало данных'
    return 'Ок'
  }

  const balanceFlagClass = (flag) => {
    if (flag === 'too_hard') return 'text-red-200 border-red-400/40 bg-red-500/10'
    if (flag === 'too_easy') return 'text-amber-200 border-amber-400/40 bg-amber-500/10'
    if (flag === 'insufficient_data') return 'text-slate-300 border-slate-400/30 bg-slate-500/10'
    return 'text-emerald-200 border-emerald-400/40 bg-emerald-500/10'
  }

  const escapeCsv = (value) => {
    const text = String(value ?? '')
    if (/[",;\n]/.test(text)) {
      return `"${text.replace(/"/g, '""')}"`
    }
    return text
  }

  const downloadCsvFile = (filename, headers, rows) => {
    const headerLine = headers.map(escapeCsv).join(';')
    const contentLines = rows.map((row) => row.map(escapeCsv).join(';'))
    const csv = [headerLine, ...contentLines].join('\n')
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  }

  const exportCategoryAnalyticsCsv = () => {
    const rows = categoryAnalytics.map((item) => ([
      item.category_id,
      item.category_title,
      item.attempts,
      item.correct_answers,
      item.accuracy,
      item.avg_time_seconds,
      item.unique_players,
      item.questions_seen,
      item.total_questions,
      item.coverage_percent,
      item.difficulty_band,
    ]))

    downloadCsvFile(
      `analytics_categories_${analyticsMode}_${analyticsDays}d.csv`,
      ['category_id', 'category_title', 'attempts', 'correct_answers', 'accuracy_percent', 'avg_time_seconds', 'unique_players', 'questions_seen', 'total_questions', 'coverage_percent', 'difficulty_band'],
      rows
    )
  }

  const exportQuestionAnalyticsCsv = () => {
    const rows = questionAnalytics.map((item) => ([
      item.question_id,
      item.category_id,
      item.category_title,
      item.attempts,
      item.correct_answers,
      item.accuracy,
      item.avg_time_seconds,
      item.unique_players,
      item.difficulty_band,
      item.question_text,
    ]))

    downloadCsvFile(
      `analytics_questions_${analyticsMode}_${analyticsDays}d.csv`,
      ['question_id', 'category_id', 'category_title', 'attempts', 'correct_answers', 'accuracy_percent', 'avg_time_seconds', 'unique_players', 'difficulty_band', 'question_text'],
      rows
    )
  }

  const runAction = async (fn) => {
    setActionLoading(true)
    try {
      await fn()
      hapticFeedback('success')
      await Promise.all([loadStats(), loadDuels(), loadFacts()])
    } catch (e) {
      alert(e.message || 'Ошибка действия')
    } finally {
      setActionLoading(false)
      setConfirm(null)
    }
  }

  const handleCancelAll = async () => {
    await runAction(async () => {
      const res = await api.adminCancelAllDuels()
      if (!res.success) throw new Error(res.error || 'Не удалось завершить дуэли')
    })
  }

  const handleCancelById = async (duelId) => {
    await runAction(async () => {
      const res = await api.adminCancelDuel(duelId)
      if (!res.success) throw new Error(res.error || 'Не удалось завершить дуэль')
    })
  }

  const handleCancelByCode = async () => {
    const code = duelCodeToCancel.trim()
    if (code.length < 5) return
    await runAction(async () => {
      const res = await api.adminCancelDuelByCode(code)
      if (!res.success) throw new Error(res.error || 'Не удалось завершить дуэль по коду')
      setDuelCodeToCancel('')
    })
  }

  const handleAddQuestion = async () => {
    const answers = questionForm.answers.filter((a) => a.trim() !== '')
    if (!questionForm.category_id || !questionForm.question_text.trim() || answers.length < 2) {
      alert('Заполните категорию, вопрос и минимум 2 ответа')
      return
    }

    setActionLoading(true)
    try {
      const res = await api.adminAddQuestion({
        category_id: Number(questionForm.category_id),
        question_text: questionForm.question_text.trim(),
        answers,
        correct_answer: questionForm.correct_answer,
      })
      if (!res.success) throw new Error(res.error || 'Не удалось добавить вопрос')
      hapticFeedback('success')
      setQuestionForm((prev) => ({
        ...prev,
        question_text: '',
        answers: ['', '', '', ''],
        correct_answer: 0,
      }))
      await loadStats()
    } catch (e) {
      alert(e.message || 'Ошибка')
    } finally {
      setActionLoading(false)
    }
  }

  const handleAddFact = async () => {
    if (!factForm.statement.trim()) {
      alert('Введите текст факта')
      return
    }

    setActionLoading(true)
    try {
      const res = await api.adminAddFact({
        statement: factForm.statement.trim(),
        explanation: factForm.explanation.trim(),
        is_true: factForm.is_true,
        is_active: factForm.is_active,
      })
      if (!res.success) throw new Error(res.error || 'Не удалось добавить факт')
      hapticFeedback('success')
      setFactForm({
        statement: '',
        explanation: '',
        is_true: true,
        is_active: true,
      })
      await Promise.all([loadFacts(), loadStats()])
    } catch (e) {
      alert(e.message || 'Ошибка')
    } finally {
      setActionLoading(false)
    }
  }

  const toggleFact = async (factId, nextState) => {
    await runAction(async () => {
      const res = await api.adminToggleFact(factId, nextState)
      if (!res.success) throw new Error(res.error || 'Не удалось изменить активность факта')
    })
  }

  const handleBroadcast = async () => {
    const title = broadcastForm.title.trim()
    const message = broadcastForm.message.trim()
    if (!title || !message) {
      alert('Укажи заголовок и текст уведомления')
      return
    }

    setActionLoading(true)
    try {
      const res = await api.adminBroadcastNotification(title, message)
      if (!res.success) throw new Error(res.error || 'Не удалось отправить рассылку')

      setBroadcastForm({ title: '', message: '' })
      hapticFeedback('success')
      await loadAdminNotifications()
    } catch (e) {
      alert(e.message || 'Ошибка')
    } finally {
      setActionLoading(false)
    }
  }

  const handleGrantLootbox = async () => {
    const target = grantForm.target.trim()
    const quantity = Math.max(1, Number(grantForm.quantity || 1))
    if (!target) {
      alert('Укажи ID пользователя')
      return
    }

    setActionLoading(true)
    setGrantResult(null)
    try {
      const payload = {
        lootbox_type: grantForm.lootbox_type,
        quantity,
      }
      if (grantForm.target_type === 'user_id') {
        payload.user_id = Number(target)
      } else {
        payload.telegram_id = Number(target)
      }

      const res = await api.adminGrantLootbox(payload)
      if (!res.success) throw new Error(res.error || 'Не удалось выдать лутбокс')

      const data = res.data || {}
      setGrantResult({
        message: data.message || 'Лутбоксы выданы',
        user_id: data.user_id,
        telegram_id: data.telegram_id,
        lootbox_type: data.lootbox_type,
        added: data.added,
        total: data.total_quantity,
      })
      hapticFeedback('success')
    } catch (e) {
      alert(e.message || 'Ошибка')
    } finally {
      setActionLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative flex items-center justify-center">
        <div className="noise-overlay" />
        <div className="relative z-10 text-white/70">Загрузка админ-панели...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-dvh bg-aurora relative p-6">
        <div className="noise-overlay" />
        <div className="relative z-10 max-w-md mx-auto pt-10">
          <h1 className="text-2xl text-red-300 font-bold mb-2">Доступ запрещён</h1>
          <p className="text-white/60 mb-6">{error}</p>
          <button onClick={() => navigate('/')} className="px-5 py-3 rounded-xl bg-white/10 text-white">
            На главную
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative pb-24">
      <div className="noise-overlay" />
      <div className="relative z-10 max-w-5xl mx-auto px-4 pt-4">
        <div className="rounded-3xl border border-white/10 bg-black/30 backdrop-blur-xl p-4 mb-4">
          <div className="flex items-center justify-between gap-3 mb-4">
            <div>
              <h1 className="text-white text-2xl font-black">Админ-панель</h1>
              <p className="text-white/50 text-sm">Управление игрой, модерация и контент</p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setAutoRefresh((v) => !v)}
                className={`px-3 py-2 rounded-xl text-xs font-semibold ${autoRefresh ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30' : 'bg-white/10 text-white/60 border border-white/15'}`}
              >
                {autoRefresh ? 'Автообновление: ON' : 'Автообновление: OFF'}
              </button>
              <button onClick={loadAll} className="px-3 py-2 rounded-xl bg-white/10 text-white/80 text-xs font-semibold border border-white/15">
                Обновить
              </button>
            </div>
          </div>

          <div className="flex gap-2 overflow-x-auto scrollbar-hide pb-1">
            {TABS.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`px-4 py-2 rounded-xl text-sm whitespace-nowrap ${
                  activeTab === tab.id ? 'bg-white text-slate-900 font-semibold' : 'bg-white/10 text-white/70'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        {activeTab === 'overview' && stats && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <Kpi title="Игроки" value={stats.total_users} />
            <Kpi title="Активных 24ч" value={stats.active_today} />
            <Kpi title="Дуэли" value={stats.total_duels} />
            <Kpi title="Активные дуэли" value={stats.active_duels} />
            <Kpi title="Вопросы" value={stats.total_questions} />
            <Kpi title="Факты П/Л" value={stats.total_facts} />
            <Kpi title="Новых 24ч" value={stats.new_users_today} />
            <Kpi title="Дуэлей 24ч" value={stats.duels_today} />
          </motion.div>
        )}

        {activeTab === 'analytics' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <div className="rounded-2xl border border-white/10 bg-black/25 p-4 space-y-3">
              <div className="text-sm text-white/70">Фильтры аналитики</div>
              <div className="grid md:grid-cols-3 gap-2">
                <select
                  value={analyticsDays}
                  onChange={(e) => setAnalyticsDays(Number(e.target.value))}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value={7}>За 7 дней</option>
                  <option value={14}>За 14 дней</option>
                  <option value={30}>За 30 дней</option>
                  <option value={90}>За 90 дней</option>
                </select>
                <select
                  value={analyticsMode}
                  onChange={(e) => setAnalyticsMode(e.target.value)}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value="duel">Только дуэли</option>
                  <option value="all">Все режимы</option>
                  <option value="quiz">Обычная игра</option>
                  <option value="story">Сюжет</option>
                </select>
                <input
                  type="number"
                  min={1}
                  max={5000}
                  value={analyticsMinAttempts}
                  onChange={(e) => setAnalyticsMinAttempts(Number(e.target.value || 1))}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                  placeholder="Мин. попыток"
                />
              </div>

              <div className="grid md:grid-cols-5 gap-2">
                <select
                  value={analyticsCategoryId}
                  onChange={(e) => setAnalyticsCategoryId(e.target.value)}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value="all">Все категории</option>
                  {(stats?.categories || []).map((c) => (
                    <option key={c.id} value={c.id}>{c.title}</option>
                  ))}
                </select>
                <select
                  value={analyticsQuestionSort}
                  onChange={(e) => setAnalyticsQuestionSort(e.target.value)}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value="attempts">Сорт: попытки</option>
                  <option value="accuracy">Сорт: точность</option>
                  <option value="avg_time">Сорт: время</option>
                  <option value="players">Сорт: игроки</option>
                  <option value="question_id">Сорт: ID</option>
                </select>
                <select
                  value={analyticsQuestionOrder}
                  onChange={(e) => setAnalyticsQuestionOrder(e.target.value)}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value="desc">По убыванию</option>
                  <option value="asc">По возрастанию</option>
                </select>
                <input
                  value={analyticsQuestionQuery}
                  onChange={(e) => setAnalyticsQuestionQuery(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && loadAnalytics()}
                  placeholder="Поиск по вопросу"
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                />
                <button onClick={loadAnalytics} className="rounded-xl bg-white/10 text-white/80 px-4 py-3">
                  Применить
                </button>
              </div>

              <div className="grid md:grid-cols-2 gap-2">
                <button
                  onClick={exportCategoryAnalyticsCsv}
                  disabled={categoryAnalytics.length === 0}
                  className="rounded-xl bg-cyan-500/20 border border-cyan-400/30 text-cyan-200 px-4 py-3 disabled:opacity-40"
                >
                  Экспорт CSV: категории
                </button>
                <button
                  onClick={exportQuestionAnalyticsCsv}
                  disabled={questionAnalytics.length === 0}
                  className="rounded-xl bg-violet-500/20 border border-violet-400/30 text-violet-200 px-4 py-3 disabled:opacity-40"
                >
                  Экспорт CSV: вопросы
                </button>
              </div>
            </div>

            {analyticsSummary && (
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Kpi title="Вопросов в отчёте" value={analyticsSummary.questions} />
                <Kpi title="Попыток" value={analyticsSummary.attempts} />
                <Kpi title="Точность (%)" value={analyticsSummary.accuracy} />
                <Kpi title="Верных ответов" value={analyticsSummary.correct_answers} />
              </div>
            )}

            <div className="rounded-2xl border border-white/10 bg-black/25 p-4">
              <div className="text-white text-sm font-semibold mb-3">Дневной тренд</div>
              <div className="flex gap-2 overflow-x-auto scrollbar-hide">
                {analyticsTrend.map((point) => (
                  <div key={point.day} className="min-w-[124px] rounded-xl border border-white/10 bg-black/20 p-2.5 text-xs">
                    <div className="text-white/60 mb-1">{point.day}</div>
                    <div className="text-white">Попытки: {point.attempts}</div>
                    <div className="text-white/80">Точность: {point.accuracy}%</div>
                  </div>
                ))}
                {analyticsTrend.length === 0 && (
                  <div className="text-white/50 text-sm">Нет данных по тренду.</div>
                )}
              </div>
            </div>

            <div className="rounded-2xl border border-white/10 bg-black/25 p-4">
              <div className="text-white text-sm font-semibold mb-3">Категории: точность и покрытие</div>
              <div className="space-y-2">
                {categoryAnalytics.map((item) => (
                  <div key={item.category_id} className="rounded-xl border border-white/10 bg-black/20 p-3 grid grid-cols-1 md:grid-cols-6 gap-2 text-xs">
                    <div className="text-white">
                      <div className="font-semibold">{item.category_icon} {item.category_title}</div>
                      <div className="text-white/50 flex items-center gap-1.5">
                        <span>Сложность: {item.difficulty_band}</span>
                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 border ${balanceFlagClass(item.balance_flag)}`}>
                          {balanceFlagLabel(item.balance_flag)}
                        </span>
                      </div>
                    </div>
                    <div className="text-white/70">Попытки: <span className="text-white">{item.attempts}</span></div>
                    <div className="text-white/70">Точность: <span className="text-white">{item.accuracy}%</span></div>
                    <div className="text-white/70">Ср. время: <span className="text-white">{item.avg_time_seconds}с</span></div>
                    <div className="text-white/70">Игроков: <span className="text-white">{item.unique_players}</span></div>
                    <div className="text-white/70">Покрытие: <span className="text-white">{item.questions_seen}/{item.total_questions} ({item.coverage_percent}%)</span></div>
                  </div>
                ))}
                {categoryAnalytics.length === 0 && (
                  <div className="text-white/50 text-sm">Нет данных по выбранным фильтрам.</div>
                )}
              </div>
            </div>

            <div className="rounded-2xl border border-white/10 bg-black/25 p-4">
              <div className="text-white text-sm font-semibold mb-3">Вопросы: детализация</div>
              <div className="space-y-2 max-h-[52vh] overflow-auto pr-1">
                {questionAnalytics.map((item) => (
                  <div key={item.question_id} className="rounded-xl border border-white/10 bg-black/20 p-3">
                    <div className="text-white text-sm font-semibold mb-1">
                      #{item.question_id} · {item.category_icon} {item.category_title}
                    </div>
                    <div className="text-white/80 text-sm mb-2">{item.question_text}</div>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
                      <div className="text-white/70">Попытки: <span className="text-white">{item.attempts}</span></div>
                      <div className="text-white/70">Точность: <span className="text-white">{item.accuracy}%</span></div>
                      <div className="text-white/70">Ср. время: <span className="text-white">{item.avg_time_seconds}с</span></div>
                      <div className="text-white/70">Игроков: <span className="text-white">{item.unique_players}</span></div>
                      <div className="text-white/70 flex items-center gap-1.5">
                        <span>Сложность: <span className="text-white">{item.difficulty_band}</span></span>
                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 border ${balanceFlagClass(item.balance_flag)}`}>
                          {balanceFlagLabel(item.balance_flag)}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
                {questionAnalytics.length === 0 && (
                  <div className="text-white/50 text-sm">Нет данных по выбранным фильтрам.</div>
                )}
              </div>
            </div>
          </motion.div>
        )}

        {activeTab === 'users' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <input
              value={userQuery}
              onChange={(e) => setUserQuery(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && loadUsers()}
              placeholder="Поиск: имя / username / telegram_id"
              className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
            />
            <button onClick={loadUsers} className="px-4 py-2 rounded-xl bg-white/10 text-white/80 text-sm">Искать</button>
            <div className="space-y-2">
              {users.map((u) => (
                <div key={u.id} className="rounded-xl border border-white/10 bg-black/25 p-3 flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <div className="text-white font-semibold truncate">{u.first_name} {u.last_name || ''}</div>
                    <div className="text-white/50 text-xs truncate">@{u.username || '—'} · tg:{u.telegram_id}</div>
                  </div>
                  <div className="text-right text-xs">
                    <div className="text-cyan-200">MMR {u.rating}</div>
                    <div className="text-emerald-200">LVL {u.level} · XP {u.experience}</div>
                  </div>
                </div>
              ))}
            </div>
          </motion.div>
        )}

        {activeTab === 'duels' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <div className="grid md:grid-cols-5 gap-2">
              <input
                value={duelQuery}
                onChange={(e) => setDuelQuery(e.target.value.toUpperCase())}
                onKeyDown={(e) => e.key === 'Enter' && loadDuels()}
                placeholder="Поиск: код / user id"
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <select
                value={duelStatus}
                onChange={(e) => setDuelStatus(e.target.value)}
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              >
                <option value="all">Все статусы</option>
                <option value="waiting">waiting</option>
                <option value="matched">matched</option>
                <option value="in_progress">in_progress</option>
                <option value="finished">finished</option>
                <option value="cancelled">cancelled</option>
              </select>
              <input
                type="date"
                value={duelDateFrom}
                onChange={(e) => setDuelDateFrom(e.target.value)}
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <input
                type="date"
                value={duelDateTo}
                onChange={(e) => setDuelDateTo(e.target.value)}
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <button onClick={loadDuels} className="rounded-xl bg-white/10 text-white/80 px-4 py-3">Применить</button>
            </div>

            <div className="grid md:grid-cols-3 gap-2">
              <input
                value={duelCodeToCancel}
                onChange={(e) => setDuelCodeToCancel(e.target.value.replace(/\D+/g, '').slice(0, 5))}
                placeholder="Код дуэли для завершения"
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <button
                onClick={handleCancelByCode}
                disabled={actionLoading || duelCodeToCancel.length !== 5}
                className="rounded-xl bg-red-500/20 border border-red-400/30 text-red-200 px-4 py-3 disabled:opacity-40"
              >
                Завершить по коду
              </button>
              <button
                onClick={() => setConfirm({ type: 'all_duels' })}
                disabled={actionLoading || activeDuelsCount === 0}
                className="rounded-xl bg-red-500/20 border border-red-400/30 text-red-200 px-4 py-3 disabled:opacity-40"
              >
                Завершить все активные ({activeDuelsCount})
              </button>
            </div>

            <div className="space-y-2">
              {duels.map((d) => (
                <div
                  key={d.id}
                  className="rounded-xl border border-white/10 bg-black/25 p-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-white/[0.07] transition-colors"
                  onClick={() => openDuelDetails(d.id)}
                  role="button"
                  tabIndex={0}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') openDuelDetails(d.id)
                  }}
                >
                  <div className="min-w-0">
                    <div className="text-white font-semibold">{d.code} · {d.status}</div>
                    <div className="text-white/60 text-xs truncate">
                      {d.initiator?.name || '???'} vs {d.opponent?.name || '???'}
                    </div>
                    <div className="text-white/45 text-[11px] truncate mt-1">
                      Создана: {formatDateTime(d.created_at)}
                    </div>
                  </div>
                  {['waiting', 'matched', 'in_progress'].includes(d.status) && (
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        setConfirm({ type: 'duel', duelId: d.id, code: d.code })
                      }}
                      className="px-3 py-2 rounded-lg bg-red-500/20 border border-red-400/30 text-red-200 text-xs"
                    >
                      Завершить
                    </button>
                  )}
                </div>
              ))}
            </div>
          </motion.div>
        )}

        {activeTab === 'questions' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <select
              value={questionForm.category_id}
              onChange={(e) => setQuestionForm((prev) => ({ ...prev, category_id: e.target.value }))}
              className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
            >
              <option value="">Категория</option>
              {(stats?.categories || []).map((c) => (
                <option key={c.id} value={c.id}>{c.title}</option>
              ))}
            </select>
            <textarea
              rows={3}
              value={questionForm.question_text}
              onChange={(e) => setQuestionForm((prev) => ({ ...prev, question_text: e.target.value }))}
              placeholder="Текст вопроса"
              className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
            />
            {questionForm.answers.map((answer, idx) => (
              <div key={idx} className="flex items-center gap-2">
                <input
                  type="radio"
                  checked={questionForm.correct_answer === idx}
                  onChange={() => setQuestionForm((prev) => ({ ...prev, correct_answer: idx }))}
                />
                <input
                  value={answer}
                  onChange={(e) => {
                    const next = [...questionForm.answers]
                    next[idx] = e.target.value
                    setQuestionForm((prev) => ({ ...prev, answers: next }))
                  }}
                  placeholder={`Ответ ${idx + 1}`}
                  className="flex-1 rounded-xl bg-black/30 border border-white/15 px-4 py-2.5 text-white"
                />
              </div>
            ))}
            <button
              onClick={handleAddQuestion}
              disabled={actionLoading}
              className="w-full rounded-xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 px-4 py-3 disabled:opacity-40"
            >
              Добавить вопрос
            </button>
          </motion.div>
        )}

        {activeTab === 'facts' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <div className="grid md:grid-cols-3 gap-2">
              <input
                value={factQuery}
                onChange={(e) => setFactQuery(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && loadFacts()}
                placeholder="Поиск по тексту факта"
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <select
                value={factTruth}
                onChange={(e) => setFactTruth(e.target.value)}
                className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              >
                <option value="all">Все</option>
                <option value="true">Только правда</option>
                <option value="false">Только ложь</option>
              </select>
              <button onClick={loadFacts} className="rounded-xl bg-white/10 text-white/80 px-4 py-3">Применить</button>
            </div>

            <div className="rounded-2xl border border-white/10 bg-black/25 p-4 space-y-2">
              <div className="text-sm text-white/70">Добавить новый факт</div>
              <textarea
                rows={2}
                value={factForm.statement}
                onChange={(e) => setFactForm((prev) => ({ ...prev, statement: e.target.value }))}
                placeholder="Текст факта"
                className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <textarea
                rows={2}
                value={factForm.explanation}
                onChange={(e) => setFactForm((prev) => ({ ...prev, explanation: e.target.value }))}
                placeholder="Пояснение (опционально)"
                className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <div className="flex gap-3 text-sm text-white/80">
                <label className="flex items-center gap-2"><input type="radio" checked={factForm.is_true} onChange={() => setFactForm((prev) => ({ ...prev, is_true: true }))} /> Правда</label>
                <label className="flex items-center gap-2"><input type="radio" checked={!factForm.is_true} onChange={() => setFactForm((prev) => ({ ...prev, is_true: false }))} /> Ложь</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={factForm.is_active} onChange={(e) => setFactForm((prev) => ({ ...prev, is_active: e.target.checked }))} /> Активен</label>
              </div>
              <button onClick={handleAddFact} disabled={actionLoading} className="w-full rounded-xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 px-4 py-3 disabled:opacity-40">
                Добавить факт
              </button>
            </div>

            <div className="space-y-2">
              {facts.map((f) => (
                <div key={f.id} className="rounded-xl border border-white/10 bg-black/25 p-3 flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="text-white text-sm">{f.statement}</div>
                    <div className="text-xs mt-1">
                      <span className={f.is_true ? 'text-emerald-200' : 'text-red-200'}>{f.is_true ? 'Правда' : 'Ложь'}</span>
                      <span className="text-white/40"> · {f.is_active ? 'активен' : 'выключен'}</span>
                    </div>
                  </div>
                  <button
                    onClick={() => toggleFact(f.id, !f.is_active)}
                    className={`px-3 py-2 rounded-lg text-xs ${f.is_active ? 'bg-red-500/20 border border-red-400/30 text-red-200' : 'bg-emerald-500/20 border border-emerald-400/30 text-emerald-200'}`}
                  >
                    {f.is_active ? 'Выключить' : 'Включить'}
                  </button>
                </div>
              ))}
            </div>
          </motion.div>
        )}

        {activeTab === 'lootboxes' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <div className="rounded-2xl border border-white/10 bg-black/25 p-4 space-y-3">
              <div className="text-sm text-white/70">Выдать лутбоксы конкретному игроку</div>
              <div className="grid md:grid-cols-2 gap-2">
                <select
                  value={grantForm.target_type}
                  onChange={(e) => setGrantForm((prev) => ({ ...prev, target_type: e.target.value }))}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value="telegram_id">По Telegram ID</option>
                  <option value="user_id">По внутреннему User ID</option>
                </select>
                <input
                  value={grantForm.target}
                  onChange={(e) => setGrantForm((prev) => ({ ...prev, target: e.target.value.replace(/\D+/g, '') }))}
                  placeholder={grantForm.target_type === 'telegram_id' ? 'Telegram ID игрока' : 'User ID игрока'}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                />
              </div>

              <div className="grid md:grid-cols-3 gap-2">
                <select
                  value={grantForm.lootbox_type}
                  onChange={(e) => setGrantForm((prev) => ({ ...prev, lootbox_type: e.target.value }))}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                >
                  <option value="bronze">Бронзовый</option>
                  <option value="silver">Серебряный</option>
                  <option value="gold">Золотой</option>
                  <option value="legendary">Легендарный</option>
                </select>
                <input
                  type="number"
                  min={1}
                  max={999}
                  value={grantForm.quantity}
                  onChange={(e) => setGrantForm((prev) => ({ ...prev, quantity: e.target.value }))}
                  className="rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
                />
                <button
                  onClick={handleGrantLootbox}
                  disabled={actionLoading}
                  className="rounded-xl bg-violet-500/20 border border-violet-400/30 text-violet-200 px-4 py-3 disabled:opacity-40"
                >
                  Выдать
                </button>
              </div>
              <div className="text-[11px] text-white/45">
                Поддерживаются типы: bronze, silver, gold, legendary
              </div>
            </div>

            {grantResult && (
              <div className="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-4">
                <div className="text-emerald-100 text-sm">{grantResult.message}</div>
                <div className="text-emerald-200/80 text-xs mt-1">
                  user_id: {grantResult.user_id} · tg: {grantResult.telegram_id}
                </div>
                <div className="text-emerald-200/80 text-xs mt-1">
                  Тип: {grantResult.lootbox_type} · добавлено: {grantResult.added} · всего: {grantResult.total}
                </div>
              </div>
            )}
          </motion.div>
        )}

        {activeTab === 'broadcast' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-3">
            <div className="rounded-2xl border border-white/10 bg-black/25 p-4 space-y-2">
              <div className="text-sm text-white/70">Рассылка всем игрокам</div>
              <input
                value={broadcastForm.title}
                onChange={(e) => setBroadcastForm((prev) => ({ ...prev, title: e.target.value }))}
                placeholder="Заголовок уведомления"
                maxLength={160}
                className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <textarea
                rows={4}
                value={broadcastForm.message}
                onChange={(e) => setBroadcastForm((prev) => ({ ...prev, message: e.target.value }))}
                placeholder="Текст уведомления"
                maxLength={3000}
                className="w-full rounded-xl bg-black/30 border border-white/15 px-4 py-3 text-white"
              />
              <button
                onClick={handleBroadcast}
                disabled={actionLoading}
                className="w-full rounded-xl bg-amber-500/20 border border-amber-400/30 text-amber-200 px-4 py-3 disabled:opacity-40"
              >
                Отправить рассылку
              </button>
            </div>

            <div className="space-y-2">
              {adminNotifications.map((item) => (
                <div key={item.id} className="rounded-xl border border-white/10 bg-black/25 p-3">
                  <div className="text-white font-semibold text-sm">{item.title}</div>
                  <div className="text-white/70 text-xs mt-1 whitespace-pre-wrap">{item.message}</div>
                  <div className="text-white/40 text-[11px] mt-2">{new Date(item.created_at).toLocaleString('ru-RU')}</div>
                </div>
              ))}
            </div>
          </motion.div>
        )}
      </div>

      <AnimatePresence>
        {confirm && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
            <motion.div initial={{ scale: 0.95 }} animate={{ scale: 1 }} exit={{ scale: 0.95 }} className="w-full max-w-sm rounded-2xl border border-white/10 bg-dark-950 p-5">
              <div className="text-white text-lg font-semibold mb-2">Подтвердите действие</div>
              <div className="text-white/60 text-sm mb-4">
                {confirm.type === 'all_duels'
                  ? 'Завершить все активные дуэли?'
                  : `Завершить дуэль ${confirm.code}?`}
              </div>
              <div className="flex gap-2">
                <button onClick={() => setConfirm(null)} className="flex-1 px-4 py-2 rounded-xl bg-white/10 text-white/70">Отмена</button>
                <button
                  onClick={() => {
                    if (confirm.type === 'all_duels') handleCancelAll()
                    else handleCancelById(confirm.duelId)
                  }}
                  disabled={actionLoading}
                  className="flex-1 px-4 py-2 rounded-xl bg-red-500/20 border border-red-400/30 text-red-200 disabled:opacity-40"
                >
                  {actionLoading ? '...' : 'Завершить'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}

        {duelDetailsOpen && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-50 bg-black/75 p-4 overflow-auto">
            <motion.div initial={{ scale: 0.98 }} animate={{ scale: 1 }} exit={{ scale: 0.98 }} className="w-full max-w-4xl mx-auto rounded-2xl border border-white/10 bg-dark-950 p-4 md:p-5">
              <div className="flex items-start justify-between gap-2 mb-4">
                <div>
                  <div className="text-white text-xl font-semibold">Детали дуэли</div>
                  <div className="text-white/50 text-xs">Максимальная информация по матчу</div>
                </div>
                <button onClick={() => setDuelDetailsOpen(false)} className="px-3 py-2 rounded-xl bg-white/10 text-white/70">Закрыть</button>
              </div>

              {duelDetailsLoading && (
                <div className="text-white/70 text-sm">Загрузка деталей...</div>
              )}

              {!duelDetailsLoading && duelDetailsError && (
                <div className="rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-red-200 text-sm">{duelDetailsError}</div>
              )}

              {!duelDetailsLoading && !duelDetailsError && duelDetails?.duel && (
                <div className="space-y-3">
                  <div className="rounded-xl border border-white/10 bg-black/25 p-3">
                    <div className="text-white font-semibold mb-1">
                      #{duelDetails.duel.id} · {duelDetails.duel.code} · {duelDetails.duel.status}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-white/70">
                      <div>Создана: <span className="text-white">{formatDateTime(duelDetails.duel.created_at)}</span></div>
                      <div>Старт: <span className="text-white">{formatDateTime(duelDetails.duel.started_at)}</span></div>
                      <div>Финиш: <span className="text-white">{formatDateTime(duelDetails.duel.finished_at)}</span></div>
                      <div>Матчмейкинг: <span className="text-white">{formatDateTime(duelDetails.duel.matched_at)}</span></div>
                      <div>Длительность: <span className="text-white">{formatDuration(duelDetails.duel.duration_seconds)}</span></div>
                      <div>Раундов до победы: <span className="text-white">{duelDetails.duel.rounds_to_win ?? '—'}</span></div>
                    </div>
                  </div>

                  <div className="rounded-xl border border-white/10 bg-black/25 p-3">
                    <div className="text-white text-sm font-semibold mb-2">Участники</div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                      <div className="rounded-lg border border-white/10 bg-black/20 p-2">
                        <div className="text-white font-semibold">Инициатор: {duelDetails.duel.initiator?.name || '—'}</div>
                        <div className="text-white/60">user_id: {duelDetails.duel.initiator?.id ?? '—'} · tg: {duelDetails.duel.initiator?.telegram_id ?? '—'}</div>
                        <div className="text-white/60">username: {duelDetails.duel.initiator?.username ? `@${duelDetails.duel.initiator.username}` : '—'}</div>
                        <div className="text-white/60">Текущий рейтинг: {duelDetails.duel.initiator?.rating ?? '—'}</div>
                      </div>
                      <div className="rounded-lg border border-white/10 bg-black/20 p-2">
                        <div className="text-white font-semibold">Оппонент: {duelDetails.duel.opponent?.name || '—'}</div>
                        <div className="text-white/60">user_id: {duelDetails.duel.opponent?.id ?? '—'} · tg: {duelDetails.duel.opponent?.telegram_id ?? '—'}</div>
                        <div className="text-white/60">username: {duelDetails.duel.opponent?.username ? `@${duelDetails.duel.opponent.username}` : '—'}</div>
                        <div className="text-white/60">Текущий рейтинг: {duelDetails.duel.opponent?.rating ?? '—'}</div>
                      </div>
                    </div>
                  </div>

                  <div className="rounded-xl border border-white/10 bg-black/25 p-3">
                    <div className="text-white text-sm font-semibold mb-2">Итог матча</div>
                    {duelDetails.duel.result ? (
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-white/70">
                        <div>Результат: <span className="text-white">{duelDetails.duel.result.result}</span></div>
                        <div>Победитель: <span className="text-white">{duelDetails.duel.result.winner_name || 'Ничья'}</span></div>
                        <div>Счет: <span className="text-white">{duelDetails.duel.result.initiator_total_score} : {duelDetails.duel.result.opponent_total_score}</span></div>
                        <div>Верных: <span className="text-white">{duelDetails.duel.result.initiator_correct} : {duelDetails.duel.result.opponent_correct}</span></div>
                        <div>MMR инициатора: <span className="text-white">{duelDetails.duel.result.rating_changes?.initiator ?? 0}</span></div>
                        <div>MMR оппонента: <span className="text-white">{duelDetails.duel.result.rating_changes?.opponent ?? 0}</span></div>
                        <div className="md:col-span-2">
                          Тех.поражение: <span className="text-white">{duelDetails.duel.result.technical_defeat ? JSON.stringify(duelDetails.duel.result.technical_defeat) : 'нет'}</span>
                        </div>
                      </div>
                    ) : (
                      <div className="text-white/60 text-sm">Итогов пока нет.</div>
                    )}
                  </div>

                  <div className="rounded-xl border border-white/10 bg-black/25 p-3">
                    <div className="text-white text-sm font-semibold mb-2">Раунды ({duelDetails.rounds?.length || 0})</div>
                    <div className="space-y-2 max-h-[40vh] overflow-auto pr-1">
                      {(duelDetails.rounds || []).map((round) => (
                        <div key={round.id} className="rounded-lg border border-white/10 bg-black/20 p-2 text-xs">
                          <div className="text-white font-semibold">
                            R{round.round_number} · {round.question?.category?.icon || '❓'} {round.question?.category?.title || 'Без категории'}
                          </div>
                          <div className="text-white/80 mt-1">{round.question?.text || 'Вопрос недоступен'}</div>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2 text-white/70">
                            <div>
                              <div>Инициатор: score {round.initiator?.score ?? 0}</div>
                              <div>Ответ: {round.initiator?.payload?.is_correct === null ? '—' : (round.initiator?.payload?.is_correct ? 'верно' : 'ошибка')}</div>
                              <div>Причина: {round.initiator?.payload?.reason || '—'}</div>
                              <div>Время: {round.initiator?.payload?.time_elapsed ?? '—'}с</div>
                            </div>
                            <div>
                              <div>Оппонент: score {round.opponent?.score ?? 0}</div>
                              <div>Ответ: {round.opponent?.payload?.is_correct === null ? '—' : (round.opponent?.payload?.is_correct ? 'верно' : 'ошибка')}</div>
                              <div>Причина: {round.opponent?.payload?.reason || '—'}</div>
                              <div>Время: {round.opponent?.payload?.time_elapsed ?? '—'}с</div>
                            </div>
                          </div>
                          <div className="text-white/40 mt-1">Отправка: {formatDateTime(round.question_sent_at)} · Закрыт: {formatDateTime(round.closed_at)}</div>
                        </div>
                      ))}
                      {(duelDetails.rounds || []).length === 0 && (
                        <div className="text-white/50 text-sm">Раунды не найдены.</div>
                      )}
                    </div>
                  </div>

                  <div className="rounded-xl border border-white/10 bg-black/25 p-3">
                    <div className="text-white text-sm font-semibold mb-2">Настройки дуэли (raw)</div>
                    <pre className="text-[11px] text-white/70 whitespace-pre-wrap break-words">{JSON.stringify(duelDetails.duel.settings || {}, null, 2)}</pre>
                  </div>
                </div>
              )}
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

function Kpi({ title, value }) {
  return (
    <div className="rounded-xl border border-white/10 bg-black/25 p-4">
      <div className="text-white/50 text-xs mb-1">{title}</div>
      <div className="text-white text-2xl font-black">{value ?? 0}</div>
    </div>
  )
}

export default AdminPage
