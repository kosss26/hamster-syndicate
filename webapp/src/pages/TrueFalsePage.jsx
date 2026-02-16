import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import RewardNotifications from '../components/RewardNotifications'
import { addNotificationItems } from '../utils/notificationInbox'

const QUESTION_TIME_LIMIT = 15
const BREAK_STATE_MS = 3000

function TrueFalsePage() {
  const navigate = useNavigate()
  const { user } = useTelegram()

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [fact, setFact] = useState(null)
  const [streak, setStreak] = useState(0)
  const [record, setRecord] = useState(0)
  const [result, setResult] = useState(null)
  const [answered, setAnswered] = useState(false)
  const [timeLeft, setTimeLeft] = useState(QUESTION_TIME_LIMIT)
  const [phase, setPhase] = useState('playing')
  const [selectedChoice, setSelectedChoice] = useState(null)
  const [rewardNotifications, setRewardNotifications] = useState([])
  const breakTimerRef = useRef(null)

  useEffect(() => {
    showBackButton(true)
    loadQuestion()

    return () => {
      if (breakTimerRef.current) {
        clearTimeout(breakTimerRef.current)
        breakTimerRef.current = null
      }
    }
  }, [])

  useEffect(() => {
    if (loading || answered || phase === 'break' || timeLeft <= 0) return

    const timer = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          handleTimeout()
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => clearInterval(timer)
  }, [loading, answered, timeLeft, phase])

  const applyNextQuestion = (nextFact) => {
    if (nextFact) {
      setFact(nextFact)
      setAnswered(false)
      setResult(null)
      setSelectedChoice(null)
      setTimeLeft(QUESTION_TIME_LIMIT)
      setPhase('playing')
      return
    }

    loadQuestion({ soft: true })
  }

  const scheduleNextQuestion = (nextFact = null) => {
    if (breakTimerRef.current) {
      clearTimeout(breakTimerRef.current)
      breakTimerRef.current = null
    }

    setPhase('break')
    breakTimerRef.current = setTimeout(() => {
      applyNextQuestion(nextFact)
    }, BREAK_STATE_MS)
  }

  const handleTimeout = async () => {
    if (answered || !fact) return
    setAnswered(true)
    setSelectedChoice(null)
    hapticFeedback('warning')

    try {
      const response = await api.submitTrueFalseAnswer(fact.id, null)

      if (response.success) {
        const data = response.data
        queueRewardNotifications(data)
        const previousStreak = streak
        setResult({
          isCorrect: false,
          explanation: data.explanation || 'Время вышло!',
          correctAnswer: data.correct_answer,
          timedOut: true,
          brokenStreak: previousStreak
        })
        setStreak(data.streak ?? 0)
        setRecord(data.record)
        hapticFeedback('error')
        scheduleNextQuestion(data.next_fact || null)
      }
    } catch (err) {
      console.error('Timeout error:', err)
      setResult({
        isCorrect: false,
        explanation: 'Время вышло!',
        correctAnswer: null,
        timedOut: true,
        brokenStreak: streak
      })
      setStreak(0)
      scheduleNextQuestion()
    }
  }

  const loadQuestion = async ({ soft = false } = {}) => {
    try {
      if (!soft) {
        setLoading(true)
      }
      setError(null)
      setAnswered(false)
      setResult(null)
      setSelectedChoice(null)
      setTimeLeft(QUESTION_TIME_LIMIT)
      setPhase('playing')

      const response = await api.getTrueFalseQuestion()

      if (response.success) {
        setFact(response.data)
      } else {
        setError(response.error || 'Не удалось загрузить вопрос')
      }
    } catch (err) {
      console.error('Failed to load question:', err)
      setError(`Ошибка: ${err.message}`)
    } finally {
      setLoading(false)
    }
  }

  const handleAnswer = async (answer) => {
    if (answered || !fact) return
    const previousStreak = streak
    setAnswered(true)
    setSelectedChoice(answer)
    hapticFeedback('light')

    try {
      const response = await api.submitTrueFalseAnswer(fact.id, answer)

      if (response.success) {
        const data = response.data
        queueRewardNotifications(data)
        setResult({
          isCorrect: data.is_correct,
          explanation: data.explanation,
          correctAnswer: data.correct_answer,
          timedOut: false,
          brokenStreak: data.is_correct ? 0 : previousStreak
        })
        setStreak(data.streak)
        setRecord(data.record)

        if (data.is_correct) {
          hapticFeedback('success')
        } else {
          hapticFeedback('error')
        }

        scheduleNextQuestion(data.next_fact || null)
      }
    } catch (err) {
      console.error('Failed to submit answer:', err)
      setError(`Ошибка: ${err.message}`)
    }
  }

  const dismissRewardNotification = (id) => {
    setRewardNotifications((prev) => prev.filter((item) => item.id !== id))
  }

  const queueRewardNotifications = (payload) => {
    if (!payload) return

    const queue = []
    const achievements = Array.isArray(payload.achievement_unlocks) ? payload.achievement_unlocks : []
    const drops = Array.isArray(payload.collection_drops) ? payload.collection_drops : []

    achievements.forEach((unlock, index) => {
      const achievement = unlock?.achievement
      if (!achievement?.title) return
      queue.push({
        id: `tf_ach_${Date.now()}_${index}_${achievement.id || achievement.key || 'x'}`,
        type: 'achievement',
        icon: achievement.icon || '🏆',
        title: achievement.title,
        subtitle: achievement.description || '',
        rarity: achievement.rarity || 'common',
      })
    })

    drops.forEach((drop, index) => {
      const item = drop?.item
      if (!item?.name) return
      const isDuplicate = Boolean(drop?.is_duplicate)
      const coins = isDuplicate
        ? Number(drop?.duplicate_compensation?.coins || 0)
        : Number(drop?.new_card_bonus?.coins || 0)
      queue.push({
        id: `tf_card_${Date.now()}_${index}_${item.id || item.key || 'x'}`,
        type: 'card',
        icon: isDuplicate ? '♻️' : '🃏',
        title: isDuplicate ? `Дубликат: ${item.name}` : `Карточка: ${item.name}`,
        subtitle: isDuplicate
          ? `Обмен на +${coins} монет`
          : `Редкость: ${item.rarity_label || drop.rarity_label || 'Обычная'}${coins > 0 ? ` · +${coins} монет` : ''}`,
        rarity: item.rarity || 'common',
      })
    })

    if (queue.length === 0) return

    setRewardNotifications((prev) => [...prev, ...queue].slice(-5))
    addNotificationItems(queue)
    queue.forEach((entry) => {
      setTimeout(() => dismissRewardNotification(entry.id), 4500)
    })
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
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
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center w-full max-w-sm">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            className="text-6xl mb-4"
          >
            😔
          </motion.div>
          <p className="text-white/50 mb-6">{error}</p>
          <button 
            onClick={loadQuestion}
            className="w-full py-4 bg-gradient-to-r from-game-primary to-purple-600 rounded-2xl font-semibold text-white shadow-glow active:scale-95 transition-transform"
          >
            Попробовать снова
          </button>
        </div>
      </div>
    )
  }

  const timerProgress = timeLeft / QUESTION_TIME_LIMIT
  const timerColor = timeLeft <= 5 ? '#ef4444' : timeLeft <= 10 ? '#f59e0b' : '#a855f7'
  const nextMilestone = Math.max(5, Math.ceil(Math.max(streak, 1) / 5) * 5)
  const streakMilestoneProgress = Math.min(1, streak / nextMilestone)

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col">
      <div className="aurora-blob aurora-blob-3" style={{ opacity: 0.4 }} />
      <div className="noise-overlay" />
      <RewardNotifications items={rewardNotifications} onDismiss={dismissRewardNotification} />

      {/* Confetti Effects */}
      <AnimatePresence>
        {result?.isCorrect && (
          <>
            {[...Array(12)].map((_, i) => (
              <motion.div
                key={i}
                className="confetti"
                initial={{ opacity: 1, y: -20, x: Math.random() * 300 - 150 }}
                animate={{ opacity: 0, y: 600, rotate: 720 }}
                transition={{ duration: 2, delay: i * 0.1 }}
                style={{
                  left: `${10 + Math.random() * 80}%`,
                  backgroundColor: ['#6366f1', '#ec4899', '#22c55e', '#fbbf24'][Math.floor(Math.random() * 4)]
                }}
              />
            ))}
          </>
        )}
      </AnimatePresence>

      <div className="relative z-10 p-6 flex-1 flex flex-col safe-top safe-bottom">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <button 
            onClick={() => navigate('/')}
            className="w-10 h-10 rounded-full glass flex items-center justify-center text-white/60 hover:text-white active:scale-95 transition-all"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </button>

          {/* Timer */}
          <div className="relative w-14 h-14">
            <svg className="w-full h-full -rotate-90">
              <circle cx="28" cy="28" r="24" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="3" />
              <motion.circle
                cx="28" cy="28" r="24"
                fill="none"
                stroke={timerColor}
                strokeWidth="3"
                strokeLinecap="round"
                strokeDasharray={151}
                strokeDashoffset={151 - (151 * timerProgress)}
                initial={{ strokeDashoffset: 151 }}
                animate={{ strokeDashoffset: 151 - (151 * timerProgress) }}
                className="timer-glow"
              />
            </svg>
            <div className={`absolute inset-0 flex items-center justify-center font-bold ${timeLeft <= 5 ? 'text-game-danger animate-pulse' : 'text-white'}`}>
              {timeLeft}
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <div className="glass rounded-full px-4 py-2 flex items-center gap-2">
              <span className="text-xl">🔥</span>
              <span className="font-bold text-white">{streak}</span>
            </div>
            <div className="glass rounded-full px-3 py-2">
              <span className="text-xs text-white/80">Рекорд: <span className="font-bold text-gradient-gold">{record}</span></span>
            </div>
          </div>
        </div>

        <div className="mb-6">
          <div className="flex items-center justify-between text-xs text-white/50 mb-2">
            <span>Прогресс серии</span>
            <span>{streak}/{nextMilestone}</span>
          </div>
          <div className="h-2 w-full rounded-full bg-white/10 overflow-hidden">
            <motion.div
              className="h-full rounded-full bg-gradient-to-r from-emerald-400 via-lime-300 to-yellow-300"
              initial={{ width: 0 }}
              animate={{ width: `${streakMilestoneProgress * 100}%` }}
              transition={{ duration: 0.35 }}
            />
          </div>
        </div>

        {/* Fact Card - Centered */}
        <div className="flex-1 flex flex-col justify-center mb-8">
          <motion.div
            key={fact?.id}
            initial={{ opacity: 0, scale: 0.9, rotateX: -15 }}
            animate={{ opacity: 1, scale: 1, rotateX: 0 }}
            className="perspective-1000"
          >
            <div className={`bento-card card-shine p-8 min-h-[240px] flex flex-col items-center justify-center text-center transition-all ${
              phase === 'break'
                ? result?.isCorrect
                  ? 'border border-game-success/35'
                  : 'border border-game-danger/35'
                : ''
            }`}>
              <div className={`bento-glow blur-2xl ${
                phase === 'break'
                  ? result?.isCorrect
                    ? 'bg-gradient-to-br from-emerald-500/20 via-green-400/10 to-transparent'
                    : 'bg-gradient-to-br from-red-500/20 via-rose-400/10 to-transparent'
                  : 'bg-gradient-to-br from-purple-500/20 via-pink-500/10 to-transparent'
              }`} />
              
              <div className="text-4xl mb-6">🤔</div>
              <p className="relative text-xl md:text-2xl font-medium leading-relaxed text-white">
                {fact?.statement}
              </p>
            </div>
          </motion.div>

          {/* Success Overlay inside layout */}
          <AnimatePresence>
            {result && phase === 'break' && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0 }}
                className={`mt-4 glass rounded-2xl p-4 flex items-start gap-3 ${
                  result.isCorrect ? 'border border-game-success/30' : 'border border-game-danger/35'
                }`}
              >
                <div className={`p-2 rounded-full ${result.isCorrect ? 'bg-game-success/20 text-game-success' : 'bg-game-danger/20 text-game-danger'}`}>
                  {result.isCorrect ? (
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  ) : (
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  )}
                </div>
                <div>
                  <p className={`font-bold mb-1 ${result.isCorrect ? 'text-game-success' : 'text-game-danger'}`}>
                    {result.isCorrect ? 'Верно!' : (result.timedOut ? 'Время вышло' : 'Неверно')}
                  </p>
                  <p className="text-sm text-white/65 mb-1">
                    Правильный ответ: <span className={result.correctAnswer ? 'text-game-success' : 'text-game-danger'}>
                      {result.correctAnswer ? 'Правда' : 'Ложь'}
                    </span>
                  </p>
                  {result.explanation && <p className="text-sm text-white/60">{result.explanation}</p>}
                  {!result.isCorrect && (
                    <p className="text-xs text-white/45 mt-1">Серия прервана: {result.brokenStreak || 0} → 0</p>
                  )}
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {/* Controls */}
        <div className="grid grid-cols-2 gap-3 mt-4 mb-2">
          <motion.button
            whileTap={{ scale: 0.95 }}
            onClick={() => handleAnswer(true)}
            disabled={answered || phase === 'break'}
            className={`group relative h-20 rounded-3xl font-bold text-xl overflow-hidden transition-all ${
              answered || phase === 'break'
                ? result?.correctAnswer === true
                  ? 'bg-game-success ring-4 ring-game-success/30'
                  : selectedChoice === true
                    ? 'bg-red-500/70 ring-2 ring-red-400/40'
                    : 'bg-white/5 opacity-30 grayscale'
                : 'bg-gradient-to-br from-green-500 to-emerald-600 shadow-glow-success'
            }`}
          >
            <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 rounded-3xl" />
            <div className="relative flex flex-col items-center justify-center h-full">
              <span className="text-2xl mb-1">✅</span>
              <span className="text-white text-sm">Правда</span>
            </div>
          </motion.button>
          
          <motion.button
            whileTap={{ scale: 0.95 }}
            onClick={() => handleAnswer(false)}
            disabled={answered || phase === 'break'}
            className={`group relative h-20 rounded-3xl font-bold text-xl overflow-hidden transition-all ${
              answered || phase === 'break'
                ? result?.correctAnswer === false
                  ? 'bg-game-danger ring-4 ring-game-danger/30'
                  : selectedChoice === false
                    ? 'bg-red-500/70 ring-2 ring-red-400/40'
                    : 'bg-white/5 opacity-30 grayscale'
                : 'bg-gradient-to-br from-red-500 to-rose-600 shadow-glow-danger'
            }`}
          >
            <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 rounded-3xl" />
            <div className="relative flex flex-col items-center justify-center h-full">
              <span className="text-2xl mb-1">❌</span>
              <span className="text-white text-sm">Ложь</span>
            </div>
          </motion.button>
        </div>
      </div>
    </div>
  )
}

export default TrueFalsePage
