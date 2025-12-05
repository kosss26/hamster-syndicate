import { useState, useEffect, useCallback, useRef } from 'react'
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
  WAITING_OPPONENT_ANSWER: 'waiting_opponent_answer',
  SHOWING_RESULT: 'showing_result',
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
  const [opponentAnswer, setOpponentAnswer] = useState(null)
  
  const currentQuestionId = useRef(null)
  const timerRef = useRef(null)
  const answeredRoundId = useRef(null)

  useEffect(() => {
    showBackButton(true)
  }, [])

  useEffect(() => {
    if (searchParams.get('mode') === 'random') {
      startSearch()
    }
  }, [searchParams])

  useEffect(() => {
    if (duelIdParam) {
      loadDuel(parseInt(duelIdParam))
    }
  }, [duelIdParam])

  useEffect(() => {
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
    
    if (state !== STATES.PLAYING) return

    timerRef.current = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          if (timerRef.current) {
            clearInterval(timerRef.current)
            timerRef.current = null
          }
          handleTimeout()
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current)
        timerRef.current = null
      }
    }
  }, [state, question?.id])

  useEffect(() => {
    if (!duel || state === STATES.FINISHED || state === STATES.SHOWING_RESULT) return
    
    const interval = state === STATES.WAITING_OPPONENT_ANSWER ? 1000 : 3000
    
    const checkInterval = setInterval(() => {
      checkDuelStatus(duel.duel_id)
    }, interval)

    return () => clearInterval(checkInterval)
  }, [duel, state])

  const checkDuelStatus = async (duelId) => {
    try {
      const response = await api.getDuel(duelId)
      
      if (response.success) {
        const data = response.data
        
        const isInitiator = data.is_initiator
        setScore({
          player: isInitiator ? data.initiator_score : data.opponent_score,
          opponent: isInitiator ? data.opponent_score : data.initiator_score
        })
        
        if (data.status === 'finished') {
          setState(STATES.FINISHED)
        } else if (data.status === 'waiting' && state === STATES.WAITING_OPPONENT) {
          if (data.opponent) {
            setState(STATES.FOUND)
            hapticFeedback('success')
            setTimeout(() => {
              loadDuel(duelId)
            }, 2000)
          }
        } else if (state === STATES.WAITING_OPPONENT && data.question) {
          loadDuel(duelId)
        } else if (state === STATES.WAITING_OPPONENT_ANSWER) {
          const currentRoundId = data.round_status?.round_id
          const lastClosedRound = data.last_closed_round
          
          if (answeredRoundId.current && lastClosedRound && 
              lastClosedRound.round_id === answeredRoundId.current &&
              currentRoundId !== answeredRoundId.current) {
            setOpponentAnswer({
              answered: true,
              correct: lastClosedRound.opponent_correct
            })
            
            if (lastClosedRound.correct_answer_id) {
              setCorrectAnswer(lastClosedRound.correct_answer_id)
            }
            
            setState(STATES.SHOWING_RESULT)
            hapticFeedback(lastClosedRound.opponent_correct ? 'warning' : 'success')
            
            setTimeout(() => {
              currentQuestionId.current = null
              answeredRoundId.current = null
              loadDuel(duelId)
            }, 3000)
          } else if (data.round_status?.opponent_answered) {
            setOpponentAnswer({
              answered: true,
              correct: data.round_status.opponent_correct
            })
            
            if (!correctAnswer && data.round_status.correct_answer_id) {
              setCorrectAnswer(data.round_status.correct_answer_id)
            }
            
            setState(STATES.SHOWING_RESULT)
            hapticFeedback(data.round_status.opponent_correct ? 'warning' : 'success')
            
            setTimeout(() => {
              currentQuestionId.current = null
              answeredRoundId.current = null
              loadDuel(duelId)
            }, 3000)
          }
        }
      }
    } catch (err) {
      console.error('Failed to check duel status:', err)
    }
  }

  const loadDuel = async (duelId) => {
    try {
      const response = await api.getDuel(duelId)
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        setRound(data.current_round)
        setTotalRounds(data.total_rounds)
        
        const isInitiator = data.is_initiator
        setScore({
          player: isInitiator ? data.initiator_score : data.opponent_score,
          opponent: isInitiator ? data.opponent_score : data.initiator_score
        })
        
        if (data.status === 'finished') {
          setState(STATES.FINISHED)
        } else if (data.question) {
          if (currentQuestionId.current !== data.question.id) {
            currentQuestionId.current = data.question.id
            answeredRoundId.current = null
            
            const sortedQuestion = {
              ...data.question,
              answers: [...data.question.answers].sort((a, b) => a.id - b.id)
            }
            setQuestion(sortedQuestion)
            setSelectedAnswer(null)
            setCorrectAnswer(null)
            setOpponentAnswer(null)
            setLastResult(null)
            
            const timeLimit = data.round_status?.time_limit || 30
            if (data.round_status?.question_sent_at) {
              const sentAt = new Date(data.round_status.question_sent_at)
              const elapsed = Math.floor((Date.now() - sentAt.getTime()) / 1000)
              setTimeLeft(Math.max(0, timeLimit - elapsed))
            } else {
              setTimeLeft(timeLimit)
            }
          }
          if (state !== STATES.PLAYING && state !== STATES.SHOWING_RESULT && state !== STATES.WAITING_OPPONENT_ANSWER) {
            setState(STATES.PLAYING)
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
          setState(STATES.FOUND)
          hapticFeedback('success')
          
          setTimeout(() => {
            loadDuel(data.duel_id)
          }, 2000)
        } else {
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
    
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
    
    try {
      const response = await api.submitAnswer(duel.duel_id, round, answerId)
      
      if (response.success) {
        const data = response.data
        setLastResult(data)
        
        if (data.round_id) {
          answeredRoundId.current = data.round_id
        }
        
        if (data.correct_answer_id) {
          setCorrectAnswer(data.correct_answer_id)
        } else {
          setCorrectAnswer(data.is_correct ? answerId : null)
        }
        
        if (data.is_correct) {
          hapticFeedback('success')
          setScore(prev => ({ ...prev, player: prev.player + (data.points_earned || 1) }))
        } else {
          hapticFeedback('error')
        }
        
        if (data.opponent_answered) {
          setOpponentAnswer({
            answered: true,
            correct: data.opponent_correct
          })
          
          setState(STATES.SHOWING_RESULT)
          
          setTimeout(() => {
            currentQuestionId.current = null
            answeredRoundId.current = null
            loadDuel(duel.duel_id)
          }, 3000)
        } else {
          setOpponentAnswer({
            answered: false,
            correct: null
          })
          
          setState(STATES.WAITING_OPPONENT_ANSWER)
        }
      }
    } catch (err) {
      console.error('Failed to submit answer:', err)
      setError(`Ошибка: ${err.message}`)
    }
  }

  const handleTimeout = async () => {
    if (selectedAnswer !== null) return
    hapticFeedback('warning')
    
    setSelectedAnswer(-1)
    setLastResult({ is_correct: false, timeout: true })
    
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
    
    setOpponentAnswer({
      answered: false,
      correct: null
    })
    
    setState(STATES.WAITING_OPPONENT_ANSWER)
  }

  const getAnswerClass = (answerId) => {
    if (correctAnswer === null && selectedAnswer === null) return ''
    if (correctAnswer === answerId) return 'correct'
    if (selectedAnswer === answerId && correctAnswer !== answerId) return 'incorrect'
    return 'opacity-50'
  }

  // Меню выбора режима
  if (state === STATES.MENU) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />

        <div className="relative z-10 p-4 flex flex-col min-h-screen">
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-center pt-8 mb-8"
          >
            <motion.div 
              className="text-6xl mb-4"
              animate={{ scale: [1, 1.1, 1] }}
              transition={{ duration: 2, repeat: Infinity }}
            >
              ⚔️
            </motion.div>
            <h1 className="text-3xl font-bold text-gradient-primary">Дуэль</h1>
            <p className="text-white/40 mt-2">Выбери режим игры</p>
          </motion.div>

          {error && (
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              className="glass rounded-2xl p-4 mb-4 border border-game-danger/30"
            >
              <p className="text-game-danger text-sm">{error}</p>
            </motion.div>
          )}

          <div className="flex-1 flex flex-col gap-4">
            <motion.button
              initial={{ opacity: 0, x: -30 }}
              animate={{ opacity: 1, x: 0 }}
              onClick={startSearch}
              disabled={loading}
              className="bento-card p-6 text-left group disabled:opacity-50"
            >
              <div className="bento-glow bg-gradient-to-br from-game-primary/30 to-purple-500/20 blur-2xl" />
              
              <div className="relative flex items-center gap-5">
                <motion.div 
                  className="w-16 h-16 rounded-2xl bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center text-3xl shadow-glow"
                  whileHover={{ scale: 1.1, rotate: 5 }}
                >
                  🎲
                </motion.div>
                <div>
                  <h3 className="font-bold text-lg text-white">Случайный соперник</h3>
                  <p className="text-white/40 text-sm">Найдём тебе достойного противника</p>
                </div>
              </div>
            </motion.button>

            <motion.button
              initial={{ opacity: 0, x: 30 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.1 }}
              className="bento-card p-6 text-left opacity-50 cursor-not-allowed"
              disabled
            >
              <div className="flex items-center gap-5">
                <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center text-3xl">
                  👥
                </div>
                <div>
                  <h3 className="font-bold text-lg text-white">Пригласить друга</h3>
                  <p className="text-white/40 text-sm">Скоро будет доступно</p>
                </div>
              </div>
            </motion.button>
          </div>
        </div>
      </div>
    )
  }

  // Поиск соперника
  if (state === STATES.SEARCHING) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />

        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          className="relative z-10 text-center"
        >
          <div className="relative w-36 h-36 mx-auto mb-8">
            {/* Pulse rings */}
            <div className="absolute inset-0 rounded-full bg-game-primary/20 pulse-ring" />
            <div className="absolute inset-4 rounded-full bg-game-primary/30 pulse-ring" style={{ animationDelay: '0.3s' }} />
            <div className="absolute inset-8 rounded-full bg-game-primary/40 pulse-ring" style={{ animationDelay: '0.6s' }} />
            
            {/* Center icon */}
            <motion.div 
              className="absolute inset-12 rounded-full bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center shadow-glow-lg"
              animate={{ scale: [1, 1.1, 1] }}
              transition={{ duration: 1.5, repeat: Infinity }}
            >
              <span className="text-4xl">🔍</span>
            </motion.div>
          </div>
          
          <h2 className="text-2xl font-bold mb-2 text-white">Ищем соперника...</h2>
          <p className="text-white/40">Это займёт несколько секунд</p>
        </motion.div>
      </div>
    )
  }

  // Ожидание соперника
  if (state === STATES.WAITING_OPPONENT) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-3" />
        <div className="noise-overlay" />

        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          className="relative z-10 text-center"
        >
          <div className="relative w-36 h-36 mx-auto mb-8">
            <div className="absolute inset-0 rounded-full bg-game-warning/20 pulse-ring" />
            <motion.div 
              className="absolute inset-8 rounded-full bg-gradient-to-br from-game-warning to-orange-500 flex items-center justify-center shadow-glow-warning"
              animate={{ rotate: 360 }}
              transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
            >
              <span className="text-4xl">⏳</span>
            </motion.div>
          </div>
          
          <h2 className="text-2xl font-bold mb-2 text-white">Ожидаем соперника</h2>
          <p className="text-white/40 mb-6">
            Код дуэли: <span className="font-mono font-bold text-game-primary">{duel?.code}</span>
          </p>
          
          <button
            onClick={() => {
              setState(STATES.MENU)
              setDuel(null)
            }}
            className="px-8 py-3 glass rounded-xl text-white/70 hover:text-white transition-colors"
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
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />

        <motion.div
          initial={{ opacity: 0, scale: 0.5 }}
          animate={{ opacity: 1, scale: 1 }}
          className="relative z-10 text-center"
        >
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: "spring", stiffness: 200, delay: 0.2 }}
            className="text-7xl mb-6"
          >
            ⚔️
          </motion.div>
          
          <h2 className="text-3xl font-bold mb-6 text-gradient-primary">Соперник найден!</h2>
          
          <div className="flex items-center justify-center gap-6">
            <motion.div 
              initial={{ x: -50, opacity: 0 }}
              animate={{ x: 0, opacity: 1 }}
              transition={{ delay: 0.4 }}
              className="text-center"
            >
              <div className="w-20 h-20 rounded-full bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center text-3xl mb-3 shadow-glow mx-auto">
                {user?.first_name?.[0] || '?'}
              </div>
              <p className="font-medium text-white">{user?.first_name || 'Ты'}</p>
            </motion.div>
            
            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ delay: 0.6, type: "spring" }}
              className="text-2xl text-white/30 font-bold"
            >
              VS
            </motion.div>
            
            <motion.div 
              initial={{ x: 50, opacity: 0 }}
              animate={{ x: 0, opacity: 1 }}
              transition={{ delay: 0.4 }}
              className="text-center"
            >
              <div className="w-20 h-20 rounded-full bg-gradient-to-br from-game-danger to-orange-500 flex items-center justify-center text-3xl mb-3 shadow-glow-danger mx-auto">
                👤
              </div>
              <p className="font-medium text-white">Соперник</p>
            </motion.div>
          </div>
        </motion.div>
      </div>
    )
  }

  // Игра
  if ((state === STATES.PLAYING || state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.SHOWING_RESULT) && question) {
    const timerProgress = timeLeft / 30
    const timerColor = timeLeft <= 10 ? '#ef4444' : timeLeft <= 20 ? '#f59e0b' : '#6366f1'

    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden">
        <div className="aurora-blob aurora-blob-1" style={{ opacity: 0.3 }} />
        <div className="aurora-blob aurora-blob-2" style={{ opacity: 0.3 }} />
        <div className="noise-overlay" />

        <div className="relative z-10 p-4 flex flex-col min-h-screen">
          {/* Header */}
          <div className="flex items-center justify-between mb-4">
            <div className="glass rounded-xl px-3 py-2">
              <span className="text-white/50 text-xs">Раунд</span>
              <span className="font-bold ml-1 text-white">{round}/{totalRounds}</span>
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
                <span className={`font-bold text-lg ${timeLeft <= 10 ? 'text-game-danger' : 'text-white'}`}>
                  {timeLeft}
                </span>
              </div>
            </div>

            <div className="glass rounded-xl px-3 py-2 flex items-center gap-2">
              <span className="text-game-success font-bold">{score.player}</span>
              <span className="text-white/30">:</span>
              <span className="text-game-danger font-bold">{score.opponent}</span>
            </div>
          </div>

          {/* Progress */}
          <div className="flex gap-1 mb-6">
            {Array.from({ length: totalRounds }).map((_, i) => (
              <motion.div
                key={i}
                className={`flex-1 h-1.5 rounded-full transition-all duration-300 ${
                  i < round - 1 ? 'bg-game-success shadow-glow-success' : 
                  i === round - 1 ? 'bg-game-primary shadow-glow' : 
                  'bg-white/10'
                }`}
                initial={i === round - 1 ? { scale: 0.8 } : {}}
                animate={i === round - 1 ? { scale: 1 } : {}}
              />
            ))}
          </div>

          {/* Category */}
          <motion.div 
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-center mb-4"
          >
            <span className="inline-flex items-center gap-2 px-4 py-2 rounded-full glass text-sm">
              <span>📜</span>
              <span className="text-white/70">{question.category}</span>
            </span>
          </motion.div>

          {/* Question */}
          <motion.div 
            key={question.id}
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="glass rounded-3xl p-6 mb-6"
          >
            <p className="text-lg font-medium leading-relaxed text-center text-white">
              {question.text}
            </p>
          </motion.div>

          {/* Answers */}
          <div className="flex-1 flex flex-col gap-3">
            {question.answers.map((answer, index) => (
              <motion.button
                key={answer.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.1 }}
                onClick={() => handleAnswerSelect(answer.id)}
                disabled={selectedAnswer !== null}
                className={`btn-answer ${getAnswerClass(answer.id)}`}
              >
                <div className="flex items-center gap-4">
                  <span className="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center font-bold text-white/50">
                    {String.fromCharCode(65 + index)}
                  </span>
                  <span className="flex-1 text-left text-white">{answer.text}</span>
                  
                  {selectedAnswer === answer.id && lastResult?.is_correct && (
                    <motion.span 
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="text-2xl"
                    >
                      ✓
                    </motion.span>
                  )}
                  {selectedAnswer === answer.id && lastResult && !lastResult.is_correct && !lastResult.timeout && (
                    <motion.span 
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="text-2xl"
                    >
                      ✗
                    </motion.span>
                  )}
                </div>
              </motion.button>
            ))}
          </div>

          {/* Результат раунда */}
          <AnimatePresence>
            {(state === STATES.SHOWING_RESULT || state === STATES.WAITING_OPPONENT_ANSWER) && lastResult && (
              <motion.div
                initial={{ opacity: 0, y: 100 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: 100 }}
                className="fixed bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-dark-950 via-dark-950/95 to-transparent"
              >
                <div className="glass rounded-3xl p-5">
                  {/* Твой результат */}
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-3">
                      <div className="w-12 h-12 rounded-full bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center font-bold text-white">
                        {user?.first_name?.[0] || '?'}
                      </div>
                      <span className="font-medium text-white">Ты</span>
                    </div>
                    <div className={`flex items-center gap-2 ${lastResult.is_correct ? 'text-game-success' : 'text-game-danger'}`}>
                      {lastResult.timeout ? (
                        <>
                          <span>⏱️</span>
                          <span className="font-bold">Время вышло</span>
                        </>
                      ) : lastResult.is_correct ? (
                        <>
                          <span>✅</span>
                          <span className="font-bold">+{lastResult.points_earned || 10}</span>
                        </>
                      ) : (
                        <>
                          <span>❌</span>
                          <span className="font-bold">Неверно</span>
                        </>
                      )}
                    </div>
                  </div>
                  
                  <div className="border-t border-white/10 my-3" />
                  
                  {/* Результат соперника */}
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-12 h-12 rounded-full bg-gradient-to-br from-game-danger to-orange-500 flex items-center justify-center">
                        👤
                      </div>
                      <span className="font-medium text-white/70">Соперник</span>
                    </div>
                    <div className="flex items-center gap-2">
                      {opponentAnswer && opponentAnswer.answered ? (
                        opponentAnswer.correct ? (
                          <span className="text-game-success font-bold flex items-center gap-2">
                            <span>✅</span> Верно
                          </span>
                        ) : (
                          <span className="text-game-danger font-bold flex items-center gap-2">
                            <span>❌</span> Неверно
                          </span>
                        )
                      ) : (
                        <span className="text-white/40 flex items-center gap-2">
                          <motion.span
                            animate={{ opacity: [0.5, 1, 0.5] }}
                            transition={{ duration: 1.5, repeat: Infinity }}
                          >
                            ⏳
                          </motion.span>
                          <span>Ожидание...</span>
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    )
  }

  // Финиш
  if (state === STATES.FINISHED) {
    const isWinner = score.player > score.opponent
    const isDraw = score.player === score.opponent
    const ratingChange = isWinner ? '+10' : isDraw ? '0' : '-10'

    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="aurora-blob aurora-blob-3" />
        <div className="noise-overlay" />

        <motion.div 
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          className="relative z-10 text-center w-full max-w-sm"
        >
          <motion.div 
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: "spring", stiffness: 200, delay: 0.2 }}
            className="text-8xl mb-6"
          >
            {isWinner ? '🏆' : isDraw ? '🤝' : '😔'}
          </motion.div>
          
          <motion.h2 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className={`text-4xl font-bold mb-4 ${
              isWinner ? 'text-gradient-gold' : isDraw ? 'text-white' : 'text-white/60'
            }`}
          >
            {isWinner ? 'Победа!' : isDraw ? 'Ничья!' : 'Поражение'}
          </motion.h2>
          
          <motion.div 
            initial={{ opacity: 0, scale: 0.8 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: 0.5 }}
            className="text-6xl font-bold my-8"
          >
            <span className="text-game-success">{score.player}</span>
            <span className="text-white/30 mx-4">:</span>
            <span className="text-game-danger">{score.opponent}</span>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
            className="glass rounded-2xl p-5 mb-8"
          >
            <div className="text-center">
              <div className={`text-3xl font-bold ${
                isWinner ? 'text-game-success' : isDraw ? 'text-white/50' : 'text-game-danger'
              }`}>
                {ratingChange}
              </div>
              <div className="text-sm text-white/40">Рейтинг</div>
            </div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.7 }}
            className="flex gap-4"
          >
            <button
              onClick={() => navigate('/')}
              className="flex-1 py-4 px-6 glass rounded-2xl font-semibold text-white/70 hover:text-white transition-colors active:scale-95"
            >
              Домой
            </button>
            <button
              onClick={() => {
                setState(STATES.MENU)
                setDuel(null)
                setRound(1)
                setScore({ player: 0, opponent: 0 })
                currentQuestionId.current = null
              }}
              className="flex-1 py-4 px-6 bg-gradient-to-r from-game-primary to-purple-600 rounded-2xl font-semibold text-white shadow-glow active:scale-95 transition-transform"
            >
              Ещё раз
            </button>
          </motion.div>
        </motion.div>
      </div>
    )
  }

  // Loading state
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

export default DuelPage
