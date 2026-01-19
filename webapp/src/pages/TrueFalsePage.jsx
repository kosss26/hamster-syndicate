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

  // Game Over Screen
  if (result && !result.isCorrect) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />

        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="relative z-10 w-full max-w-sm"
        >
          <div className="text-center mb-8">
            <motion.div
              initial={{ scale: 0, rotate: -180 }}
              animate={{ scale: 1, rotate: 0 }}
              transition={{ type: "spring", stiffness: 200, delay: 0.2 }}
              className="text-8xl mb-4 inline-block"
            >
              💔
            </motion.div>
            <h2 className="text-3xl font-bold text-white mb-2">Неверно!</h2>
            <p className="text-white/60">Серия прервана</p>
          </div>
          
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="bento-card p-6 mb-6"
          >
            <div className="bento-glow bg-gradient-to-br from-red-500/20 to-transparent blur-2xl" />
            
            <p className="text-white/50 text-xs uppercase tracking-wider mb-2">Правильный ответ</p>
            <div className="flex items-center gap-3 mb-4">
              <span className="text-2xl">{result.correctAnswer ? '✅' : '❌'}</span>
              <span className={`text-xl font-bold ${result.correctAnswer ? 'text-game-success' : 'text-game-danger'}`}>
                {result.correctAnswer ? 'Правда' : 'Ложь'}
              </span>
            </div>
            
            {result.explanation && (
              <div className="bg-white/5 rounded-xl p-3 border border-white/5">
                <p className="text-sm text-white/80 leading-relaxed">{result.explanation}</p>
              </div>
            )}
          </motion.div>
          
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="grid grid-cols-2 gap-4 mb-8"
          >
            <div className="glass rounded-2xl p-4 text-center">
              <div className="text-3xl font-bold text-white mb-1">{streak}</div>
              <div className="text-xs text-white/40 uppercase tracking-wider">Серия</div>
            </div>
            <div className="glass rounded-2xl p-4 text-center">
              <div className="text-3xl font-bold text-gradient-gold mb-1">{record}</div>
              <div className="text-xs text-white/40 uppercase tracking-wider">Рекорд</div>
            </div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="flex gap-3"
          >
            <button
              onClick={() => navigate('/')}
              className="flex-1 py-4 glass rounded-2xl font-semibold text-white/60 hover:text-white transition-colors active:scale-95"
            >
              Выход
            </button>
            <button
              onClick={loadQuestion}
              className="flex-[2] py-4 bg-gradient-to-r from-game-primary to-purple-600 rounded-2xl font-semibold text-white shadow-glow active:scale-95 transition-transform"
            >
              Играть снова
            </button>
          </motion.div>
        </motion.div>
      </div>
    )
  }

  const timerProgress = timeLeft / 15
  const timerColor = timeLeft <= 5 ? '#ef4444' : timeLeft <= 10 ? '#f59e0b' : '#a855f7'

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col">
      <div className="aurora-blob aurora-blob-3" style={{ opacity: 0.4 }} />
      <div className="noise-overlay" />

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
          
          <div className="glass rounded-full px-4 py-2 flex items-center gap-2">
            <span className="text-xl">🔥</span>
            <span className="font-bold text-white">{streak}</span>
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
            <div className="bento-card card-shine p-8 min-h-[240px] flex flex-col items-center justify-center text-center">
              <div className="bento-glow bg-gradient-to-br from-purple-500/20 via-pink-500/10 to-transparent blur-2xl" />
              
              <div className="text-4xl mb-6">🤔</div>
              <p className="relative text-xl md:text-2xl font-medium leading-relaxed text-white">
                {fact?.statement}
              </p>
            </div>
          </motion.div>

          {/* Success Overlay inside layout */}
          <AnimatePresence>
            {result && result.isCorrect && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0 }}
                className="mt-4 glass rounded-2xl p-4 border border-game-success/30 flex items-start gap-3"
              >
                <div className="bg-game-success/20 p-2 rounded-full text-game-success">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div>
                  <p className="font-bold text-game-success mb-1">Верно!</p>
                  <p className="text-sm text-white/60">{result.explanation}</p>
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
            disabled={answered}
            className={`group relative h-20 rounded-3xl font-bold text-xl overflow-hidden transition-all ${
              answered 
                ? result?.correctAnswer === true
                  ? 'bg-game-success ring-4 ring-game-success/30'
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
            disabled={answered}
            className={`group relative h-20 rounded-3xl font-bold text-xl overflow-hidden transition-all ${
              answered 
                ? result?.correctAnswer === false
                  ? 'bg-game-danger ring-4 ring-game-danger/30'
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
