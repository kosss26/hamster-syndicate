import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

function TrueFalsePage() {
  const navigate = useNavigate()
  const { user } = useTelegram()
  
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [fact, setFact] = useState(null)
  const [streak, setStreak] = useState(0)
  const [record, setRecord] = useState(0)
  const [result, setResult] = useState(null) // { isCorrect, explanation, correctAnswer }
  const [answered, setAnswered] = useState(false)
  const [timeLeft, setTimeLeft] = useState(15)

  useEffect(() => {
    showBackButton(true)
    loadQuestion()
  }, [])

  // Таймер
  useEffect(() => {
    if (loading || answered || timeLeft <= 0) return

    const timer = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          // Время вышло - считаем как неправильный ответ
          handleTimeout()
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => clearInterval(timer)
  }, [loading, answered, timeLeft])

  const handleTimeout = async () => {
    if (answered || !fact) return
    setAnswered(true)
    hapticFeedback('warning')
    
    try {
      // Отправляем неправильный ответ (противоположный правильному)
      const response = await api.submitTrueFalseAnswer(fact.id, null)
      
      if (response.success) {
        const data = response.data
        setResult({
          isCorrect: false,
          explanation: data.explanation || 'Время вышло!',
          correctAnswer: data.correct_answer
        })
        setStreak(0)
        setRecord(data.record)
        hapticFeedback('error')
      }
    } catch (err) {
      console.error('Timeout error:', err)
      setResult({
        isCorrect: false,
        explanation: 'Время вышло!',
        correctAnswer: null
      })
    }
  }

  const loadQuestion = async () => {
    try {
      setLoading(true)
      setError(null)
      setAnswered(false)
      setResult(null)
      setTimeLeft(15)
      
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
    
    setAnswered(true)
    hapticFeedback('light')
    
    try {
      const response = await api.submitTrueFalseAnswer(fact.id, answer)
      
      if (response.success) {
        const data = response.data
        setResult({
          isCorrect: data.is_correct,
          explanation: data.explanation,
          correctAnswer: data.correct_answer
        })
        setStreak(data.streak)
        setRecord(data.record)
        
        if (data.is_correct) {
          hapticFeedback('success')
        } else {
          hapticFeedback('error')
        }
        
        // Если есть следующий факт - подготавливаем его
        if (data.next_fact) {
          setTimeout(() => {
            setFact(data.next_fact)
            setAnswered(false)
            setResult(null)
            setTimeLeft(15) // Сброс таймера на новый вопрос
          }, 2500)
        }
      }
    } catch (err) {
      console.error('Failed to submit answer:', err)
      setError(`Ошибка: ${err.message}`)
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-game flex items-center justify-center">
        <div className="text-center">
          <div className="w-12 h-12 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-telegram-hint">Загрузка...</p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-game flex items-center justify-center p-4">
        <div className="text-center">
          <div className="text-4xl mb-4">😔</div>
          <p className="text-telegram-hint mb-4">{error}</p>
          <button 
            onClick={loadQuestion}
            className="px-6 py-3 bg-purple-500 rounded-xl font-semibold text-white"
          >
            Попробовать снова
          </button>
        </div>
      </div>
    )
  }

  // Игра окончена (неправильный ответ)
  if (result && !result.isCorrect) {
    return (
      <div className="min-h-screen bg-gradient-game p-4 flex flex-col items-center justify-center">
        <motion.div
          initial={{ opacity: 0, scale: 0.5 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center max-w-md"
        >
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ type: 'spring', delay: 0.2 }}
            className="text-7xl mb-4"
          >
            😔
          </motion.div>
          
          <h2 className="text-2xl font-bold mb-2 text-white">Игра окончена!</h2>
          
          <div className="glass rounded-2xl p-5 mb-6">
            <p className="text-telegram-hint text-sm mb-2">Правильный ответ:</p>
            <p className="text-xl font-bold mb-3">
              {result.correctAnswer ? '✅ Правда' : '❌ Ложь'}
            </p>
            {result.explanation && (
              <p className="text-sm text-telegram-hint">{result.explanation}</p>
            )}
          </div>
          
          <div className="glass rounded-2xl p-4 mb-6">
            <div className="grid grid-cols-2 gap-4">
              <div className="text-center">
                <div className="text-3xl font-bold text-purple-500">{streak}</div>
                <div className="text-xs text-telegram-hint">Серия</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-game-warning">{record}</div>
                <div className="text-xs text-telegram-hint">Рекорд</div>
              </div>
            </div>
          </div>

          <div className="flex gap-3">
            <button
              onClick={() => navigate('/')}
              className="flex-1 py-3 px-6 rounded-xl bg-white/10 font-semibold active:scale-95 transition-transform"
            >
              Домой
            </button>
            <button
              onClick={loadQuestion}
              className="flex-1 py-3 px-6 rounded-xl bg-purple-500 font-semibold active:scale-95 transition-transform"
            >
              Ещё раз
            </button>
          </div>
        </motion.div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-game p-4 flex flex-col">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between mb-6 pt-2"
      >
        <div className="flex items-center gap-3">
          <div className="text-3xl">🧠</div>
          <div>
            <h1 className="font-bold text-white">Правда или ложь</h1>
            <p className="text-xs text-telegram-hint">Проверь свои знания</p>
          </div>
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
              stroke={timeLeft <= 5 ? '#ef4444' : '#a855f7'}
              strokeWidth="4"
              strokeLinecap="round"
              strokeDasharray={150.8}
              strokeDashoffset={150.8 - (150.8 * timeLeft / 15)}
              className="transition-all duration-1000"
            />
          </svg>
          <div className="absolute inset-0 flex items-center justify-center">
            <span className={`font-bold text-white ${timeLeft <= 5 ? 'text-game-danger' : ''}`}>
              {timeLeft}
            </span>
          </div>
        </div>
        
        <div className="text-right">
          <div className="text-2xl font-bold text-purple-500">{streak}</div>
          <div className="text-xs text-telegram-hint">серия</div>
        </div>
      </motion.div>

      {/* Streak Progress */}
      <div className="mb-6">
        <div className="flex justify-between text-xs text-telegram-hint mb-2">
          <span>Текущая серия</span>
          <span>Рекорд: {record}</span>
        </div>
        <div className="flex gap-1">
          {Array.from({ length: 10 }).map((_, i) => (
            <div
              key={i}
              className={`flex-1 h-2 rounded-full transition-colors ${
                i < streak ? 'bg-purple-500' : 'bg-white/10'
              }`}
            />
          ))}
        </div>
      </div>

      {/* Fact Card */}
      <motion.div
        key={fact?.id}
        initial={{ opacity: 0, x: 30 }}
        animate={{ opacity: 1, x: 0 }}
        className="flex-1 flex flex-col justify-center"
      >
        <div className="glass rounded-2xl p-6 mb-6">
          <p className="text-xl font-medium leading-relaxed text-center">
            {fact?.statement}
          </p>
        </div>

        {/* Result overlay */}
        <AnimatePresence>
          {result && result.isCorrect && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0 }}
              className="glass rounded-2xl p-4 mb-4 border-2 border-game-success"
            >
              <div className="flex items-center gap-3">
                <span className="text-3xl">✅</span>
                <div>
                  <p className="font-bold text-game-success">Правильно!</p>
                  {result.explanation && (
                    <p className="text-sm text-telegram-hint">{result.explanation}</p>
                  )}
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Answer Buttons */}
        <div className="grid grid-cols-2 gap-4">
          <motion.button
            whileTap={{ scale: 0.95 }}
            onClick={() => handleAnswer(true)}
            disabled={answered}
            className={`py-6 rounded-2xl font-bold text-lg transition-all ${
              answered 
                ? result?.correctAnswer === true
                  ? 'bg-game-success'
                  : 'bg-white/5 opacity-50'
                : 'bg-game-success hover:bg-game-success/80'
            }`}
          >
            <span className="text-3xl block mb-1">✅</span>
            Правда
          </motion.button>
          
          <motion.button
            whileTap={{ scale: 0.95 }}
            onClick={() => handleAnswer(false)}
            disabled={answered}
            className={`py-6 rounded-2xl font-bold text-lg transition-all ${
              answered 
                ? result?.correctAnswer === false
                  ? 'bg-game-danger'
                  : 'bg-white/5 opacity-50'
                : 'bg-game-danger hover:bg-game-danger/80'
            }`}
          >
            <span className="text-3xl block mb-1">❌</span>
            Ложь
          </motion.button>
        </div>
      </motion.div>
    </div>
  )
}

export default TrueFalsePage

