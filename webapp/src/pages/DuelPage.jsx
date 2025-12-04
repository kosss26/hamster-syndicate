import { useState, useEffect, useCallback } from 'react'
import { useNavigate, useSearchParams, useParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

// Состояния дуэли
const STATES = {
  MENU: 'menu',
  SEARCHING: 'searching',
  FOUND: 'found',
  PLAYING: 'playing',
  WAITING_OPPONENT: 'waiting_opponent',
  FINISHED: 'finished'
}

function DuelPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { id: duelIdParam } = useParams()
  const { user } = useTelegram()
  
  const [state, setState] = useState(STATES.MENU)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  
  const [duel, setDuel] = useState(null)
  const [question, setQuestion] = useState(null)
  const [timeLeft, setTimeLeft] = useState(30)
  const [round, setRound] = useState(1)
  const [totalRounds, setTotalRounds] = useState(10)
  const [score, setScore] = useState({ player: 0, opponent: 0 })
  const [selectedAnswer, setSelectedAnswer] = useState(null)
  const [correctAnswer, setCorrectAnswer] = useState(null)
  const [lastResult, setLastResult] = useState(null)

  // Показываем кнопку "Назад"
  useEffect(() => {
    showBackButton(true)
  }, [])

  // Автостарт случайной дуэли
  useEffect(() => {
    if (searchParams.get('mode') === 'random') {
      startSearch()
    }
  }, [searchParams])

  // Загрузка существующей дуэли
  useEffect(() => {
    if (duelIdParam) {
      loadDuel(parseInt(duelIdParam))
    }
  }, [duelIdParam])

  // Таймер
  useEffect(() => {
    if (state !== STATES.PLAYING || timeLeft <= 0) return

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
  }, [state, timeLeft])

  // Периодическая проверка состояния дуэли
  useEffect(() => {
    if (!duel || state === STATES.FINISHED) return
    
    const interval = setInterval(() => {
      loadDuel(duel.duel_id)
    }, 3000)

    return () => clearInterval(interval)
  }, [duel, state])

  const loadDuel = async (duelId) => {
    try {
      const response = await api.getDuel(duelId)
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        setRound(data.current_round)
        setTotalRounds(data.total_rounds)
        setScore({
          player: data.initiator_score,
          opponent: data.opponent_score
        })
        
        if (data.status === 'finished') {
          setState(STATES.FINISHED)
        } else if (data.question) {
          setQuestion(data.question)
          if (state !== STATES.PLAYING) {
            setState(STATES.PLAYING)
            setTimeLeft(30)
          }
        } else if (data.status === 'waiting') {
          setState(STATES.WAITING_OPPONENT)
        }
      }
    } catch (err) {
      console.error('Failed to load duel:', err)
    }
  }

  const startSearch = async () => {
    setState(STATES.SEARCHING)
    setLoading(true)
    setError(null)
    hapticFeedback('medium')
    
    try {
      const response = await api.createDuel('random')
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        
        if (data.opponent_id) {
          // Соперник уже найден
          setState(STATES.FOUND)
          hapticFeedback('success')
          
          setTimeout(() => {
            loadDuel(data.duel_id)
          }, 2000)
        } else {
          // Ожидаем соперника
          setState(STATES.WAITING_OPPONENT)
        }
      } else {
        setError(response.error || 'Не удалось создать дуэль')
        setState(STATES.MENU)
      }
    } catch (err) {
      console.error('Failed to create duel:', err)
      setError(`Ошибка: ${err.message}`)
      setState(STATES.MENU)
    } finally {
      setLoading(false)
    }
  }

  const handleAnswerSelect = async (answerId) => {
    if (selectedAnswer !== null || !duel || !question) return
    
    setSelectedAnswer(answerId)
    hapticFeedback('light')
    
    try {
      const response = await api.submitAnswer(duel.duel_id, round, answerId)
      
      if (response.success) {
        const data = response.data
        setLastResult(data)
        
        // Находим правильный ответ (если сервер вернул)
        const correctId = data.is_correct ? answerId : null
        setCorrectAnswer(correctId)
        
        if (data.is_correct) {
          hapticFeedback('success')
          setScore(prev => ({ ...prev, player: prev.player + data.points_earned }))
        } else {
          hapticFeedback('error')
        }
        
        // Загружаем следующий раунд через 2 сек
        setTimeout(() => {
          loadDuel(duel.duel_id)
          setSelectedAnswer(null)
          setCorrectAnswer(null)
          setTimeLeft(30)
        }, 2000)
      }
    } catch (err) {
      console.error('Failed to submit answer:', err)
      setError(`Ошибка: ${err.message}`)
    }
  }

  const handleTimeout = async () => {
    if (selectedAnswer !== null) return
    hapticFeedback('warning')
    
    // При таймауте считаем ответ неправильным
    setTimeout(() => {
      loadDuel(duel.duel_id)
      setSelectedAnswer(null)
      setCorrectAnswer(null)
      setTimeLeft(30)
    }, 1000)
  }

  const getAnswerClass = (answerId) => {
    if (correctAnswer === null && selectedAnswer === null) return ''
    if (selectedAnswer === answerId) {
      if (correctAnswer === answerId) return 'correct'
      return 'incorrect'
    }
    if (correctAnswer === answerId) return 'correct'
    return 'opacity-50'
  }

  // Меню выбора режима
  if (state === STATES.MENU) {
    return (
      <div className="min-h-screen p-4 flex flex-col">
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center pt-8 mb-8"
        >
          <div className="text-5xl mb-3">⚔️</div>
          <h1 className="text-2xl font-bold">Дуэль</h1>
          <p className="text-telegram-hint mt-2">Выбери режим игры</p>
        </motion.div>

        {error && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="glass rounded-xl p-4 mb-4 border border-game-danger/50"
          >
            <p className="text-game-danger text-sm">{error}</p>
          </motion.div>
        )}

        <div className="flex-1 flex flex-col gap-4">
          <motion.button
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            onClick={startSearch}
            disabled={loading}
            className="glass rounded-2xl p-6 text-left hover:bg-white/10 transition-colors active:scale-95 disabled:opacity-50"
          >
            <div className="flex items-center gap-4">
              <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center text-2xl">
                🎲
              </div>
              <div>
                <h3 className="font-semibold text-lg">Случайный соперник</h3>
                <p className="text-sm text-telegram-hint">Найдём тебе достойного противника</p>
              </div>
            </div>
          </motion.button>

          <motion.button
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.1 }}
            className="glass rounded-2xl p-6 text-left hover:bg-white/10 transition-colors active:scale-95 opacity-50"
            disabled
          >
            <div className="flex items-center gap-4">
              <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center text-2xl">
                👥
              </div>
              <div>
                <h3 className="font-semibold text-lg">Пригласить друга</h3>
                <p className="text-sm text-telegram-hint">Скоро будет доступно</p>
              </div>
            </div>
          </motion.button>
        </div>
      </div>
    )
  }

  // Поиск соперника
  if (state === STATES.SEARCHING) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center"
        >
          <div className="relative w-32 h-32 mx-auto mb-6">
            <div className="absolute inset-0 rounded-full bg-game-primary/30 pulse-ring"></div>
            <div className="absolute inset-4 rounded-full bg-game-primary/50 pulse-ring" style={{ animationDelay: '0.5s' }}></div>
            <div className="absolute inset-8 rounded-full bg-game-primary flex items-center justify-center">
              <span className="text-4xl">🔍</span>
            </div>
          </div>
          <h2 className="text-xl font-bold mb-2">Ищем соперника...</h2>
          <p className="text-telegram-hint">Это займёт несколько секунд</p>
        </motion.div>
      </div>
    )
  }

  // Ожидание соперника
  if (state === STATES.WAITING_OPPONENT) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center"
        >
          <div className="relative w-32 h-32 mx-auto mb-6">
            <div className="absolute inset-0 rounded-full bg-yellow-500/30 pulse-ring"></div>
            <div className="absolute inset-8 rounded-full bg-yellow-500 flex items-center justify-center">
              <span className="text-4xl">⏳</span>
            </div>
          </div>
          <h2 className="text-xl font-bold mb-2">Ожидаем соперника</h2>
          <p className="text-telegram-hint mb-4">Код дуэли: <span className="font-mono font-bold">{duel?.code}</span></p>
          <button
            onClick={() => {
              setState(STATES.MENU)
              setDuel(null)
            }}
            className="px-6 py-2 bg-white/10 rounded-xl"
          >
            Отмена
          </button>
        </motion.div>
      </div>
    )
  }

  // Соперник найден
  if (state === STATES.FOUND) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, scale: 0.5 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center"
        >
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ type: 'spring', delay: 0.2 }}
            className="text-6xl mb-4"
          >
            ⚔️
          </motion.div>
          <h2 className="text-2xl font-bold mb-2">Соперник найден!</h2>
          <div className="flex items-center justify-center gap-4 mt-6">
            <div className="text-center">
              <div className="w-16 h-16 rounded-full bg-game-primary flex items-center justify-center text-2xl mb-2">
                {user?.first_name?.[0] || '?'}
              </div>
              <p className="text-sm font-medium">{user?.first_name || 'Ты'}</p>
            </div>
            <div className="text-2xl text-telegram-hint">VS</div>
            <div className="text-center">
              <div className="w-16 h-16 rounded-full bg-game-danger flex items-center justify-center text-2xl mb-2">
                👤
              </div>
              <p className="text-sm font-medium">Соперник</p>
            </div>
          </div>
        </motion.div>
      </div>
    )
  }

  // Игра
  if (state === STATES.PLAYING && question) {
    return (
      <div className="min-h-screen p-4 flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between mb-4">
          <div className="text-sm">
            <span className="text-telegram-hint">Раунд</span>
            <span className="font-bold ml-1">{round}/{totalRounds}</span>
          </div>
          
          {/* Timer */}
          <div className="relative w-14 h-14">
            <svg className="w-full h-full -rotate-90">
              <circle
                cx="28"
                cy="28"
                r="24"
                fill="none"
                stroke="rgba(255,255,255,0.1)"
                strokeWidth="4"
              />
              <circle
                cx="28"
                cy="28"
                r="24"
                fill="none"
                stroke={timeLeft <= 10 ? '#ef4444' : '#6366f1'}
                strokeWidth="4"
                strokeLinecap="round"
                strokeDasharray={150.8}
                strokeDashoffset={150.8 - (150.8 * timeLeft / 30)}
                className="transition-all duration-1000"
              />
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
              <span className={`font-bold ${timeLeft <= 10 ? 'text-game-danger' : ''}`}>
                {timeLeft}
              </span>
            </div>
          </div>

          <div className="text-sm">
            <span className="text-game-success font-bold">{score.player}</span>
            <span className="text-telegram-hint mx-1">:</span>
            <span className="text-game-danger font-bold">{score.opponent}</span>
          </div>
        </div>

        {/* Progress */}
        <div className="flex gap-1 mb-6">
          {Array.from({ length: totalRounds }).map((_, i) => (
            <div
              key={i}
              className={`flex-1 h-1 rounded-full transition-colors ${
                i < round - 1 ? 'bg-game-success' : 
                i === round - 1 ? 'bg-game-primary' : 
                'bg-white/10'
              }`}
            />
          ))}
        </div>

        {/* Category */}
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center mb-4"
        >
          <span className="inline-block px-3 py-1 rounded-full bg-game-primary/20 text-game-primary text-sm">
            📜 {question.category}
          </span>
        </motion.div>

        {/* Question */}
        <motion.div
          key={`q-${round}`}
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          className="glass rounded-2xl p-5 mb-6"
        >
          <p className="text-lg font-medium leading-relaxed">
            {question.text}
          </p>
        </motion.div>

        {/* Answers */}
        <div className="flex-1 flex flex-col gap-3">
          <AnimatePresence>
            {question.answers.map((answer, index) => (
              <motion.button
                key={answer.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.1 }}
                onClick={() => handleAnswerSelect(answer.id)}
                disabled={selectedAnswer !== null}
                className={`btn-answer ${getAnswerClass(answer.id)}`}
              >
                <div className="flex items-center gap-3">
                  <span className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-sm font-medium">
                    {String.fromCharCode(65 + index)}
                  </span>
                  <span className="flex-1 text-left">{answer.text}</span>
                  {selectedAnswer === answer.id && lastResult?.is_correct && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="text-xl"
                    >
                      ✓
                    </motion.span>
                  )}
                  {selectedAnswer === answer.id && lastResult && !lastResult.is_correct && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="text-xl"
                    >
                      ✗
                    </motion.span>
                  )}
                </div>
              </motion.button>
            ))}
          </AnimatePresence>
        </div>
      </div>
    )
  }

  // Финиш
  if (state === STATES.FINISHED) {
    const isWinner = score.player > score.opponent
    const isDraw = score.player === score.opponent

    return (
      <div className="min-h-screen p-4 flex flex-col items-center justify-center">
        <motion.div
          initial={{ opacity: 0, scale: 0.5 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center"
        >
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: 'spring', delay: 0.2 }}
            className="text-7xl mb-4"
          >
            {isWinner ? '🏆' : isDraw ? '🤝' : '😔'}
          </motion.div>
          
          <h2 className="text-3xl font-bold mb-2">
            {isWinner ? 'Победа!' : isDraw ? 'Ничья!' : 'Поражение'}
          </h2>
          
          <div className="text-5xl font-bold my-6">
            <span className="text-game-success">{score.player}</span>
            <span className="text-telegram-hint mx-3">:</span>
            <span className="text-game-danger">{score.opponent}</span>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="glass rounded-2xl p-4 mb-6"
          >
            <div className="flex justify-around">
              <div className="text-center">
                <div className="text-2xl font-bold text-game-success">
                  {isWinner ? '+15' : isDraw ? '+5' : '-10'}
                </div>
                <div className="text-xs text-telegram-hint">Рейтинг</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-game-warning">
                  {isWinner ? '+50' : isDraw ? '+25' : '+10'}
                </div>
                <div className="text-xs text-telegram-hint">Монеты</div>
              </div>
            </div>
          </motion.div>

          <div className="flex gap-3">
            <button
              onClick={() => navigate('/')}
              className="flex-1 py-3 px-6 rounded-xl bg-white/10 font-semibold active:scale-95 transition-transform"
            >
              Домой
            </button>
            <button
              onClick={() => {
                setState(STATES.MENU)
                setDuel(null)
                setRound(1)
                setScore({ player: 0, opponent: 0 })
              }}
              className="flex-1 py-3 px-6 rounded-xl bg-game-primary font-semibold active:scale-95 transition-transform"
            >
              Ещё раз
            </button>
          </div>
        </motion.div>
      </div>
    )
  }

  // Loading state
  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <div className="w-12 h-12 border-4 border-game-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p className="text-telegram-hint">Загрузка...</p>
      </div>
    </div>
  )
}

export default DuelPage
