import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
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
  
  // Форма добавления вопроса
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

  if (loading) {
    return (
      <div style={{ 
        minHeight: '100vh', 
        background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        color: 'white'
      }}>
        <p>Загрузка...</p>
      </div>
    )
  }

  if (error) {
    return (
      <div style={{ 
        minHeight: '100vh', 
        background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)',
        padding: '20px',
        color: 'white'
      }}>
        <h1 style={{ color: '#ef4444' }}>⛔ Доступ запрещён</h1>
        <p style={{ opacity: 0.7 }}>{error}</p>
        <button
          onClick={() => navigate('/')}
          style={{
            marginTop: '20px',
            padding: '12px 24px',
            background: '#6366f1',
            border: 'none',
            borderRadius: '8px',
            color: 'white',
            cursor: 'pointer'
          }}
        >
          На главную
        </button>
      </div>
    )
  }

  return (
    <div style={{ 
      minHeight: '100vh', 
      background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)',
      padding: '20px',
      paddingBottom: '100px',
      color: 'white'
    }}>
      <h1 style={{ fontSize: '24px', marginBottom: '8px', textAlign: 'center' }}>
        ⚙️ Админ-панель
      </h1>
      <p style={{ textAlign: 'center', opacity: 0.6, marginBottom: '20px', fontSize: '14px' }}>
        Управление ботом
      </p>

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '20px', overflowX: 'auto' }}>
        {['stats', 'users', 'duels', 'questions'].map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            style={{
              padding: '10px 16px',
              borderRadius: '10px',
              border: 'none',
              background: activeTab === tab ? '#6366f1' : 'rgba(255,255,255,0.1)',
              color: 'white',
              fontWeight: '500',
              cursor: 'pointer',
              whiteSpace: 'nowrap',
              fontSize: '13px'
            }}
          >
            {tab === 'stats' && '📊 Статистика'}
            {tab === 'users' && '👥 Игроки'}
            {tab === 'duels' && '⚔️ Дуэли'}
            {tab === 'questions' && '❓ Вопросы'}
          </button>
        ))}
      </div>

      {/* Stats Tab */}
      {activeTab === 'stats' && stats && (
        <div>
          <div style={{ 
            display: 'grid', 
            gridTemplateColumns: '1fr 1fr', 
            gap: '12px',
            marginBottom: '20px'
          }}>
            <StatCard title="Всего игроков" value={stats.total_users} icon="👥" color="#6366f1" />
            <StatCard title="Активных сегодня" value={stats.active_today} icon="🔥" color="#f59e0b" />
            <StatCard title="Всего дуэлей" value={stats.total_duels} icon="⚔️" color="#ef4444" />
            <StatCard title="Активных дуэлей" value={stats.active_duels} icon="🎮" color="#22c55e" />
            <StatCard title="Вопросов" value={stats.total_questions} icon="❓" color="#8b5cf6" />
            <StatCard title="Фактов П/Л" value={stats.total_facts} icon="🧠" color="#ec4899" />
          </div>

          <div style={{ 
            background: 'rgba(255,255,255,0.1)', 
            padding: '16px', 
            borderRadius: '12px',
            marginBottom: '16px'
          }}>
            <h3 style={{ fontSize: '14px', marginBottom: '12px', opacity: 0.7 }}>
              Последние 24 часа
            </h3>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', textAlign: 'center' }}>
              <div>
                <p style={{ fontSize: '24px', fontWeight: 'bold', color: '#22c55e' }}>{stats.duels_today}</p>
                <p style={{ fontSize: '11px', opacity: 0.6 }}>Дуэлей</p>
              </div>
              <div>
                <p style={{ fontSize: '24px', fontWeight: 'bold', color: '#6366f1' }}>{stats.new_users_today}</p>
                <p style={{ fontSize: '11px', opacity: 0.6 }}>Новых</p>
              </div>
              <div>
                <p style={{ fontSize: '24px', fontWeight: 'bold', color: '#f59e0b' }}>{stats.tf_games_today}</p>
                <p style={{ fontSize: '11px', opacity: 0.6 }}>П/Л игр</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Users Tab */}
      {activeTab === 'users' && stats?.recent_users && (
        <div>
          <h3 style={{ fontSize: '14px', marginBottom: '12px', opacity: 0.7 }}>
            Последние игроки
          </h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            {stats.recent_users.map((u, i) => (
              <div 
                key={u.id}
                style={{
                  background: 'rgba(255,255,255,0.1)',
                  padding: '12px',
                  borderRadius: '10px',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '12px'
                }}
              >
                <div style={{
                  width: '36px',
                  height: '36px',
                  borderRadius: '50%',
                  background: 'linear-gradient(135deg, #6366f1, #9333ea)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontWeight: 'bold'
                }}>
                  {u.first_name?.[0] || '?'}
                </div>
                <div style={{ flex: 1 }}>
                  <p style={{ fontWeight: '500' }}>{u.first_name} {u.last_name || ''}</p>
                  <p style={{ fontSize: '12px', opacity: 0.6 }}>@{u.username || 'нет'} • ID: {u.telegram_id}</p>
                </div>
                <div style={{ textAlign: 'right', fontSize: '12px' }}>
                  <p style={{ color: '#6366f1', fontWeight: 'bold' }}>{u.rating}</p>
                  <p style={{ opacity: 0.5 }}>рейтинг</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Duels Tab */}
      {activeTab === 'duels' && stats?.recent_duels && (
        <div>
          {/* Cancel all active duels */}
          {stats.active_duels > 0 && (
            <button
              onClick={() => setShowConfirm({ type: 'all' })}
              style={{
                width: '100%',
                padding: '12px',
                background: 'rgba(239,68,68,0.2)',
                border: '1px solid rgba(239,68,68,0.5)',
                borderRadius: '10px',
                color: '#ef4444',
                cursor: 'pointer',
                marginBottom: '16px',
                fontWeight: '500'
              }}
            >
              🛑 Завершить все активные дуэли ({stats.active_duels})
            </button>
          )}

          <h3 style={{ fontSize: '14px', marginBottom: '12px', opacity: 0.7 }}>
            Последние дуэли
          </h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            {stats.recent_duels.map((d, i) => (
              <div 
                key={d.id}
                style={{
                  background: 'rgba(255,255,255,0.1)',
                  padding: '12px',
                  borderRadius: '10px'
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                  <span style={{ 
                    fontSize: '11px', 
                    padding: '4px 8px', 
                    borderRadius: '6px',
                    background: d.status === 'finished' ? 'rgba(34,197,94,0.3)' : 
                               d.status === 'in_progress' ? 'rgba(99,102,241,0.3)' : 
                               d.status === 'cancelled' ? 'rgba(107,114,128,0.3)' :
                               'rgba(251,191,36,0.3)',
                    color: d.status === 'finished' ? '#22c55e' : 
                           d.status === 'in_progress' ? '#6366f1' : 
                           d.status === 'cancelled' ? '#9ca3af' : '#fbbf24'
                  }}>
                    {d.status}
                  </span>
                  <span style={{ fontSize: '11px', opacity: 0.5 }}>{d.code}</span>
                </div>
                <p style={{ fontSize: '13px' }}>
                  {d.initiator_name} vs {d.opponent_name || '???'}
                </p>
                {d.status === 'finished' && (
                  <p style={{ fontSize: '12px', opacity: 0.6, marginTop: '4px' }}>
                    Счёт: {d.initiator_score} : {d.opponent_score}
                  </p>
                )}
                {['waiting', 'matched', 'in_progress'].includes(d.status) && (
                  <button
                    onClick={() => setShowConfirm({ type: 'single', id: d.id, code: d.code })}
                    style={{
                      marginTop: '8px',
                      padding: '6px 12px',
                      background: 'rgba(239,68,68,0.2)',
                      border: '1px solid rgba(239,68,68,0.5)',
                      borderRadius: '6px',
                      color: '#ef4444',
                      cursor: 'pointer',
                      fontSize: '12px'
                    }}
                  >
                    ✕ Завершить
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Questions Tab */}
      {activeTab === 'questions' && (
        <div>
          <button
            onClick={() => setShowAddQuestion(true)}
            style={{
              width: '100%',
              padding: '14px',
              background: 'linear-gradient(135deg, #22c55e, #16a34a)',
              border: 'none',
              borderRadius: '12px',
              color: 'white',
              cursor: 'pointer',
              marginBottom: '16px',
              fontWeight: '600',
              fontSize: '15px'
            }}
          >
            ➕ Добавить вопрос
          </button>

          <div style={{ 
            background: 'rgba(255,255,255,0.1)', 
            padding: '16px', 
            borderRadius: '12px',
            marginBottom: '16px'
          }}>
            <h3 style={{ fontSize: '14px', marginBottom: '12px' }}>📊 По категориям</h3>
            {stats?.categories?.map((cat, i) => (
              <div key={i} style={{ 
                display: 'flex', 
                justifyContent: 'space-between', 
                padding: '8px 0',
                borderBottom: i < stats.categories.length - 1 ? '1px solid rgba(255,255,255,0.1)' : 'none'
              }}>
                <span style={{ fontSize: '13px' }}>{cat.title}</span>
                <span style={{ fontSize: '13px', color: '#6366f1', fontWeight: 'bold' }}>{cat.count}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Refresh button */}
      <button
        onClick={loadAdminData}
        style={{
          width: '100%',
          padding: '14px',
          background: 'rgba(255,255,255,0.1)',
          border: 'none',
          borderRadius: '12px',
          color: 'white',
          cursor: 'pointer',
          marginTop: '20px'
        }}
      >
        🔄 Обновить данные
      </button>

      {/* Confirm Modal */}
      {showConfirm && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0,0,0,0.8)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '20px',
          zIndex: 1000
        }}>
          <div style={{
            background: '#1a1a2e',
            padding: '24px',
            borderRadius: '16px',
            maxWidth: '320px',
            width: '100%'
          }}>
            <h3 style={{ marginBottom: '12px', textAlign: 'center' }}>⚠️ Подтверждение</h3>
            <p style={{ opacity: 0.8, textAlign: 'center', marginBottom: '20px', fontSize: '14px' }}>
              {showConfirm.type === 'all' 
                ? `Завершить ВСЕ активные дуэли (${stats.active_duels} шт)?`
                : `Завершить дуэль ${showConfirm.code}?`
              }
            </p>
            <div style={{ display: 'flex', gap: '12px' }}>
              <button
                onClick={() => setShowConfirm(null)}
                disabled={actionLoading}
                style={{
                  flex: 1,
                  padding: '12px',
                  background: 'rgba(255,255,255,0.1)',
                  border: 'none',
                  borderRadius: '10px',
                  color: 'white',
                  cursor: 'pointer'
                }}
              >
                Отмена
              </button>
              <button
                onClick={() => showConfirm.type === 'all' ? cancelAllDuels() : cancelDuel(showConfirm.id)}
                disabled={actionLoading}
                style={{
                  flex: 1,
                  padding: '12px',
                  background: '#ef4444',
                  border: 'none',
                  borderRadius: '10px',
                  color: 'white',
                  cursor: 'pointer',
                  opacity: actionLoading ? 0.5 : 1
                }}
              >
                {actionLoading ? '...' : 'Завершить'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Add Question Modal */}
      {showAddQuestion && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0,0,0,0.9)',
          overflowY: 'auto',
          padding: '20px',
          zIndex: 1000
        }}>
          <div style={{
            background: '#1a1a2e',
            padding: '20px',
            borderRadius: '16px',
            maxWidth: '400px',
            margin: '0 auto'
          }}>
            <h3 style={{ marginBottom: '16px', textAlign: 'center' }}>➕ Новый вопрос</h3>
            
            <label style={{ display: 'block', marginBottom: '12px' }}>
              <span style={{ fontSize: '12px', opacity: 0.7, display: 'block', marginBottom: '4px' }}>Категория</span>
              <select
                value={newQuestion.category_id}
                onChange={(e) => setNewQuestion({...newQuestion, category_id: e.target.value})}
                style={{
                  width: '100%',
                  padding: '12px',
                  borderRadius: '8px',
                  border: 'none',
                  background: 'rgba(255,255,255,0.1)',
                  color: 'white',
                  fontSize: '14px'
                }}
              >
                <option value="">Выберите категорию</option>
                {stats?.categories?.map((cat, i) => (
                  <option key={i} value={cat.id || i + 1}>{cat.title}</option>
                ))}
              </select>
            </label>

            <label style={{ display: 'block', marginBottom: '12px' }}>
              <span style={{ fontSize: '12px', opacity: 0.7, display: 'block', marginBottom: '4px' }}>Текст вопроса</span>
              <textarea
                value={newQuestion.question_text}
                onChange={(e) => setNewQuestion({...newQuestion, question_text: e.target.value})}
                placeholder="Введите вопрос..."
                rows={3}
                style={{
                  width: '100%',
                  padding: '12px',
                  borderRadius: '8px',
                  border: 'none',
                  background: 'rgba(255,255,255,0.1)',
                  color: 'white',
                  fontSize: '14px',
                  resize: 'vertical'
                }}
              />
            </label>

            <div style={{ marginBottom: '12px' }}>
              <span style={{ fontSize: '12px', opacity: 0.7, display: 'block', marginBottom: '8px' }}>
                Варианты ответов (первый = правильный)
              </span>
              {newQuestion.answers.map((answer, i) => (
                <div key={i} style={{ display: 'flex', gap: '8px', marginBottom: '8px', alignItems: 'center' }}>
                  <input
                    type="radio"
                    name="correct"
                    checked={newQuestion.correct_answer === i}
                    onChange={() => setNewQuestion({...newQuestion, correct_answer: i})}
                    style={{ accentColor: '#22c55e' }}
                  />
                  <input
                    value={answer}
                    onChange={(e) => {
                      const answers = [...newQuestion.answers]
                      answers[i] = e.target.value
                      setNewQuestion({...newQuestion, answers})
                    }}
                    placeholder={`Ответ ${i + 1}${i === 0 ? ' (правильный)' : ''}`}
                    style={{
                      flex: 1,
                      padding: '10px',
                      borderRadius: '8px',
                      border: newQuestion.correct_answer === i ? '2px solid #22c55e' : 'none',
                      background: 'rgba(255,255,255,0.1)',
                      color: 'white',
                      fontSize: '14px'
                    }}
                  />
                </div>
              ))}
            </div>

            <div style={{ display: 'flex', gap: '12px', marginTop: '20px' }}>
              <button
                onClick={() => setShowAddQuestion(false)}
                disabled={actionLoading}
                style={{
                  flex: 1,
                  padding: '14px',
                  background: 'rgba(255,255,255,0.1)',
                  border: 'none',
                  borderRadius: '10px',
                  color: 'white',
                  cursor: 'pointer'
                }}
              >
                Отмена
              </button>
              <button
                onClick={addQuestion}
                disabled={actionLoading}
                style={{
                  flex: 1,
                  padding: '14px',
                  background: '#22c55e',
                  border: 'none',
                  borderRadius: '10px',
                  color: 'white',
                  cursor: 'pointer',
                  fontWeight: '600',
                  opacity: actionLoading ? 0.5 : 1
                }}
              >
                {actionLoading ? 'Добавляю...' : 'Добавить'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function StatCard({ title, value, icon, color }) {
  return (
    <div style={{ 
      background: 'rgba(255,255,255,0.1)', 
      padding: '16px', 
      borderRadius: '12px',
      textAlign: 'center'
    }}>
      <span style={{ fontSize: '24px' }}>{icon}</span>
      <p style={{ fontSize: '24px', fontWeight: 'bold', color, marginTop: '8px' }}>{value ?? '—'}</p>
      <p style={{ fontSize: '11px', opacity: 0.6 }}>{title}</p>
    </div>
  )
}

export default AdminPage
