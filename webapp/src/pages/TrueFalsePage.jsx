import { useState, useEffect } from 'react'
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
  const [result, setResult] = useState(null)
  const [answered, setAnswered] = useState(false)
  const [timeLeft, setTimeLeft] = useState(15)

  useEffect(() => {
    showBackButton(true)
    loadQuestion()
  }, [])

  useEffect(() => {
    if (loading || answered || timeLeft <= 0) return

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
  }, [loading, answered, timeLeft])

  const handleTimeout = async () => {
    if (answered || !fact) return
    setAnswered(true)
    hapticFeedback('warning')
    
    try {
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
        
        if (data.is_correct && data.next_fact) {
          setTimeout(() => {
            setFact(data.next_fact)
            setAnswered(false)
            setResult(null)
            setTimeLeft(15)
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
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
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
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
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
            className="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl font-semibold text-white shadow-glow"
          >
            Попробовать снова
          </button>
        </div>
      </div>
    )
  }

  // Game Over
  if (result && !result.isCorrect) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="aurora-blob aurora-blob-3" />
        <div className="noise-overlay" />

        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          className="relative z-10 text-center max-w-md w-full"
        >
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: "spring", stiffness: 200, delay: 0.2 }}
            className="text-8xl mb-6"
          >
            😔
          </motion.div>
          
          <h2 className="text-3xl font-bold mb-6 text-white">Игра окончена!</h2>
          
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="glass rounded-3xl p-6 mb-6"
          >
            <p className="text-white/50 text-sm mb-2">Правильный ответ:</p>
            <p className="text-2xl font-bold mb-4 text-white">
              {result.correctAnswer ? '✅ Правда' : '❌ Ложь'}
            </p>
            {result.explanation && (
              <p className="text-sm text-white/50">{result.explanation}</p>
            )}
          </motion.div>
          
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="glass rounded-3xl p-5 mb-8"
          >
            <div className="grid grid-cols-2 gap-6">
              <div className="text-center">
                <div className="text-4xl font-bold text-gradient-primary">{streak}</div>
                <div className="text-xs text-white/40 mt-1">Серия</div>
              </div>
              <div className="text-center">
                <div className="text-4xl font-bold text-gradient-gold">{record}</div>
                <div className="text-xs text-white/40 mt-1">Рекорд</div>
              </div>
            </div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="flex gap-4"
          >
            <button
              onClick={() => navigate('/')}
              className="flex-1 py-4 px-6 glass rounded-2xl font-semibold text-white/70 hover:text-white transition-colors active:scale-95"
            >
              Домой
            </button>
            <button
              onClick={loadQuestion}
              className="flex-1 py-4 px-6 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl font-semibold text-white shadow-glow active:scale-95 transition-transform"
            >
              Ещё раз
            </button>
          </motion.div>
        </motion.div>
      </div>
    )
  }

  const timerProgress = timeLeft / 15
  const timerColor = timeLeft <= 5 ? '#ef4444' : timeLeft <= 10 ? '#f59e0b' : '#a855f7'

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden">
      <div className="aurora-blob aurora-blob-2" style={{ opacity: 0.4 }} />
      <div className="aurora-blob aurora-blob-3" style={{ opacity: 0.4 }} />
      <div className="noise-overlay" />

      <div className="relative z-10 p-4 flex flex-col min-h-screen">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center justify-between mb-6 pt-2"
        >
          <div className="flex items-center gap-3">
            <motion.div 
              className="text-4xl"
              animate={{ scale: [1, 1.1, 1] }}
              transition={{ duration: 2, repeat: Infinity }}
            >
              🧠
            </motion.div>
            <div>
              <h1 className="font-bold text-white">Правда или ложь</h1>
              <p className="text-xs text-white/40">Проверь свои знания</p>
            </div>
          </div>

          {/* Timer */}
          <div className="relative w-16 h-16">
            <svg className="w-full h-full -rotate-90">
              <circle
                cx="32"
                cy="32"
                r="28"
                fill="none"
                stroke="rgba(255,255,255,0.1)"
                strokeWidth="4"
              />
              <motion.circle
                cx="32"
                cy="32"
                r="28"
                fill="none"
                stroke={timerColor}
                strokeWidth="4"
                strokeLinecap="round"
                strokeDasharray={176}
                strokeDashoffset={176 - (176 * timerProgress)}
                className="timer-glow"
                style={{ filter: `drop-shadow(0 0 8px ${timerColor})` }}
              />
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
              <span className={`font-bold text-lg ${timeLeft <= 5 ? 'text-game-danger' : 'text-white'}`}>
                {timeLeft}
              </span>
            </div>
          </div>
          
          <div className="glass rounded-xl px-4 py-2 text-center">
            <div className="text-2xl font-bold text-gradient-primary">{streak}</div>
            <div className="text-2xs text-white/40">серия</div>
          </div>
        </motion.div>

        {/* Streak Progress */}
        <motion.div 
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2 }}
          className="mb-6"
        >
          <div className="flex justify-between text-xs text-white/40 mb-2">
            <span>Текущая серия</span>
            <span>Рекорд: {record}</span>
          </div>
          <div className="flex gap-1.5">
            {Array.from({ length: 10 }).map((_, i) => (
              <motion.div
                key={i}
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ delay: 0.1 + i * 0.03 }}
                className={`flex-1 h-2 rounded-full transition-all duration-300 ${
                  i < streak 
                    ? 'bg-gradient-to-r from-purple-500 to-pink-500 shadow-glow' 
                    : 'bg-white/10'
                }`}
              />
            ))}
          </div>
        </motion.div>

        {/* Fact Card */}
        <motion.div
          key={fact?.id}
          initial={{ opacity: 0, x: 50 }}
          animate={{ opacity: 1, x: 0 }}
          className="flex-1 flex flex-col justify-center"
        >
          <div className="bento-card card-shine p-8 mb-6">
            <div className="bento-glow bg-gradient-to-br from-purple-500/20 via-pink-500/10 to-transparent blur-2xl" />
            
            <p className="relative text-xl font-medium leading-relaxed text-center text-white">
              {fact?.statement}
            </p>
          </div>

          {/* Result overlay */}
          <AnimatePresence>
            {result && result.isCorrect && (
              <motion.div
                initial={{ opacity: 0, y: 20, scale: 0.9 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, scale: 0.9 }}
                className="glass rounded-3xl p-5 mb-4 border-2 border-game-success shadow-glow-success"
              >
                <div className="flex items-center gap-4">
                  <motion.span 
                    className="text-4xl"
                    initial={{ scale: 0, rotate: -180 }}
                    animate={{ scale: 1, rotate: 0 }}
                    transition={{ type: "spring", stiffness: 300 }}
                  >
                    ✅
                  </motion.span>
                  <div>
                    <p className="font-bold text-game-success text-lg">Правильно!</p>
                    {result.explanation && (
                      <p className="text-sm text-white/50">{result.explanation}</p>
                    )}
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>

          {/* Answer Buttons */}
          <div className="grid grid-cols-2 gap-4">
            <motion.button
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => handleAnswer(true)}
              disabled={answered}
              className={`relative py-8 rounded-3xl font-bold text-xl transition-all overflow-hidden ${
                answered 
                  ? result?.correctAnswer === true
                    ? 'bg-game-success shadow-glow-success'
                    : 'bg-white/5 opacity-40'
                  : 'bg-gradient-to-br from-game-success to-emerald-600 shadow-glow-success hover:shadow-glow-success'
              }`}
            >
              {/* Shine effect */}
              {!answered && (
                <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full hover:translate-x-full transition-transform duration-700" />
              )}
              
              <span className="relative flex flex-col items-center gap-2">
                <span className="text-4xl">✅</span>
                <span>Правда</span>
              </span>
            </motion.button>
            
            <motion.button
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => handleAnswer(false)}
              disabled={answered}
              className={`relative py-8 rounded-3xl font-bold text-xl transition-all overflow-hidden ${
                answered 
                  ? result?.correctAnswer === false
                    ? 'bg-game-danger shadow-glow-danger'
                    : 'bg-white/5 opacity-40'
                  : 'bg-gradient-to-br from-game-danger to-red-600 shadow-glow-danger hover:shadow-glow-danger'
              }`}
            >
              {/* Shine effect */}
              {!answered && (
                <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full hover:translate-x-full transition-transform duration-700" />
              )}
              
              <span className="relative flex flex-col items-center gap-2">
                <span className="text-4xl">❌</span>
                <span>Ложь</span>
              </span>
            </motion.button>
          </div>
        </motion.div>
      </div>
    </div>
  )
}

export default TrueFalsePage
