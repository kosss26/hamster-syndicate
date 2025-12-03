import { useState, useEffect, useCallback } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'

// Состояния дуэли
const STATES = {
  MENU: 'menu',
  SEARCHING: 'searching',
  FOUND: 'found',
  PLAYING: 'playing',
  ROUND_RESULT: 'round_result',
  FINISHED: 'finished'
}

// Моковые данные для демонстрации
const MOCK_QUESTION = {
  id: 1,
  text: 'Какой город был столицей Древнего Египта во времена фараона Тутанхамона?',
  category: 'История',
  answers: [
    { id: 1, text: 'Фивы' },
    { id: 2, text: 'Мемфис' },
    { id: 3, text: 'Александрия' },
    { id: 4, text: 'Гелиополь' }
  ]
}

function DuelPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { user } = useTelegram()
  
  const [state, setState] = useState(STATES.MENU)
  const [timeLeft, setTimeLeft] = useState(30)
  const [round, setRound] = useState(1)
  const [score, setScore] = useState({ player: 0, opponent: 0 })
  const [selectedAnswer, setSelectedAnswer] = useState(null)
  const [correctAnswer, setCorrectAnswer] = useState(null)
  const [question, setQuestion] = useState(MOCK_QUESTION)

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

  const startSearch = () => {
    setState(STATES.SEARCHING)
    hapticFeedback('medium')
    
    // Имитация поиска соперника
    setTimeout(() => {
      setState(STATES.FOUND)
      hapticFeedback('success')
      
      // Начинаем игру через 2 секунды
      setTimeout(() => {
        setState(STATES.PLAYING)
        setTimeLeft(30)
      }, 2000)
    }, 2000)
  }

  const handleAnswerSelect = (answerId) => {
    if (selectedAnswer !== null) return
    
    setSelectedAnswer(answerId)
    hapticFeedback('light')
    
    // Имитация проверки ответа
    setTimeout(() => {
      const correct = 1 // В реальности придёт с сервера
      setCorrectAnswer(correct)
      
      if (answerId === correct) {
        hapticFeedback('success')
        setScore(prev => ({ ...prev, player: prev.player + 1 }))
      } else {
        hapticFeedback('error')
      }
      
      // Переход к следующему раунду
      setTimeout(() => {
        if (round >= 10) {
          setState(STATES.FINISHED)
        } else {
          setRound(prev => prev + 1)
          setSelectedAnswer(null)
          setCorrectAnswer(null)
          setTimeLeft(30)
        }
      }, 2000)
    }, 500)
  }

  const handleTimeout = () => {
    if (selectedAnswer !== null) return
    setCorrectAnswer(1)
    hapticFeedback('warning')
    
    setTimeout(() => {
      if (round >= 10) {
        setState(STATES.FINISHED)
      } else {
        setRound(prev => prev + 1)
        setSelectedAnswer(null)
        setCorrectAnswer(null)
        setTimeLeft(30)
      }
    }, 2000)
  }

  const getAnswerClass = (answerId) => {
    if (correctAnswer === null) {
      return selectedAnswer === answerId ? 'ring-2 ring-game-primary' : ''
    }
    if (answerId === correctAnswer) return 'correct'
    if (answerId === selectedAnswer) return 'incorrect'
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

        <div className="flex-1 flex flex-col gap-4">
          <motion.button
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            onClick={startSearch}
            className="glass rounded-2xl p-6 text-left hover:bg-white/10 transition-colors active:scale-98"
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
            className="glass rounded-2xl p-6 text-left hover:bg-white/10 transition-colors active:scale-98"
          >
            <div className="flex items-center gap-4">
              <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center text-2xl">
                👥
              </div>
              <div>
                <h3 className="font-semibold text-lg">Пригласить друга</h3>
                <p className="text-sm text-telegram-hint">Отправь приглашение по нику</p>
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
  if (state === STATES.PLAYING) {
    return (
      <div className="min-h-screen p-4 flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between mb-4">
          <div className="text-sm">
            <span className="text-telegram-hint">Раунд</span>
            <span className="font-bold ml-1">{round}/10</span>
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
          {Array.from({ length: 10 }).map((_, i) => (
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
          key={round}
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
                disabled={correctAnswer !== null}
                className={`btn-answer ${getAnswerClass(answer.id)}`}
              >
                <div className="flex items-center gap-3">
                  <span className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-sm font-medium">
                    {String.fromCharCode(65 + index)}
                  </span>
                  <span className="flex-1">{answer.text}</span>
                  {correctAnswer === answer.id && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="text-xl"
                    >
                      ✓
                    </motion.span>
                  )}
                  {selectedAnswer === answer.id && correctAnswer !== null && correctAnswer !== answer.id && (
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
                <div className="text-2xl font-bold text-game-success">+15</div>
                <div className="text-xs text-telegram-hint">Рейтинг</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-game-warning">+50</div>
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

  return null
}

export default DuelPage

