import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import RewardNotifications from '../components/RewardNotifications'
import { addNotificationItems } from '../utils/notificationInbox'

const QUESTION_TIME_LIMIT = 15
const BREAK_STATE_MS = 3000

function UiImageIcon({ src, alt, fallback, className = '' }) {
  const [failed, setFailed] = useState(false)

  if (failed) {
    return <span className={className}>{fallback}</span>
  }

  return (
    <img
      src={src}
      alt={alt}
      className={className}
      onError={() => setFailed(true)}
    />
  )
}

function TrueFalseAnswerButton({
  imageSrc,
  fallbackIcon,
  label,
  onClick,
  disabled,
  isCorrect,
  isWrongSelected,
  isPositive,
}) {
  const [failed, setFailed] = useState(false)

  const imageStateClass = disabled
    ? isCorrect
      ? 'ring-4 ring-game-success/35 bg-game-success/10'
      : isWrongSelected
        ? 'ring-2 ring-red-400/50 bg-red-500/10'
        : 'opacity-35 grayscale'
    : 'hover:brightness-110 active:brightness-95'

  const fallbackStateClass = disabled
    ? isCorrect
      ? 'bg-game-success ring-4 ring-game-success/30'
      : isWrongSelected
        ? 'bg-red-500/70 ring-2 ring-red-400/40'
        : 'bg-white/5 opacity-30 grayscale'
    : (isPositive
      ? 'bg-gradient-to-br from-green-500 to-emerald-600 shadow-glow-success'
      : 'bg-gradient-to-br from-red-500 to-rose-600 shadow-glow-danger')

  return (
    <motion.button
      whileTap={{ scale: 0.95 }}
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      className={`group relative h-32 rounded-[28px] font-bold text-xl overflow-hidden transition-all ${failed ? fallbackStateClass : imageStateClass}`}
    >
      {!failed ? (
        <>
          <img
            src={imageSrc}
            alt={label}
            className="absolute inset-0 w-full h-full object-contain scale-[2.4]"
            onError={() => setFailed(true)}
          />
          <span className="sr-only">{label}</span>
        </>
      ) : (
        <>
          <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 rounded-[28px]" />
          <div className="relative flex flex-col items-center justify-center h-full">
            <span className="text-3xl mb-1">{fallbackIcon}</span>
            <span className="text-white text-base">{label}</span>
          </div>
        </>
      )}
    </motion.button>
  )
}

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
  const factRef = useRef(null)
  const questionDeadlineRef = useRef(null)

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
    factRef.current = fact
  }, [fact])

  const resolveTiming = (payload = {}) => {
    const timeLimitRaw = Number(payload?.time_limit || QUESTION_TIME_LIMIT)
    const timeLimit = Number.isFinite(timeLimitRaw) && timeLimitRaw > 0 ? Math.floor(timeLimitRaw) : QUESTION_TIME_LIMIT

    const expiresAtMs = payload?.question_expires_at ? Date.parse(payload.question_expires_at) : NaN
    const fromExpires = Number.isFinite(expiresAtMs)
      ? Math.max(0, Math.ceil((expiresAtMs - Date.now()) / 1000))
      : null

    const fromPayload = Number(payload?.time_left)
    const safePayload = Number.isFinite(fromPayload) ? Math.max(0, Math.floor(fromPayload)) : null

    const timeLeftResolved = safePayload ?? fromExpires ?? timeLimit
    const deadlineMs = Number.isFinite(expiresAtMs) ? expiresAtMs : Date.now() + (timeLeftResolved * 1000)

    return { timeLeftResolved, deadlineMs }
  }

  const applyQuestionPayload = (nextFact, { triggerTimeoutIfExpired = true } = {}) => {
    if (!nextFact) return

    const { timeLeftResolved, deadlineMs } = resolveTiming(nextFact)

    setFact(nextFact)
    setAnswered(false)
    setResult(null)
    setSelectedChoice(null)
    setTimeLeft(timeLeftResolved)
    setPhase('playing')
    questionDeadlineRef.current = deadlineMs

    if (triggerTimeoutIfExpired && timeLeftResolved <= 0) {
      setTimeout(() => {
        handleTimeout(nextFact)
      }, 0)
    }
  }

  const applyNextQuestion = (nextFact) => {
    if (nextFact) {
      applyQuestionPayload(nextFact, { triggerTimeoutIfExpired: true })
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

  const handleTimeout = async (factOverride = null) => {
    const currentFact = factOverride || factRef.current
    if (answered || !currentFact) return
    setAnswered(true)
    setSelectedChoice(null)
    hapticFeedback('warning')

    try {
      const response = await api.submitTrueFalseAnswer(currentFact.id, null)

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

  useEffect(() => {
    if (loading || answered || phase === 'break' || !factRef.current) return

    const syncTimeLeft = () => {
      const deadlineMs = Number(questionDeadlineRef.current || 0)
      if (!Number.isFinite(deadlineMs) || deadlineMs <= 0) return

      const next = Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000))
      setTimeLeft(next)

      if (next <= 0) {
        handleTimeout()
      }
    }

    syncTimeLeft()
    const timer = setInterval(syncTimeLeft, 700)
    const onVisibilityChange = () => syncTimeLeft()

    document.addEventListener('visibilitychange', onVisibilityChange)
    window.addEventListener('focus', onVisibilityChange)

    return () => {
      clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisibilityChange)
      window.removeEventListener('focus', onVisibilityChange)
    }
  }, [loading, answered, phase, fact?.id])

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
        applyQuestionPayload(response.data, { triggerTimeoutIfExpired: true })
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
          timedOut: Boolean(data.timed_out),
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

      <div className="relative z-10 p-6 flex-1 flex flex-col safe-top safe-bottom overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-5">
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
              <UiImageIcon
                src="/api/images/ui/truefalse_streak.png"
                alt="Серия"
                fallback="🔥"
                className="w-5 h-5 object-contain"
              />
              <span className="font-bold text-white">{streak}</span>
            </div>
            <div className="glass rounded-full px-3 py-2">
              <span className="text-xs text-white/80">Рекорд: <span className="font-bold text-gradient-gold">{record}</span></span>
            </div>
          </div>
        </div>

        <div className="mb-3">
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
        <div className="mb-2">
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
              
              <UiImageIcon
                src="/api/images/ui/truefalse_question.png"
                alt="Вопрос"
                fallback="🤔"
                className="w-16 h-16 mb-6 object-contain"
              />
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
        <div className="grid grid-cols-1 gap-1.5 mt-1 mb-1">
          <TrueFalseAnswerButton
            imageSrc="/api/images/ui/truefalse_btn_true.png"
            fallbackIcon="✅"
            label="Правда"
            onClick={() => handleAnswer(true)}
            disabled={answered || phase === 'break'}
            isCorrect={Boolean((answered || phase === 'break') && result?.correctAnswer === true)}
            isWrongSelected={Boolean((answered || phase === 'break') && selectedChoice === true && result?.correctAnswer !== true)}
            isPositive
          />
          
          <TrueFalseAnswerButton
            imageSrc="/api/images/ui/truefalse_btn_false.png"
            fallbackIcon="❌"
            label="Ложь"
            onClick={() => handleAnswer(false)}
            disabled={answered || phase === 'break'}
            isCorrect={Boolean((answered || phase === 'break') && result?.correctAnswer === false)}
            isWrongSelected={Boolean((answered || phase === 'break') && selectedChoice === false && result?.correctAnswer !== false)}
            isPositive={false}
          />
        </div>
      </div>
    </div>
  )
}

export default TrueFalsePage
