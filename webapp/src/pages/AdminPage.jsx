import { useEffect, useMemo, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useNavigate } from 'react-router-dom'
import api from '../api/client'
import { hapticFeedback, showBackButton } from '../hooks/useTelegram'

const TABS = [
  { id: 'overview', label: 'Обзор' },
  { id: 'users', label: 'Игроки' },
  { id: 'duels', label: 'Дуэли' },
  { id: 'questions', label: 'Вопросы' },
  { id: 'facts', label: 'П/Л факты' },
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
  const [users, setUsers] = useState([])
  const [duels, setDuels] = useState([])
  const [facts, setFacts] = useState([])

  const [userQuery, setUserQuery] = useState('')
  const [duelQuery, setDuelQuery] = useState('')
  const [duelStatus, setDuelStatus] = useState('all')
  const [factQuery, setFactQuery] = useState('')
  const [factTruth, setFactTruth] = useState('all')

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

  useEffect(() => {
    showBackButton(true)
    loadAll()
  }, [])

  useEffect(() => {
    if (!autoRefresh) return undefined
    const interval = setInterval(() => {
      if (activeTab === 'overview') {
        loadStats()
      } else if (activeTab === 'users') {
        loadUsers()
      } else if (activeTab === 'duels') {
        loadDuels()
      } else if (activeTab === 'facts') {
        loadFacts()
      }
    }, 10000)
    return () => clearInterval(interval)
  }, [autoRefresh, activeTab, userQuery, duelQuery, duelStatus, factQuery, factTruth])

  const loadAll = async () => {
    setLoading(true)
    setError(null)
    try {
      await Promise.all([loadStats(), loadUsers(), loadDuels(), loadFacts()])
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
      limit: 60,
    })
    if (res.success) setDuels(res.data.items || [])
  }

  const loadFacts = async () => {
    const res = await api.getAdminFacts({
      q: factQuery,
      truth: factTruth,
      limit: 100,
    })
    if (res.success) setFacts(res.data.items || [])
  }

  const activeDuelsCount = useMemo(
    () => duels.filter((d) => ['waiting', 'matched', 'in_progress'].includes(d.status)).length,
    [duels]
  )

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
            <div className="grid md:grid-cols-3 gap-2">
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
                <div key={d.id} className="rounded-xl border border-white/10 bg-black/25 p-3 flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <div className="text-white font-semibold">{d.code} · {d.status}</div>
                    <div className="text-white/60 text-xs truncate">
                      {d.initiator?.name || '???'} ({d.initiator?.rating ?? 0}) vs {d.opponent?.name || '???'} ({d.opponent?.rating ?? 0})
                    </div>
                  </div>
                  {['waiting', 'matched', 'in_progress'].includes(d.status) && (
                    <button
                      onClick={() => setConfirm({ type: 'duel', duelId: d.id, code: d.code })}
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
