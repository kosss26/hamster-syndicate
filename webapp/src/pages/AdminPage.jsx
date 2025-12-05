import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

function AdminPage() {
  const navigate = useNavigate()
  const { user } = useTelegram()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [activeTab, setActiveTab] = useState('stats')
  const [showConfirm, setShowConfirm] = useState(null)
  const [actionLoading, setActionLoading] = useState(false)
  
  const [showAddQuestion, setShowAddQuestion] = useState(false)
  const [newQuestion, setNewQuestion] = useState({
    category_id: '',
    question_text: '',
    answers: ['', '', '', ''],
    correct_answer: 0
  })

  useEffect(() => {
    showBackButton(true)
    loadAdminData()
  }, [])

  const loadAdminData = async () => {
    try {
      setLoading(true)
      const response = await api.getAdminStats()
      if (response.success) {
        setStats(response.data)
      } else {
        setError(response.error)
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const cancelDuel = async (duelId) => {
    setActionLoading(true)
    try {
      const response = await api.adminCancelDuel(duelId)
      if (response.success) {
        hapticFeedback('success')
        loadAdminData()
      } else {
        alert('Ошибка: ' + response.error)
      }
    } catch (err) {
      alert('Ошибка: ' + err.message)
    } finally {
      setActionLoading(false)
      setShowConfirm(null)
    }
  }

  const cancelAllDuels = async () => {
    setActionLoading(true)
    try {
      const response = await api.adminCancelAllDuels()
      if (response.success) {
        hapticFeedback('success')
        alert(`Отменено дуэлей: ${response.data.cancelled}`)
        loadAdminData()
      } else {
        alert('Ошибка: ' + response.error)
      }
    } catch (err) {
      alert('Ошибка: ' + err.message)
    } finally {
      setActionLoading(false)
      setShowConfirm(null)
    }
  }

  const addQuestion = async () => {
    if (!newQuestion.question_text || !newQuestion.category_id) {
      alert('Заполните все поля')
      return
    }
    
    const filledAnswers = newQuestion.answers.filter(a => a.trim())
    if (filledAnswers.length < 2) {
      alert('Минимум 2 варианта ответа')
      return
    }

    setActionLoading(true)
    try {
      const response = await api.adminAddQuestion({
        category_id: parseInt(newQuestion.category_id),
        question_text: newQuestion.question_text,
        answers: newQuestion.answers.filter(a => a.trim()),
        correct_answer: newQuestion.correct_answer
      })
      
      if (response.success) {
        hapticFeedback('success')
        alert('Вопрос добавлен!')
        setNewQuestion({
          category_id: newQuestion.category_id,
          question_text: '',
          answers: ['', '', '', ''],
          correct_answer: 0
        })
        setShowAddQuestion(false)
        loadAdminData()
      } else {
        alert('Ошибка: ' + response.error)
      }
    } catch (err) {
      alert('Ошибка: ' + err.message)
    } finally {
      setActionLoading(false)
    }
  }

  const tabs = [
    { id: 'stats', label: '📊 Статистика' },
    { id: 'users', label: '👥 Игроки' },
    { id: 'duels', label: '⚔️ Дуэли' },
    { id: 'questions', label: '❓ Вопросы' }
  ]

  if (loading) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/40">Загрузка...</p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 pt-8">
          <h1 className="text-2xl font-bold text-game-danger mb-4">⛔ Доступ запрещён</h1>
          <p className="text-white/50 mb-6">{error}</p>
          <button
            onClick={() => navigate('/')}
            className="px-6 py-3 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl text-white font-medium shadow-glow"
          >
            На главную
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden">
      <div className="aurora-blob aurora-blob-1" style={{ opacity: 0.3 }} />
      <div className="aurora-blob aurora-blob-2" style={{ opacity: 0.3 }} />
      <div className="noise-overlay" />

      <div className="relative z-10 p-4 pb-24">
        {/* Header */}
        <motion.div 
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center mb-6"
        >
          <h1 className="text-2xl font-bold text-white mb-1">⚙️ Админ-панель</h1>
          <p className="text-white/40 text-sm">Управление ботом</p>
        </motion.div>

        {/* Tabs */}
        <motion.div 
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.1 }}
          className="flex gap-2 mb-6 overflow-x-auto pb-2 scrollbar-hide"
        >
          {tabs.map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`px-4 py-2.5 rounded-xl font-medium text-sm whitespace-nowrap transition-all ${
                activeTab === tab.id
                  ? 'bg-gradient-to-r from-game-primary to-purple-600 text-white shadow-glow'
                  : 'glass text-white/50 hover:text-white/70'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </motion.div>

        {/* Stats Tab */}
        {activeTab === 'stats' && stats && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <div className="grid grid-cols-2 gap-3 mb-4">
              <StatCard title="Всего игроков" value={stats.total_users} icon="👥" gradient="from-game-primary/20" />
              <StatCard title="Активных сегодня" value={stats.active_today} icon="🔥" gradient="from-game-warning/20" />
              <StatCard title="Всего дуэлей" value={stats.total_duels} icon="⚔️" gradient="from-game-danger/20" />
              <StatCard title="Активных дуэлей" value={stats.active_duels} icon="🎮" gradient="from-game-success/20" />
              <StatCard title="Вопросов" value={stats.total_questions} icon="❓" gradient="from-purple-500/20" />
              <StatCard title="Фактов П/Л" value={stats.total_facts} icon="🧠" gradient="from-pink-500/20" />
            </div>

            <div className="glass rounded-2xl p-4">
              <h3 className="text-sm text-white/50 mb-4">Последние 24 часа</h3>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div>
                  <p className="text-3xl font-bold text-game-success">{stats.duels_today}</p>
                  <p className="text-xs text-white/40">Дуэлей</p>
                </div>
                <div>
                  <p className="text-3xl font-bold text-game-primary">{stats.new_users_today}</p>
                  <p className="text-xs text-white/40">Новых</p>
                </div>
                <div>
                  <p className="text-3xl font-bold text-game-warning">{stats.tf_games_today}</p>
                  <p className="text-xs text-white/40">П/Л игр</p>
                </div>
              </div>
            </div>
          </motion.div>
        )}

        {/* Users Tab */}
        {activeTab === 'users' && stats?.recent_users && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <h3 className="text-sm text-white/50 mb-3">Последние игроки</h3>
            <div className="space-y-2">
              {stats.recent_users.map((u, i) => (
                <div key={u.id} className="glass rounded-xl p-3 flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center font-bold text-white">
                    {u.first_name?.[0] || '?'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-white truncate">{u.first_name} {u.last_name || ''}</p>
                    <p className="text-xs text-white/40 truncate">@{u.username || 'нет'} • ID: {u.telegram_id}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-bold text-game-primary">{u.rating}</p>
                    <p className="text-2xs text-white/40">рейтинг</p>
                  </div>
                </div>
              ))}
            </div>
          </motion.div>
        )}

        {/* Duels Tab */}
        {activeTab === 'duels' && stats?.recent_duels && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            {stats.active_duels > 0 && (
              <button
                onClick={() => setShowConfirm({ type: 'all' })}
                className="w-full p-4 mb-4 glass rounded-xl border border-game-danger/30 text-game-danger font-medium hover:bg-game-danger/10 transition-colors"
              >
                🛑 Завершить все активные дуэли ({stats.active_duels})
              </button>
            )}

            <h3 className="text-sm text-white/50 mb-3">Последние дуэли</h3>
            <div className="space-y-2">
              {stats.recent_duels.map((d) => (
                <div key={d.id} className="glass rounded-xl p-3">
                  <div className="flex justify-between items-center mb-2">
                    <span className={`text-xs px-2 py-1 rounded-lg ${
                      d.status === 'finished' ? 'bg-game-success/20 text-game-success' :
                      d.status === 'in_progress' ? 'bg-game-primary/20 text-game-primary' :
                      d.status === 'cancelled' ? 'bg-white/10 text-white/40' :
                      'bg-game-warning/20 text-game-warning'
                    }`}>
                      {d.status}
                    </span>
                    <span className="text-xs text-white/40 font-mono">{d.code}</span>
                  </div>
                  <p className="text-sm text-white">
                    {d.initiator_name} vs {d.opponent_name || '???'}
                  </p>
                  {d.status === 'finished' && (
                    <p className="text-xs text-white/50 mt-1">
                      Счёт: {d.initiator_score} : {d.opponent_score}
                    </p>
                  )}
                  {['waiting', 'matched', 'in_progress'].includes(d.status) && (
                    <button
                      onClick={() => setShowConfirm({ type: 'single', id: d.id, code: d.code })}
                      className="mt-2 px-3 py-1.5 text-xs bg-game-danger/20 text-game-danger rounded-lg border border-game-danger/30"
                    >
                      ✕ Завершить
                    </button>
                  )}
                </div>
              ))}
            </div>
          </motion.div>
        )}

        {/* Questions Tab */}
        {activeTab === 'questions' && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <button
              onClick={() => setShowAddQuestion(true)}
              className="w-full p-4 mb-4 bg-gradient-to-r from-game-success to-emerald-600 rounded-xl text-white font-semibold shadow-glow-success"
            >
              ➕ Добавить вопрос
            </button>

            <div className="glass rounded-2xl p-4">
              <h3 className="text-sm text-white/50 mb-3">📊 По категориям</h3>
              {stats?.categories?.map((cat, i) => (
                <div 
                  key={i} 
                  className={`flex justify-between py-3 ${
                    i < stats.categories.length - 1 ? 'border-b border-white/5' : ''
                  }`}
                >
                  <span className="text-sm text-white/70">{cat.title}</span>
                  <span className="text-sm font-bold text-game-primary">{cat.count}</span>
                </div>
              ))}
            </div>
          </motion.div>
        )}

        {/* Refresh Button */}
        <button
          onClick={loadAdminData}
          className="w-full mt-6 p-4 glass rounded-xl text-white/70 hover:text-white transition-colors"
        >
          🔄 Обновить данные
        </button>
      </div>

      {/* Confirm Modal */}
      <AnimatePresence>
        {showConfirm && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/80 flex items-center justify-center p-4 z-50"
          >
            <motion.div
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.9, opacity: 0 }}
              className="bg-dark-950 rounded-2xl p-6 max-w-xs w-full"
            >
              <h3 className="text-lg font-bold text-white text-center mb-3">⚠️ Подтверждение</h3>
              <p className="text-white/60 text-center text-sm mb-6">
                {showConfirm.type === 'all' 
                  ? `Завершить ВСЕ активные дуэли (${stats.active_duels} шт)?`
                  : `Завершить дуэль ${showConfirm.code}?`
                }
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => setShowConfirm(null)}
                  disabled={actionLoading}
                  className="flex-1 py-3 glass rounded-xl text-white/70"
                >
                  Отмена
                </button>
                <button
                  onClick={() => showConfirm.type === 'all' ? cancelAllDuels() : cancelDuel(showConfirm.id)}
                  disabled={actionLoading}
                  className="flex-1 py-3 bg-game-danger rounded-xl text-white font-medium disabled:opacity-50"
                >
                  {actionLoading ? '...' : 'Завершить'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Add Question Modal */}
      <AnimatePresence>
        {showAddQuestion && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/90 overflow-y-auto p-4 z-50"
          >
            <motion.div
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.9, opacity: 0 }}
              className="bg-dark-950 rounded-2xl p-5 max-w-md mx-auto my-4"
            >
              <h3 className="text-lg font-bold text-white text-center mb-4">➕ Новый вопрос</h3>
              
              <div className="mb-4">
                <label className="text-xs text-white/50 block mb-2">Категория</label>
                <select
                  value={newQuestion.category_id}
                  onChange={(e) => setNewQuestion({...newQuestion, category_id: e.target.value})}
                  className="w-full p-3 rounded-xl bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:border-game-primary"
                >
                  <option value="">Выберите категорию</option>
                  {stats?.categories?.map((cat, i) => (
                    <option key={i} value={cat.id || i + 1}>{cat.title}</option>
                  ))}
                </select>
              </div>

              <div className="mb-4">
                <label className="text-xs text-white/50 block mb-2">Текст вопроса</label>
                <textarea
                  value={newQuestion.question_text}
                  onChange={(e) => setNewQuestion({...newQuestion, question_text: e.target.value})}
                  placeholder="Введите вопрос..."
                  rows={3}
                  className="w-full p-3 rounded-xl bg-white/5 border border-white/10 text-white text-sm resize-none focus:outline-none focus:border-game-primary"
                />
              </div>

              <div className="mb-4">
                <label className="text-xs text-white/50 block mb-2">
                  Варианты ответов (отметьте правильный)
                </label>
                {newQuestion.answers.map((answer, i) => (
                  <div key={i} className="flex gap-2 mb-2 items-center">
                    <input
                      type="radio"
                      name="correct"
                      checked={newQuestion.correct_answer === i}
                      onChange={() => setNewQuestion({...newQuestion, correct_answer: i})}
                      className="w-4 h-4 accent-game-success"
                    />
                    <input
                      value={answer}
                      onChange={(e) => {
                        const answers = [...newQuestion.answers]
                        answers[i] = e.target.value
                        setNewQuestion({...newQuestion, answers})
                      }}
                      placeholder={`Ответ ${i + 1}`}
                      className={`flex-1 p-2.5 rounded-xl bg-white/5 text-white text-sm focus:outline-none ${
                        newQuestion.correct_answer === i 
                          ? 'border-2 border-game-success' 
                          : 'border border-white/10'
                      }`}
                    />
                  </div>
                ))}
              </div>

              <div className="flex gap-3">
                <button
                  onClick={() => setShowAddQuestion(false)}
                  disabled={actionLoading}
                  className="flex-1 py-3 glass rounded-xl text-white/70"
                >
                  Отмена
                </button>
                <button
                  onClick={addQuestion}
                  disabled={actionLoading}
                  className="flex-1 py-3 bg-game-success rounded-xl text-white font-medium disabled:opacity-50"
                >
                  {actionLoading ? 'Добавляю...' : 'Добавить'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

function StatCard({ title, value, icon, gradient }) {
  return (
    <div className="relative overflow-hidden rounded-xl p-4 text-center">
      <div className={`absolute inset-0 bg-gradient-to-br ${gradient} to-transparent`} />
      <div className="absolute inset-0 glass" />
      <div className="relative">
        <span className="text-2xl">{icon}</span>
        <p className="text-2xl font-bold text-white mt-2">{value ?? '—'}</p>
        <p className="text-xs text-white/40">{title}</p>
      </div>
    </div>
  )
}

export default AdminPage
