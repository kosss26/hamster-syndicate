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
  WAITING_OPPONENT_ANSWER: 'waiting_opponent_answer', // Ждём ответа соперника в раунде
  SHOWING_RESULT: 'showing_result', // Показ результата после ответа обоих
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
  const [opponentAnswer, setOpponentAnswer] = useState(null) // Ответ соперника
  
  // Храним ID текущего вопроса чтобы не перезаписывать его при polling
  const currentQuestionId = useRef(null)
  // Ref для таймера чтобы не перезапускать его при каждом изменении timeLeft
  const timerRef = useRef(null)

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

  // Таймер - использует ref чтобы не перезапускаться каждую секунду
  useEffect(() => {
    // Очищаем предыдущий таймер
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
    
    if (state !== STATES.PLAYING) return

    timerRef.current = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          // Очищаем таймер при достижении 0
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
  }, [state, question?.id]) // Перезапускаем только при смене состояния или вопроса

  // Периодическая проверка состояния дуэли
  useEffect(() => {
    if (!duel || state === STATES.FINISHED || state === STATES.SHOWING_RESULT) return
    
    // Определяем интервал в зависимости от состояния
    const interval = state === STATES.WAITING_OPPONENT_ANSWER ? 1000 : 3000
    
    const checkInterval = setInterval(() => {
      checkDuelStatus(duel.duel_id)
    }, interval)

    return () => clearInterval(checkInterval)
  }, [duel, state])

  // Проверка статуса без перезаписи вопроса
  const checkDuelStatus = async (duelId) => {
    try {
      const response = await api.getDuel(duelId)
      
      if (response.success) {
        const data = response.data
        
        // Обновляем счёт с учетом роли игрока
        const isInitiator = data.is_initiator
        setScore({
          player: isInitiator ? data.initiator_score : data.opponent_score,
          opponent: isInitiator ? data.opponent_score : data.initiator_score
        })
        
        if (data.status === 'finished') {
          setState(STATES.FINISHED)
        } else if (data.status === 'waiting' && state === STATES.WAITING_OPPONENT) {
          // Ждём соперника - при появлении соперника загружаем дуэль полностью
          if (data.opponent) {
            setState(STATES.FOUND)
            hapticFeedback('success')
            setTimeout(() => {
              loadDuel(duelId)
            }, 2000)
          }
        } else if (state === STATES.WAITING_OPPONENT && data.question) {
          // Появился вопрос - загружаем
          loadDuel(duelId)
        } else if (state === STATES.WAITING_OPPONENT_ANSWER && data.round_status) {
          // Ждём ответа соперника в текущем раунде
          if (data.round_status.opponent_answered) {
            // Соперник ответил! Обновляем информацию
            setOpponentAnswer({
              answered: true,
              correct: data.round_status.opponent_correct
            })
            
            // Устанавливаем правильный ответ если еще не установлен
            if (!correctAnswer && data.round_status.correct_answer_id) {
              setCorrectAnswer(data.round_status.correct_answer_id)
            }
            
            // Переходим в состояние показа результата на 3 секунды
            setState(STATES.SHOWING_RESULT)
            hapticFeedback(data.round_status.opponent_correct ? 'warning' : 'success')
            
            // Через 3 секунды загружаем следующий вопрос
            setTimeout(() => {
              currentQuestionId.current = null
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
        
        // Обновляем счёт с учетом роли игрока
        const isInitiator = data.is_initiator
        setScore({
          player: isInitiator ? data.initiator_score : data.opponent_score,
          opponent: isInitiator ? data.opponent_score : data.initiator_score
        })
        
        if (data.status === 'finished') {
          setState(STATES.FINISHED)
        } else if (data.question) {
          // Устанавливаем вопрос только если это новый вопрос
          if (currentQuestionId.current !== data.question.id) {
            currentQuestionId.current = data.question.id
            // Сортируем ответы по ID чтобы порядок был всегда одинаковый
            const sortedQuestion = {
              ...data.question,
              answers: [...data.question.answers].sort((a, b) => a.id - b.id)
            }
            setQuestion(sortedQuestion)
            setSelectedAnswer(null)
            setCorrectAnswer(null)
            setOpponentAnswer(null)
            setLastResult(null)
            
            // Устанавливаем время с учетом уже прошедшего времени
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
    
    // Останавливаем таймер
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
    
    try {
      const response = await api.submitAnswer(duel.duel_id, round, answerId)
      
      if (response.success) {
        const data = response.data
        setLastResult(data)
        
        // Устанавливаем правильный ответ
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
        
        // Проверяем, ответил ли соперник
        if (data.opponent_answered) {
          // Соперник уже ответил - показываем его результат
          setOpponentAnswer({
            answered: true,
            correct: data.opponent_correct
          })
          
          // Переходим в состояние показа результата
          setState(STATES.SHOWING_RESULT)
          
          // Загружаем следующий раунд через 3 сек
          setTimeout(() => {
            currentQuestionId.current = null
            loadDuel(duel.duel_id)
          }, 3000)
        } else {
          // Соперник еще не ответил - показываем "ожидание" и ждём
          setOpponentAnswer({
            answered: false,
            correct: null
          })
          
          // Переходим в состояние ожидания ответа соперника
          setState(STATES.WAITING_OPPONENT_ANSWER)
          // Polling в useEffect будет проверять статус раунда
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
    
    setSelectedAnswer(-1) // Маркер таймаута
    setLastResult({ is_correct: false, timeout: true })
    
    // Останавливаем таймер
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
    
    // При таймауте показываем результат и ждём соперника (или сразу следующий вопрос если раунд уже закрыт)
    setOpponentAnswer({
      answered: false,
      correct: null
    })
    
    // Переходим в состояние ожидания ответа соперника
    setState(STATES.WAITING_OPPONENT_ANSWER)
    // Polling в useEffect будет проверять статус раунда
  }

  const getAnswerClass = (answerId) => {
    if (correctAnswer === null && selectedAnswer === null) return ''
    
    // Правильный ответ всегда зелёный
    if (correctAnswer === answerId) return 'correct'
    
    // Выбранный неправильный ответ - красный
    if (selectedAnswer === answerId && correctAnswer !== answerId) return 'incorrect'
    
    // Остальные - затемнённые
    return 'opacity-50'
  }

  // Меню выбора режима
  if (state === STATES.MENU) {
    return (
      <div className="min-h-screen bg-gradient-game p-4 flex flex-col">
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center pt-8 mb-8"
        >
          <div className="text-5xl mb-3">⚔️</div>
          <h1 className="text-2xl font-bold text-white">Дуэль</h1>
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
                <h3 className="font-semibold text-lg text-white">Случайный соперник</h3>
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
                <h3 className="font-semibold text-lg text-white">Пригласить друга</h3>
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
      <div className="min-h-screen bg-gradient-game flex items-center justify-center p-4">
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
          <h2 className="text-xl font-bold mb-2 text-white">Ищем соперника...</h2>
          <p className="text-telegram-hint">Это займёт несколько секунд</p>
        </motion.div>
      </div>
    )
  }

  // Ожидание соперника
  if (state === STATES.WAITING_OPPONENT) {
    return (
      <div className="min-h-screen bg-gradient-game flex items-center justify-center p-4">
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
          <h2 className="text-xl font-bold mb-2 text-white">Ожидаем соперника</h2>
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
      <div className="min-h-screen bg-gradient-game flex items-center justify-center p-4">
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
          <h2 className="text-2xl font-bold mb-2 text-white">Соперник найден!</h2>
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

  // Игра, ожидание ответа соперника или показ результата
  if ((state === STATES.PLAYING || state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.SHOWING_RESULT) && question) {
    return (
      <div className="min-h-screen bg-gradient-game p-4 flex flex-col">
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
          key={`q-${question.id}`}
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
                {selectedAnswer === answer.id && lastResult && !lastResult.is_correct && !lastResult.timeout && (
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
        </div>

        {/* Результат раунда */}
        <AnimatePresence>
          {(state === STATES.SHOWING_RESULT || state === STATES.WAITING_OPPONENT_ANSWER) && lastResult && (
            <motion.div
              initial={{ opacity: 0, y: 50 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: 50 }}
              className="fixed bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-[#1a1a2e] to-transparent"
            >
              <div className="glass rounded-2xl p-4">
                {/* Твой результат */}
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-game-primary flex items-center justify-center font-bold">
                      {user?.first_name?.[0] || '?'}
                    </div>
                    <span className="font-medium">Ты</span>
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
                
                {/* Разделитель */}
                <div className="border-t border-white/10 my-3"></div>
                
                {/* Результат соперника */}
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-game-danger flex items-center justify-center">
                      👤
                    </div>
                    <span className="font-medium text-white/70">Соперник</span>
                  </div>
                  <div className="flex items-center gap-2 text-white/50">
                    {opponentAnswer && opponentAnswer.answered ? (
                      opponentAnswer.correct ? (
                        <>
                          <span>✅</span>
                          <span className="font-bold text-game-success">Верно</span>
                        </>
                      ) : (
                        <>
                          <span>❌</span>
                          <span className="font-bold text-game-danger">Неверно</span>
                        </>
                      )
                    ) : (
                      <>
                        <span className="animate-pulse">⏳</span>
                        <span>Ожидание...</span>
                      </>
                    )}
                  </div>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    )
  }

  // Финиш
  if (state === STATES.FINISHED) {
    const isWinner = score.player > score.opponent
    const isDraw = score.player === score.opponent

    return (
      <div className="min-h-screen bg-gradient-game p-4 flex flex-col items-center justify-center">
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
          
          <h2 className="text-3xl font-bold mb-2 text-white">
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
                currentQuestionId.current = null
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
    <div className="min-h-screen bg-gradient-game flex items-center justify-center">
      <div className="text-center">
        <div className="w-12 h-12 border-4 border-game-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p className="text-telegram-hint">Загрузка...</p>
      </div>
    </div>
  )
}

export default DuelPage
