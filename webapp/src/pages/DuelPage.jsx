import { useState, useEffect, useCallback, useRef } from 'react'
import { useNavigate, useSearchParams, useParams } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'

// Состояния дуэли
const STATES = {
  MENU: 'menu',
  SEARCHING: 'searching',
  INVITE: 'invite', // Приглашение друга
  ENTER_CODE: 'enter_code', // Ввод кода дуэли
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
  const [coins, setCoins] = useState(0) // Монеты игрока
  const [hiddenAnswers, setHiddenAnswers] = useState([]) // Скрытые ответы после 50/50
  const [hintUsed, setHintUsed] = useState(false) // Использована ли подсказка в раунде
  const [searchTimeLeft, setSearchTimeLeft] = useState(30) // Таймер поиска соперника
  const [inviteCode, setInviteCode] = useState('') // Код для присоединения к дуэли
  const [opponent, setOpponent] = useState(null) // Данные оппонента {name, rating}
  const [myRating, setMyRating] = useState(0) // Мой рейтинг
  
  const currentQuestionId = useRef(null)
  const timerRef = useRef(null)
  const answeredRoundId = useRef(null)
  const searchTimerRef = useRef(null)
  const hasAnsweredRef = useRef(false) // Для проверки в таймере

  // Загрузка профиля для получения монет
  const loadProfile = async () => {
    try {
      const response = await api.getProfile()
      if (response.success) {
        setCoins(response.data.coins || 0)
        setMyRating(response.data.rating ?? 0)
      }
    } catch (err) {
      console.error('Failed to load profile:', err)
    }
  }

  useEffect(() => {
    loadProfile()
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

  // Функция для обработки таймаута
  const handleTimeoutSubmit = useCallback(async () => {
    if (!duel || hasAnsweredRef.current) return
    
    hasAnsweredRef.current = true
    setSelectedAnswer(-1)
    setLastResult({ is_correct: false, timeout: true })
    setOpponentAnswer({ answered: false, correct: null })
    setState(STATES.WAITING_OPPONENT_ANSWER)
    hapticFeedback('warning')
    
    // Отправляем "пустой" ответ на сервер чтобы закрыть раунд
    try {
      const response = await api.submitAnswer(duel.duel_id, round, null)
      if (response.success && response.data?.round_id) {
        answeredRoundId.current = response.data.round_id
        
        // Если раунд уже закрыт (оппонент тоже ответил/таймаут)
        if (response.data.opponent_answered) {
          setOpponentAnswer({
            answered: true,
            correct: response.data.opponent_correct
          })
          if (response.data.correct_answer_id) {
            setCorrectAnswer(response.data.correct_answer_id)
          }
          setState(STATES.SHOWING_RESULT)
          
          // Через 3 секунды переход к следующему раунду
          setTimeout(() => {
            currentQuestionId.current = null
            answeredRoundId.current = null
            loadDuel(duel.duel_id)
          }, 3000)
        }
      }
    } catch (err) {
      console.error('Failed to submit timeout:', err)
    }
  }, [duel, round])

  useEffect(() => {
    if (timerRef.current) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }

    if (state !== STATES.PLAYING) return
    if (!question) return
    
    timerRef.current = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          if (timerRef.current) {
            clearInterval(timerRef.current)
            timerRef.current = null
          }
          // Вызываем таймаут
          if (!hasAnsweredRef.current) {
            handleTimeoutSubmit()
          }
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
  }, [state, question?.id, handleTimeoutSubmit])

  useEffect(() => {
    if (!duel || state === STATES.FINISHED || state === STATES.SHOWING_RESULT) return
    
    const interval = state === STATES.WAITING_OPPONENT_ANSWER ? 1000 : 3000
    
    const checkInterval = setInterval(() => {
      checkDuelStatus(duel.duel_id)
    }, interval)

    return () => clearInterval(checkInterval)
  }, [duel?.duel_id, state])

  // Таймер поиска соперника - 30 секунд максимум
  useEffect(() => {
    if (state !== STATES.WAITING_OPPONENT) {
      if (searchTimerRef.current) {
        clearInterval(searchTimerRef.current)
        searchTimerRef.current = null
      }
      return
    }

    setSearchTimeLeft(30)
    
    searchTimerRef.current = setInterval(() => {
      setSearchTimeLeft(prev => {
        if (prev <= 1) {
          // Время вышло - отменяем дуэль
          clearInterval(searchTimerRef.current)
          searchTimerRef.current = null
          
          if (duel) {
            api.cancelDuel(duel.duel_id).catch(console.error)
          }
          
          setError('Соперник не найден. Попробуйте ещё раз.')
          setState(STATES.MENU)
          hapticFeedback('error')
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => {
      if (searchTimerRef.current) {
        clearInterval(searchTimerRef.current)
        searchTimerRef.current = null
      }
    }
  }, [state, duel])

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
        } else if (state === STATES.WAITING_OPPONENT && (data.opponent || data.status === 'in_progress')) {
          // Соперник найден! Показываем экран FOUND
          if (data.opponent) {
            setOpponent({
              name: data.opponent.name || 'Соперник',
              rating: data.opponent.rating ?? 0,
              photo_url: data.opponent.photo_url
            })
          }
          setState(STATES.FOUND)
          hapticFeedback('success')
          setTimeout(() => {
            loadDuel(duelId)
          }, 3000)
        } else if (state === STATES.WAITING_OPPONENT_ANSWER) {
          const currentRoundId = data.round_status?.round_id
          const lastClosedRound = data.last_closed_round
          
          // Раунд закрылся (оба ответили или таймаут)
          const roundClosed = (
            // Наш раунд в lastClosedRound
            (answeredRoundId.current && lastClosedRound && 
             lastClosedRound.round_id === answeredRoundId.current) ||
            // Или round_status показывает что оппонент ответил
            data.round_status?.opponent_answered ||
            // Или текущий раунд уже другой
            (answeredRoundId.current && currentRoundId && 
             currentRoundId !== answeredRoundId.current)
          )
          
          if (roundClosed) {
            // Берём данные из lastClosedRound или round_status
            const opponentCorrect = lastClosedRound?.round_id === answeredRoundId.current
              ? lastClosedRound.opponent_correct
              : data.round_status?.opponent_correct
            const correctAnswerId = lastClosedRound?.round_id === answeredRoundId.current
              ? lastClosedRound.correct_answer_id
              : data.round_status?.correct_answer_id
            
            setOpponentAnswer({
              answered: true,
              correct: opponentCorrect ?? false
            })
            
            if (correctAnswerId && !correctAnswer) {
              setCorrectAnswer(correctAnswerId)
            }
            
            setState(STATES.SHOWING_RESULT)
            hapticFeedback(opponentCorrect ? 'warning' : 'success')
            
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
          // Новый вопрос — сбрасываем состояние и запускаем таймер
          if (currentQuestionId.current !== data.question.id) {
            currentQuestionId.current = data.question.id
            answeredRoundId.current = null
            
            // Ответы уже перемешаны на сервере (одинаково для обоих игроков)
            setQuestion(data.question)
            setSelectedAnswer(null)
            hasAnsweredRef.current = false
            setCorrectAnswer(null)
            setOpponentAnswer(null)
            setLastResult(null)
            setHiddenAnswers([])
            // hintUsed НЕ сбрасываем - одна подсказка на всю дуэль
            
            const timeLimit = data.round_status?.time_limit || 30
            if (data.round_status?.question_sent_at) {
              const sentAt = new Date(data.round_status.question_sent_at)
              const elapsed = Math.floor((Date.now() - sentAt.getTime()) / 1000)
              const newTimeLeft = Math.max(0, timeLimit - (isNaN(elapsed) ? 0 : elapsed))
              setTimeLeft(newTimeLeft)
            } else {
              setTimeLeft(timeLimit)
            }
            
            // При новом вопросе ВСЕГДА переходим в PLAYING
            setState(STATES.PLAYING)
          } else if (state !== STATES.PLAYING && state !== STATES.SHOWING_RESULT && state !== STATES.WAITING_OPPONENT_ANSWER) {
            // Тот же вопрос, но ещё не играем
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
    setHintUsed(false) // Сбрасываем при новой дуэли
    hapticFeedback('medium')
    
    try {
      const response = await api.createDuel('random')
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        
        if (data.opponent_id) {
          // Сохраняем данные оппонента
          if (data.opponent) {
            setOpponent({
              name: data.opponent.name || 'Соперник',
              rating: data.opponent.rating ?? 0,
              photo_url: data.opponent.photo_url
            })
          }
          setState(STATES.FOUND)
          hapticFeedback('success')
          
          setTimeout(() => {
            loadDuel(data.duel_id)
          }, 3000)
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

  // Создать дуэль для приглашения друга
  const inviteFriend = async () => {
    setLoading(true)
    setError(null)
    setHintUsed(false)
    hapticFeedback('medium')
    
    try {
      const response = await api.createDuel('invite')
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        setState(STATES.INVITE)
      } else {
        setError(response.error || 'Не удалось создать дуэль')
        setState(STATES.MENU)
      }
    } catch (err) {
      console.error('Failed to create invite duel:', err)
      setError(`Ошибка: ${err.message}`)
      setState(STATES.MENU)
    } finally {
      setLoading(false)
    }
  }

  // Поделиться ссылкой через Telegram
  const shareInvite = () => {
    if (!duel?.code) return
    
    const botUsername = 'duelquizbot'
    const url = `https://t.me/${botUsername}/app?startapp=duel_${duel.code}`
    const text = `🎮 Приглашаю тебя на дуэль!\n\nКод: ${duel.code}`
    
    // Используем Telegram share
    if (window.Telegram?.WebApp?.openTelegramLink) {
      window.Telegram.WebApp.openTelegramLink(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`)
    } else {
      // Fallback - копируем в буфер
      navigator.clipboard.writeText(`${text}\n${url}`)
      hapticFeedback('success')
    }
  }

  // Присоединиться по коду
  const joinByCode = async () => {
    if (!inviteCode.trim()) return
    
    setLoading(true)
    setError(null)
    hapticFeedback('medium')
    
    try {
      const response = await api.joinDuel(inviteCode.trim().toUpperCase())
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        
        if (data.initiator) {
           setOpponent({
             name: data.initiator.name || 'Игрок',
             rating: data.initiator.rating ?? 0,
             photo_url: data.initiator.photo_url
           })
        }
        
        setState(STATES.FOUND)
        hapticFeedback('success')
        
        setTimeout(() => {
          loadDuel(data.duel_id)
        }, 2000)
      } else {
        setError(response.error || 'Дуэль не найдена')
        hapticFeedback('error')
      }
    } catch (err) {
      console.error('Failed to join duel:', err)
      setError(`Ошибка: ${err.message}`)
      hapticFeedback('error')
    } finally {
      setLoading(false)
    }
  }

  const handleAnswerSelect = async (answerId) => {
    if (selectedAnswer !== null || !duel || !question) return
    
    setSelectedAnswer(answerId)
    hasAnsweredRef.current = true
    hapticFeedback('light')
    
    // Не останавливаем таймер визуально, чтобы видеть, сколько времени осталось у соперника
    // if (timerRef.current) {
    //   clearInterval(timerRef.current)
    //   timerRef.current = null
    // }
    
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

  // Использование подсказки 50/50
  const useHint = async () => {
    if (hintUsed || selectedAnswer !== null || !duel) return
    
    const HINT_COST = 10
    if (coins < HINT_COST) {
      hapticFeedback('error')
      return
    }
    
    try {
      const response = await api.useHint(duel.duel_id)
      
      if (response.success) {
        const data = response.data
        setHiddenAnswers(data.hidden_answer_ids || [])
        setCoins(data.coins_remaining)
        setHintUsed(true)
        hapticFeedback('success')
      }
    } catch (err) {
      console.error('Failed to use hint:', err)
      hapticFeedback('error')
    }
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
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col p-4">
        <div className="aurora-blob aurora-blob-1 opacity-50" />
        <div className="aurora-blob aurora-blob-2 opacity-50" />
        <div className="noise-overlay" />

        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="relative z-10 pt-8 mb-8"
        >
          <button 
             onClick={() => navigate('/')}
             className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center mb-6 hover:bg-white/20 transition-colors"
          >
             <span className="text-xl">←</span>
          </button>
          <h1 className="text-4xl font-black text-white italic tracking-tight mb-2 uppercase">Дуэли</h1>
          <p className="text-white/60 text-lg">Сразись за рейтинг и монеты</p>
        </motion.div>

        {error && (
          <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              className="relative z-10 bg-red-500/10 border border-red-500/20 rounded-2xl p-4 mb-4 backdrop-blur-md"
          >
            <p className="text-red-400 text-sm font-medium">{error}</p>
          </motion.div>
        )}

        <div className="relative z-10 flex-1 flex flex-col gap-4 justify-end pb-8">
          <motion.button
            initial={{ opacity: 0, x: -30 }}
            animate={{ opacity: 1, x: 0 }}
            onClick={startSearch}
            disabled={loading}
            className="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-[#4F46E5] to-[#7C3AED] p-1 disabled:opacity-50 transition-transform active:scale-95"
          >
            <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
            <div className="relative bg-[#0F172A]/40 backdrop-blur-sm rounded-[28px] p-6 flex items-center gap-5">
                <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-white/20 to-white/5 flex items-center justify-center text-3xl shadow-inner border border-white/10">
                  🎲
                </div>
                <div>
                  <h3 className="font-bold text-xl text-white mb-1">Случайный бой</h3>
                  <p className="text-white/60 text-sm">Поиск по рейтингу</p>
                </div>
            </div>
          </motion.button>

          <motion.button
            initial={{ opacity: 0, x: 30 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.1 }}
            onClick={inviteFriend}
            disabled={loading}
            className="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-[#06B6D4] to-[#3B82F6] p-1 disabled:opacity-50 transition-transform active:scale-95"
          >
             <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
            <div className="relative bg-[#0F172A]/40 backdrop-blur-sm rounded-[28px] p-6 flex items-center gap-5">
              <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-white/20 to-white/5 flex items-center justify-center text-3xl shadow-inner border border-white/10">
                ⚔️
              </div>
              <div>
                <h3 className="font-bold text-xl text-white mb-1">С другом</h3>
                <p className="text-white/60 text-sm">Создать приватную игру</p>
              </div>
            </div>
          </motion.button>

          <motion.button
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            onClick={() => setState(STATES.ENTER_CODE)}
            className="w-full py-4 text-center text-white/40 font-medium hover:text-white transition-colors"
          >
            Ввести код приглашения
          </motion.button>
        </div>
      </div>
    )
  }

  // Экран ввода кода
  if (state === STATES.ENTER_CODE) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6">
        <div className="noise-overlay" />
        
        <motion.div 
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            className="relative z-10 w-full max-w-sm"
        >
            <h2 className="text-3xl font-bold text-white text-center mb-8">Ввод кода</h2>
            
            <input
              type="text"
              value={inviteCode}
              onChange={(e) => setInviteCode(e.target.value.toUpperCase())}
              placeholder="CODE"
              maxLength={8}
              className="w-full bg-white/5 border border-white/10 rounded-2xl py-6 text-center text-4xl font-mono font-bold text-white placeholder-white/20 outline-none focus:border-game-primary transition-colors mb-6 tracking-widest uppercase"
              autoFocus
            />
            
            <button
              onClick={joinByCode}
              disabled={loading || inviteCode.length < 4}
              className="w-full py-4 bg-game-primary rounded-xl font-bold text-white shadow-lg shadow-game-primary/30 disabled:opacity-50 disabled:shadow-none transition-all active:scale-95 mb-4"
            >
              {loading ? 'Поиск...' : 'Присоединиться'}
            </button>
            
            <button
               onClick={() => setState(STATES.MENU)}
               className="w-full py-3 text-white/40 font-medium"
            >
                Отмена
            </button>
        </motion.div>
      </div>
    )
  }

  // Экраны ожидания/поиска
  if (state === STATES.SEARCHING || state === STATES.WAITING_OPPONENT || state === STATES.INVITE) {
     const isInvite = state === STATES.INVITE
     const isSearching = state === STATES.SEARCHING
     
     return (
       <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6 text-center">
         <div className="noise-overlay" />
         
         <div className="relative z-10 w-full max-w-sm">
             {/* Radar Animation */}
             <div className="relative w-64 h-64 mx-auto mb-12 flex items-center justify-center">
                 <motion.div 
                    animate={{ scale: [1, 2], opacity: [0.5, 0] }}
                    transition={{ duration: 2, repeat: Infinity, ease: "easeOut" }}
                    className="absolute inset-0 border border-game-primary/30 rounded-full"
                 />
                 <motion.div 
                    animate={{ scale: [1, 2], opacity: [0.5, 0] }}
                    transition={{ duration: 2, repeat: Infinity, ease: "easeOut", delay: 0.5 }}
                    className="absolute inset-0 border border-game-primary/30 rounded-full"
                 />
                 
                 <div className="w-32 h-32 bg-white/5 backdrop-blur-xl rounded-full border border-white/10 flex items-center justify-center relative overflow-hidden">
                     <motion.div
                        animate={{ rotate: 360 }}
                        transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
                        className="absolute inset-0 bg-gradient-to-t from-game-primary/20 to-transparent w-full h-1/2 origin-bottom"
                     />
                     <div className="text-4xl relative z-10">
                        {isInvite ? '📨' : '🔭'}
                     </div>
                 </div>
             </div>
             
             <h2 className="text-2xl font-bold text-white mb-2">
                 {isInvite ? 'Ожидание друга' : isSearching ? 'Поиск оппонента' : 'Ожидание...'}
             </h2>
             
             {isInvite ? (
                 <div className="mb-8">
                     <div className="bg-white/5 border border-white/10 rounded-xl p-4 mb-4">
                        <p className="text-white/40 text-xs uppercase mb-1">Код комнаты</p>
                        <p className="text-3xl font-mono font-bold text-white tracking-widest">{duel?.code}</p>
                     </div>
                     <button onClick={shareInvite} className="w-full py-3 bg-white/10 rounded-xl text-white font-medium mb-2">
                        Поделиться ссылкой
                     </button>
                 </div>
             ) : (
                 <p className="text-white/40 mb-8 font-mono">
                    {searchTimeLeft > 0 ? `00:${searchTimeLeft.toString().padStart(2, '0')}` : 'Отмена...'}
                 </p>
             )}
             
             <button 
                onClick={() => {
                   if (duel) api.cancelDuel(duel.duel_id).catch(console.error)
                   setState(STATES.MENU)
                }}
                className="text-white/40 text-sm hover:text-white"
             >
                Отменить поиск
             </button>
         </div>
       </div>
     )
  }

  // Соперник найден
  if (state === STATES.FOUND) {
      return (
        <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6">
            <div className="noise-overlay" />
            
            <div className="relative z-10 w-full flex flex-col items-center gap-8">
                <motion.div
                   initial={{ x: -100, opacity: 0 }}
                   animate={{ x: 0, opacity: 1 }}
                   transition={{ type: "spring", stiffness: 100 }}
                   className="flex flex-col items-center"
                >
                    <AvatarWithFrame user={user} size={96} showGlow />
                    <p className="mt-4 font-bold text-xl text-white">{user?.first_name || 'Вы'}</p>
                    <div className="px-3 py-1 bg-white/10 rounded-full text-xs font-mono mt-2 text-white/60">
                        {myRating} MMR
                    </div>
                </motion.div>
                
                <motion.div
                    initial={{ scale: 0, rotate: -180 }}
                    animate={{ scale: 1, rotate: 0 }}
                    transition={{ delay: 0.3, type: "spring" }}
                    className="text-6xl font-black italic text-transparent bg-clip-text bg-gradient-to-r from-red-500 to-orange-500"
                >
                    VS
                </motion.div>
                
                <motion.div
                   initial={{ x: 100, opacity: 0 }}
                   animate={{ x: 0, opacity: 1 }}
                   transition={{ type: "spring", stiffness: 100, delay: 0.1 }}
                   className="flex flex-col items-center"
                >
                     <div className="w-24 h-24 rounded-full bg-gradient-to-br from-red-500 to-orange-600 p-1 shadow-[0_0_30px_rgba(239,68,68,0.4)]">
                        <div className="w-full h-full rounded-full bg-black/40 backdrop-blur-sm flex items-center justify-center text-4xl overflow-hidden">
                            {opponent?.photo_url ? (
                                <img src={opponent.photo_url} alt="" className="w-full h-full object-cover" />
                            ) : (
                                <span>{opponent?.name?.[0] || '?'}</span>
                            )}
                        </div>
                     </div>
                    <p className="mt-4 font-bold text-xl text-white">{opponent?.name || 'Соперник'}</p>
                    <div className="px-3 py-1 bg-white/10 rounded-full text-xs font-mono mt-2 text-white/60">
                        {opponent?.rating || '???'} MMR
                    </div>
                </motion.div>
            </div>
        </div>
      )
  }
  
  // ИГРОВОЙ ПРОЦЕСС
  if ((state === STATES.PLAYING || state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.SHOWING_RESULT) && question) {
      const isCorrect = lastResult?.is_correct
      const isWrong = lastResult && !lastResult.is_correct
      
      return (
        <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col">
            <div className="noise-overlay" />
            
            {/* Header VS */}
            <div className="relative z-20 pt-4 px-4 pb-2 bg-gradient-to-b from-black/40 to-transparent">
                <div className="flex justify-between items-center max-w-md mx-auto w-full">
                    {/* Player */}
                    <div className="flex items-center gap-3">
                         <div className="relative">
                             <AvatarWithFrame user={user} size={48} />
                             <div className="absolute -bottom-1 -right-1 bg-game-primary text-white text-[10px] font-bold px-1.5 py-0.5 rounded-md">
                                 YOU
                             </div>
                         </div>
                         <div className="flex flex-col">
                             <span className="text-2xl font-black text-white">{score.player}</span>
                         </div>
                    </div>
                    
                    {/* Timer */}
                    <div className="relative flex flex-col items-center">
                        <div className="relative w-14 h-14 flex items-center justify-center">
                            <svg className="w-full h-full -rotate-90 absolute inset-0">
                               <circle cx="28" cy="28" r="26" stroke="rgba(255,255,255,0.1)" strokeWidth="4" fill="none" />
                               <motion.circle 
                                  cx="28" cy="28" r="26" 
                                  stroke={timeLeft <= 10 ? '#EF4444' : '#6366F1'} 
                                  strokeWidth="4" 
                                  fill="none" 
                                  strokeDasharray={163}
                                  strokeDashoffset={163 - (163 * (timeLeft / 30))}
                                  strokeLinecap="round"
                                  initial={{ strokeDashoffset: 163 }}
                                  animate={{ strokeDashoffset: 163 - (163 * (timeLeft / 30)) }}
                                  transition={{ duration: 0.5 }}
                               />
                            </svg>
                            <span className={`relative z-10 font-bold ${timeLeft <= 10 ? 'text-red-500' : 'text-white'}`}>
                                {timeLeft}
                            </span>
                        </div>
                        <div className="mt-1 text-[10px] font-mono text-white/40 font-bold">R{round}/{totalRounds}</div>
                    </div>
                    
                    {/* Opponent */}
                    <div className="flex items-center gap-3 flex-row-reverse">
                         <div className="relative">
                             <div className="w-12 h-12 rounded-full bg-gradient-to-br from-red-500 to-orange-600 p-0.5 shadow-lg">
                                <div className="w-full h-full rounded-full bg-black/40 backdrop-blur-sm overflow-hidden flex items-center justify-center text-lg">
                                    {opponent?.photo_url ? (
                                        <img src={opponent.photo_url} alt="" className="w-full h-full object-cover" />
                                    ) : (
                                        <span>{opponent?.name?.[0] || '?'}</span>
                                    )}
                                </div>
                             </div>
                         </div>
                         <div className="flex flex-col items-end">
                             <span className="text-2xl font-black text-white">{score.opponent}</span>
                         </div>
                    </div>
                </div>
            </div>

            {/* Question Area */}
            <div className="flex-1 flex flex-col justify-start pt-12 p-4 relative z-10">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={question.id}
                        initial={{ opacity: 0, y: 20, scale: 0.95 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: -20, scale: 0.95 }}
                        className="w-full max-w-md"
                    >
                         <div className="mb-4 flex justify-center">
                            <span className="px-3 py-1 rounded-full bg-white/10 text-xs font-medium text-white/60 backdrop-blur-md">
                                {question.category || 'Вопрос'}
                            </span>
                         </div>
                         <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-[32px] p-8 text-center shadow-2xl relative overflow-hidden">
                             <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-white/20 to-transparent" />
                             <p className="text-xl md:text-2xl font-bold text-white leading-relaxed">
                                 {question.text}
                             </p>
                         </div>
                    </motion.div>
                </AnimatePresence>
            </div>
            
            {/* Answers Grid */}
            <div className="p-4 relative z-10 pb-8">
                 <div className="grid grid-cols-2 gap-3 max-w-md mx-auto mb-4">
                     {question.answers
                        .filter(answer => !hiddenAnswers.includes(answer.id))
                        .map((answer, idx) => {
                            const isSelected = selectedAnswer === answer.id
                            const isCorrectAnswer = correctAnswer === answer.id
                            
                            let statusClass = "bg-white/5 border-white/10 text-white"
                            if (isSelected) statusClass = "bg-white/20 border-white/30 text-white"
                            if (isCorrectAnswer) statusClass = "bg-green-500/20 border-green-500 text-green-400"
                            if (isSelected && lastResult && !lastResult.is_correct) statusClass = "bg-red-500/20 border-red-500 text-red-400"
                            if (selectedAnswer !== null && !isSelected && !isCorrectAnswer) statusClass = "opacity-30 bg-white/5 border-white/5"

                            return (
                                <motion.button
                                    key={answer.id}
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: idx * 0.1 }}
                                    onClick={() => handleAnswerSelect(answer.id)}
                                    disabled={selectedAnswer !== null}
                                    className={`relative min-h-[100px] rounded-2xl p-4 flex flex-col items-center justify-center text-center text-sm font-semibold border backdrop-blur-md transition-all active:scale-95 ${statusClass}`}
                                >
                                    {answer.text}
                                    
                                    {isCorrectAnswer && (
                                        <div className="absolute top-2 right-2 text-green-400">✓</div>
                                    )}
                                    {isSelected && lastResult && !lastResult.is_correct && (
                                        <div className="absolute top-2 right-2 text-red-400">✗</div>
                                    )}
                                </motion.button>
                            )
                        })}
                 </div>
                 
                 {/* Hint Button */}
                 {state === STATES.PLAYING && !selectedAnswer && !hintUsed && (
                     <div className="flex justify-center">
                         <button
                            onClick={useHint}
                            disabled={coins < 10}
                            className={`px-6 py-2 rounded-full text-xs font-bold flex items-center gap-2 transition-all ${
                                coins >= 10 ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-white/5 text-white/30'
                            }`}
                         >
                            <span>💡 50/50</span>
                            <span className="opacity-50">10 💰</span>
                         </button>
                     </div>
                 )}
            </div>
            
            {/* Round Result Overlay */}
            <AnimatePresence>
                {state === STATES.SHOWING_RESULT && (
                   <motion.div 
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      className="absolute inset-0 z-30 flex items-center justify-center bg-black/60 backdrop-blur-sm pointer-events-none"
                   >
                       <motion.div 
                          initial={{ scale: 0.5, y: 50 }}
                          animate={{ scale: 1, y: 0 }}
                          className="bg-[#0F172A] border border-white/10 rounded-3xl p-6 text-center shadow-2xl min-w-[200px]"
                       >
                           {isCorrect ? (
                               <>
                                 <div className="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-[0_0_20px_rgba(34,197,94,0.4)]">
                                    <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                 </div>
                                 <div className="text-xl font-bold text-green-400">Верно!</div>
                               </>
                           ) : (
                               <>
                                 <div className="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-[0_0_20px_rgba(239,68,68,0.4)]">
                                    <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                 </div>
                                 <div className="text-xl font-bold text-red-400">Мимо</div>
                               </>
                           )}
                       </motion.div>
                   </motion.div>
                )}
            </AnimatePresence>
            
            {/* Waiting Opponent Toast */}
            <AnimatePresence>
                {state === STATES.WAITING_OPPONENT_ANSWER && (
                    <motion.div
                       initial={{ y: 100 }}
                       animate={{ y: 0 }}
                       exit={{ y: 100 }}
                       className="fixed bottom-8 left-1/2 -translate-x-1/2 z-30 bg-black/80 backdrop-blur-md px-6 py-3 rounded-full border border-white/10 text-white text-sm font-medium flex items-center gap-3"
                    >
                        <div className="w-2 h-2 rounded-full bg-yellow-400 animate-pulse" />
                        Ожидаем соперника...
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
      )
  }
  
  // FINISH SCREEN
  if (state === STATES.FINISHED) {
      const isWin = score.player > score.opponent
      const isDraw = score.player === score.opponent
      // Используем значение с сервера или фоллбэк
      const ratingChangeVal = duel?.rating_change ?? (isWin ? 10 : isDraw ? 0 : -10)
      const ratingChange = ratingChangeVal > 0 ? `+${ratingChangeVal}` : `${ratingChangeVal}`
      
      return (
         <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6 text-center">
            <div className="noise-overlay" />
            
            {isWin && (
                <>
                  <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-game-primary/20 blur-[100px] rounded-full" />
                  {/* Confetti particles could go here */}
                </>
            )}
            
            <motion.div
               initial={{ scale: 0.8, opacity: 0 }}
               animate={{ scale: 1, opacity: 1 }}
               className="relative z-10 bg-black/40 backdrop-blur-xl border border-white/10 rounded-[40px] p-8 w-full max-w-sm"
            >
                <div className="text-7xl mb-6">{isWin ? '🏆' : isDraw ? '🤝' : '💀'}</div>
                
                <h1 className={`text-4xl font-black uppercase italic mb-2 ${isWin ? 'text-gradient-gold' : 'text-white'}`}>
                    {isWin ? 'Победа' : isDraw ? 'Ничья' : 'Поражение'}
                </h1>
                
                <div className="flex items-center justify-center gap-6 my-8">
                     <div className="text-center">
                         <AvatarWithFrame user={user} size={64} />
                         <div className="text-3xl font-bold text-white mt-2">{score.player}</div>
                     </div>
                     <div className="text-white/20 font-black text-2xl">VS</div>
                     <div className="text-center">
                        <div className="w-16 h-16 rounded-full bg-white/10 mx-auto flex items-center justify-center text-2xl border-2 border-white/10">
                            {opponent?.photo_url ? <img src={opponent.photo_url} className="w-full h-full rounded-full object-cover"/> : (opponent?.name?.[0] || '?')}
                        </div>
                        <div className="text-3xl font-bold text-white/60 mt-2">{score.opponent}</div>
                     </div>
                </div>
                
                <div className="bg-white/5 rounded-2xl p-4 mb-6">
                    <div className="text-sm text-white/40 uppercase font-bold tracking-wider mb-1">Рейтинг</div>
                    <div className="text-2xl font-bold text-white">
                        {ratingChange} <span className="text-sm font-normal text-white/40">MMR</span>
                    </div>
                </div>
                
                <button
                    onClick={() => {
                        setState(STATES.MENU)
                        setDuel(null)
                        setScore({ player: 0, opponent: 0 })
                    }} 
                    className="w-full py-4 bg-white rounded-2xl text-black font-bold text-lg mb-3 hover:bg-white/90 transition-colors"
                >
                    Продолжить
                </button>
            </motion.div>
         </div>
      )
  }

  return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
          <div className="spinner" />
      </div>
  )
}

export default DuelPage