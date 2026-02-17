import { useState, useEffect, useCallback, useRef } from 'react'
import { useNavigate, useSearchParams, useParams } from 'react-router-dom'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api, { getWsBaseUrl } from '../api/client'
import { deriveDuelViewState } from './duelStateMachine'
import { addNotificationItems } from '../utils/notificationInbox'
import { WS_STATUS, FOUND_SCREEN_MIN_MS, ROUND_RESULT_MIN_MS, STATES } from './duel/constants'
import { DuelMenuView, DuelEnterCodeView, DuelWaitingView, DuelFoundView } from './duel/DuelLobbyViews'
import { DuelPlayingView, DuelFinishedView } from './duel/DuelGameViews'
import { buildRewardNotificationItems } from './duel/rewardNotifications'
import { useDuelRealtime } from './duel/useDuelRealtime'
import { useDuelPolling } from './duel/useDuelPolling'
import { useDuelSnapshotSync } from './duel/useDuelSnapshotSync'
import { useDuelSearchTimeout } from './duel/useDuelSearchTimeout'

function DuelPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { id: duelIdParam } = useParams()
  const { user, webApp } = useTelegram()
  const wsBaseUrl = getWsBaseUrl()
  const wsConfigured = Boolean(wsBaseUrl)
  
  const [state, setState] = useState(STATES.MENU)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  
  const [duel, setDuel] = useState(null)
  const [question, setQuestion] = useState(null)
  const [roundStatus, setRoundStatus] = useState(null)
  const [timeLeft, setTimeLeft] = useState(30)
  const [round, setRound] = useState(1)
  const [totalRounds, setTotalRounds] = useState(10)
  const [score, setScore] = useState({ player: 0, opponent: 0 })
  const [selectedAnswer, setSelectedAnswer] = useState(null)
  const [correctAnswer, setCorrectAnswer] = useState(null)
  const [lastResult, setLastResult] = useState(null)
  const [opponentAnswer, setOpponentAnswer] = useState(null)
  const [roundHistory, setRoundHistory] = useState([])
  const [nextRoundCountdown, setNextRoundCountdown] = useState(null)
  const [coins, setCoins] = useState(0) // Монеты игрока
  const [hiddenAnswers, setHiddenAnswers] = useState([]) // Скрытые ответы после 50/50
  const [hintUsed, setHintUsed] = useState(false) // Использована ли подсказка в раунде
  const [searchTimeLeft, setSearchTimeLeft] = useState(30) // Таймер поиска соперника
  const [ghostFallbackPending, setGhostFallbackPending] = useState(false)
  const [inviteCode, setInviteCode] = useState('') // Код для присоединения к дуэли
  const [opponent, setOpponent] = useState(null) // Данные оппонента {name, rating}
  const [incomingRematch, setIncomingRematch] = useState(null)
  const [myRating, setMyRating] = useState(0) // Мой рейтинг
  
  const currentQuestionId = useRef(null)
  const timerRef = useRef(null)
  const answeredRoundId = useRef(null)
  const selectedAnswerRef = useRef(null)
  const hasAnsweredRef = useRef(false) // Для проверки в таймере
  const duelStateRef = useRef(STATES.MENU)
  const nextRoundTimerRef = useRef(null)
  const nextRoundIntervalRef = useRef(null)
  const loadDuelRef = useRef(null)
  const checkDuelStatusRef = useRef(null)
  const applyDuelSnapshotRef = useRef(null)
  const handleUnauthorizedErrorRef = useRef(null)
  const autoJoinAttemptedRef = useRef(false)
  const autoModeHandledRef = useRef(false)
  const seenAchievementRewardsRef = useRef(new Set())
  const foundScreenUntilRef = useRef(0)
  const roundResultUntilRef = useRef(0)
  const finishedRewardsShownRef = useRef(new Set())
  const ghostPoolAvailableRef = useRef(null)
  const foundLoadTimerRef = useRef(null)

  const {
    wsConnected,
    wsConnectionState,
    wsRetrying,
    wsReconnectAttempt,
    closeDuelSocket,
    handleRealtimeRetry,
  } = useDuelRealtime({
    duelId: duel?.duel_id,
    wsConfigured,
    wsBaseUrl,
    duelStateRef,
    checkDuelStatusRef,
    getDuelWsTicket: api.getDuelWsTicket,
  })

  const enterFoundState = useCallback(() => {
    foundScreenUntilRef.current = Date.now() + FOUND_SCREEN_MIN_MS
    setState(STATES.FOUND)
  }, [])

  const queueRewardNotifications = useCallback((payload) => {
    const next = buildRewardNotificationItems(payload, seenAchievementRewardsRef.current)
    if (next.length === 0) return

    addNotificationItems(next)
  }, [])

  const queueFinishedRewardsIfNeeded = useCallback((payload) => {
    if (!payload) return

    const duelId = payload.duel_id
    const stableKey = duelId ? `duel_${duelId}` : `duel_fallback_${Date.now()}`
    const achievementUnlocks = Array.isArray(payload.achievement_unlocks) ? payload.achievement_unlocks : []

    if (achievementUnlocks.length === 0) return
    if (finishedRewardsShownRef.current.has(stableKey)) return

    finishedRewardsShownRef.current.add(stableKey)
    queueRewardNotifications(payload)
  }, [queueRewardNotifications])

  const showNoTicketsModal = useCallback(() => {
    const text = 'Нет билетов для дуэли. Подожди восстановление или купи билеты в магазине.'
    if (webApp?.showPopup) {
      webApp.showPopup({
        title: 'Билеты закончились',
        message: text,
        buttons: [{ type: 'close', text: 'Ок' }],
      })
    } else {
      window.alert(text)
    }
    hapticFeedback('error')
  }, [webApp])

  const mapCancelReason = useCallback((reason) => {
    const code = String(reason || '')
    if (code === 'search_cancelled') return 'Поиск отменён.'
    if (code === 'rematch_expired') return 'Соперник не принял реванш за 30 секунд.'
    if (code === 'rematch_declined') return 'Соперник отклонил реванш.'
    if (code === 'rematch_cancelled_by_initiator') return 'Инициатор отменил приглашение на реванш.'
    return 'Соперник не найден.'
  }, [])

  const resetToLobbyByCancellation = (cancelReason) => {
    clearNextRoundTimers()
    clearFoundLoadTimer()
    setIncomingRematch(null)
    setDuel(null)
    setQuestion(null)
    setRoundStatus(null)
    setRoundHistory([])
    setSelectedAnswer(null)
    hasAnsweredRef.current = false
    answeredRoundId.current = null
    currentQuestionId.current = null
    setError(mapCancelReason(cancelReason))
    navigate('/')
  }

  const handleUnauthorizedError = (err) => {
    const message = String(err?.message || '')
    if (message.includes('Не авторизован')) {
      setError('Сессия истекла. Открой игру через бота ещё раз.')
      closeDuelSocket()
      return true
    }

    return false
  }
  handleUnauthorizedErrorRef.current = handleUnauthorizedError

  useEffect(() => {
    duelStateRef.current = state
  }, [state])

  useEffect(() => {
    selectedAnswerRef.current = selectedAnswer
  }, [selectedAnswer])

  const clearNextRoundTimers = useCallback(() => {
    if (nextRoundTimerRef.current) {
      clearTimeout(nextRoundTimerRef.current)
      nextRoundTimerRef.current = null
    }
    if (nextRoundIntervalRef.current) {
      clearInterval(nextRoundIntervalRef.current)
      nextRoundIntervalRef.current = null
    }
    setNextRoundCountdown(null)
  }, [])

  const clearFoundLoadTimer = useCallback(() => {
    if (foundLoadTimerRef.current) {
      clearTimeout(foundLoadTimerRef.current)
      foundLoadTimerRef.current = null
    }
  }, [])

  const scheduleLoadAfterFound = useCallback((duelId) => {
    if (!duelId) return
    clearFoundLoadTimer()
    foundLoadTimerRef.current = setTimeout(() => {
      foundLoadTimerRef.current = null
      if (typeof loadDuelRef.current === 'function') {
        loadDuelRef.current(duelId)
      }
    }, FOUND_SCREEN_MIN_MS)
  }, [clearFoundLoadTimer])

  const addRoundToHistory = useCallback((roundData) => {
    if (!roundData || !roundData.round_id) return
    setRoundHistory((prev) => {
      if (prev.some((entry) => entry.round_id === roundData.round_id)) {
        return prev
      }
      const next = [
        ...prev,
        {
          round_id: roundData.round_id,
          round_number: roundData.round_number ?? prev.length + 1,
          my_correct: Boolean(roundData.my_correct),
          opponent_correct: Boolean(roundData.opponent_correct),
          my_timed_out: Boolean(roundData.my_timed_out),
          opponent_timed_out: Boolean(roundData.opponent_timed_out),
          my_time_taken: Number.isFinite(roundData.my_time_taken) ? Number(roundData.my_time_taken) : null,
          opponent_time_taken: Number.isFinite(roundData.opponent_time_taken) ? Number(roundData.opponent_time_taken) : null,
        }
      ]
      return next.slice(-30)
    })
  }, [])

  const scheduleNextRoundLoad = useCallback((duelId, delayMs = 3000) => {
    if (!duelId) return
    clearNextRoundTimers()

    const initialSeconds = Math.max(1, Math.ceil(delayMs / 1000))
    setNextRoundCountdown(initialSeconds)

    nextRoundIntervalRef.current = setInterval(() => {
      setNextRoundCountdown((prev) => {
        if (prev === null) return null
        if (prev <= 1) {
          return 1
        }
        return prev - 1
      })
    }, 1000)

    nextRoundTimerRef.current = setTimeout(() => {
      currentQuestionId.current = null
      answeredRoundId.current = null
      clearNextRoundTimers()
      if (typeof loadDuelRef.current === 'function') {
        loadDuelRef.current(duelId)
      }
    }, delayMs)
  }, [clearNextRoundTimers])

  const enterRoundResultState = useCallback((duelId) => {
    roundResultUntilRef.current = Date.now() + ROUND_RESULT_MIN_MS
    setState(STATES.SHOWING_RESULT)
    scheduleNextRoundLoad(duelId, ROUND_RESULT_MIN_MS)
  }, [scheduleNextRoundLoad])

  // Загрузка профиля для получения монет

  const renderRealtimeBadge = (compact = false) => {
    const isOnline = wsConnected
    const isConnecting = wsConnectionState === WS_STATUS.CONNECTING
    const isDisabled = wsConnectionState === WS_STATUS.DISABLED || !wsConfigured
    const attemptLabel = isConnecting && wsReconnectAttempt > 0 ? ` · #${wsReconnectAttempt}` : ''
    const label = isDisabled ? 'Realtime · DISABLED' : isOnline ? 'Realtime · ON' : isConnecting ? `Realtime · CONNECTING${attemptLabel}` : 'Realtime · OFF'
    const subtitle = isDisabled ? 'Set VITE_WS_URL to enable realtime' : isOnline ? 'WebSocket active' : isConnecting ? 'Attempting reconnect...' : 'Polling fallback'
    const dotClass = isDisabled
      ? 'bg-slate-400 shadow-[0_0_8px_rgba(148,163,184,0.55)]'
      : isOnline
      ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]'
      : isConnecting
        ? 'bg-sky-400 shadow-[0_0_8px_rgba(56,189,248,0.75)]'
        : 'bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.65)]'

    if (compact) {
      return (
        <div className={`inline-flex items-center gap-2 rounded-full border backdrop-blur-md px-2.5 py-1 text-[10px] ${isDisabled ? 'border-slate-400/30 bg-slate-500/10 text-slate-200' : isOnline ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200' : isConnecting ? 'border-sky-400/40 bg-sky-500/10 text-sky-200' : 'border-amber-300/35 bg-amber-500/10 text-amber-100'}`}>
          <span className={`h-2 w-2 rounded-full ${dotClass} ${isOnline || isConnecting || isDisabled ? 'animate-pulse' : ''}`} />
          <span className="font-semibold tracking-wide">{label}</span>
        </div>
      )
    }

    return (
      <div
        className={`inline-flex items-center gap-3 rounded-2xl border backdrop-blur-md px-3 py-2 text-xs ${isDisabled ? 'border-slate-400/30 bg-slate-500/10 text-slate-200' : isOnline ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200' : isConnecting ? 'border-sky-400/40 bg-sky-500/10 text-sky-200' : 'border-amber-300/35 bg-amber-500/10 text-amber-100'}`}
        role="status"
        aria-live="polite"
      >
        <span className={`h-2.5 w-2.5 rounded-full ${dotClass} ${isOnline || isConnecting || isDisabled ? 'animate-pulse' : ''}`} />
        <div className="flex flex-col leading-tight">
          <span className="font-semibold tracking-wide">{label}</span>
          <span className="text-[10px] opacity-75">{subtitle}</span>
        </div>
        {!isDisabled && !isOnline && !isConnecting && duel?.duel_id && (
          <button
            onClick={handleRealtimeRetry}
            disabled={wsRetrying}
            className="ml-1 rounded-full border border-white/20 px-2 py-0.5 text-[10px] font-semibold text-white/90 hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Retry
          </button>
        )}
      </div>
    )
  }

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

  const loadIncomingRematch = useCallback(async () => {
    try {
      const response = await api.getIncomingRematch()
      if (!response.success) return
      const incoming = response.data?.incoming || null
      setIncomingRematch(incoming)
    } catch (_) {
      // ignore poll errors
    }
  }, [])

  const acceptIncomingRematch = useCallback(async () => {
    if (!incomingRematch?.duel_id) return

    setLoading(true)
    setError(null)
    try {
      const response = await api.acceptRematch(incomingRematch.duel_id)
      if (!response.success) {
        throw new Error(response.error || 'Не удалось принять реванш')
      }
      setIncomingRematch(null)
      setDuel({ duel_id: incomingRematch.duel_id, status: response.data?.status || 'matched' })
      enterFoundState()
      hapticFeedback('success')
      scheduleLoadAfterFound(incomingRematch.duel_id)
    } catch (err) {
      setError(String(err?.message || 'Не удалось принять реванш'))
      hapticFeedback('error')
      loadIncomingRematch()
    } finally {
      setLoading(false)
    }
  }, [incomingRematch, enterFoundState, loadIncomingRematch, scheduleLoadAfterFound])

  const declineIncomingRematch = useCallback(async () => {
    if (!incomingRematch?.duel_id) return
    setLoading(true)
    setError(null)
    try {
      await api.declineRematch(incomingRematch.duel_id)
      setIncomingRematch(null)
      hapticFeedback('warning')
    } catch (err) {
      setError(String(err?.message || 'Не удалось отклонить реванш'))
      hapticFeedback('error')
    } finally {
      setLoading(false)
    }
  }, [incomingRematch])

  useEffect(() => {
    loadProfile()
  }, [])

  useEffect(() => {
    if (state !== STATES.MENU) return

    loadIncomingRematch()
    const timer = setInterval(() => {
      loadIncomingRematch()
    }, 5000)

    return () => clearInterval(timer)
  }, [state, loadIncomingRematch])

  useEffect(() => {
    if (autoModeHandledRef.current) return
    if (state !== STATES.MENU) return

    const mode = (searchParams.get('mode') || '').toLowerCase()
    if (mode === 'random') {
      autoModeHandledRef.current = true
      startSearch()
      return
    }
    if (mode === 'invite' || mode === 'friend') {
      autoModeHandledRef.current = true
      inviteFriend()
      return
    }
    if (mode === 'enter_code' || mode === 'join') {
      autoModeHandledRef.current = true
      const code = (searchParams.get('code') || '').replace(/\D+/g, '').slice(0, 5)
      setInviteCode(code)
      setState(STATES.ENTER_CODE)
      return
    }
  }, [searchParams, state])

  useEffect(() => {
    if (autoJoinAttemptedRef.current) return
    if (state !== STATES.MENU && state !== STATES.ENTER_CODE) return

    const queryCodeRaw = (searchParams.get('code') || '').trim()
    const startapp = (searchParams.get('startapp') || '').trim()
    const startParam = (window.Telegram?.WebApp?.initDataUnsafe?.start_param || '').trim()

    let codeFromDeepLinkRaw = ''
    if (startapp.toLowerCase().startsWith('duel_')) {
      codeFromDeepLinkRaw = startapp.slice(5)
    } else if (startParam.toLowerCase().startsWith('duel_')) {
      codeFromDeepLinkRaw = startParam.slice(5)
    }

    const inviteCodeFromLink = (queryCodeRaw || codeFromDeepLinkRaw).replace(/\D+/g, '').slice(0, 5)
    if (!/^\d{5}$/.test(inviteCodeFromLink)) return

    autoJoinAttemptedRef.current = true
    setInviteCode(inviteCodeFromLink)
    setState(STATES.ENTER_CODE)
  }, [searchParams, state])

  useEffect(() => {
    return () => {
      clearNextRoundTimers()
      clearFoundLoadTimer()
    }
  }, [clearNextRoundTimers, clearFoundLoadTimer])

  useEffect(() => {
    if (state !== STATES.FOUND) {
      clearFoundLoadTimer()
    }
  }, [state, clearFoundLoadTimer])

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
    setLastResult({
      is_correct: false,
      timeout: true,
      my_timed_out: true,
      my_reason: 'timeout',
      my_time_taken: null
    })
    setOpponentAnswer({ answered: false, correct: null, timedOut: null, timeTaken: null, reason: null })
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
            correct: response.data.opponent_correct,
            timedOut: Boolean(response.data.opponent_timed_out),
            timeTaken: Number.isFinite(response.data.opponent_time_taken) ? Number(response.data.opponent_time_taken) : null,
            reason: response.data.opponent_reason ?? null
          })
          if (response.data.correct_answer_id) {
            setCorrectAnswer(response.data.correct_answer_id)
          }
          enterRoundResultState(duel.duel_id)
        }
      }
    } catch (err) {
      console.error('Failed to submit timeout:', err)
    }
  }, [duel, round, enterRoundResultState])

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

  const applyDuelSnapshot = (data, options = {}) => {
    const { mergeDuel = false, duelIdOverride = null } = options
    const duelIdForFollowup = Number(duelIdOverride || data?.duel_id || duel?.duel_id || 0)
    const currentState = duelStateRef.current
    const serverRoundStatus = data.round_status || null

    if (mergeDuel) {
      setDuel((prev) => ({ ...(prev || {}), ...data }))
    } else {
      setDuel(data)
    }

    if (typeof data.ghost_pool_available === 'boolean') {
      ghostPoolAvailableRef.current = data.ghost_pool_available
    }
    if (data.ghost_fallback_attempted && !data.ghost_fallback_assigned && !data.is_ghost_match) {
      setGhostFallbackPending(true)
    }

    setRoundStatus(serverRoundStatus)

    if (Number.isFinite(data.current_round)) {
      setRound(Number(data.current_round))
    }
    if (Number.isFinite(data.total_rounds)) {
      setTotalRounds(Number(data.total_rounds))
    }
    if (data.last_closed_round) {
      addRoundToHistory(data.last_closed_round)
    }

    const isInitiator = Boolean(data.is_initiator)
    setScore({
      player: isInitiator ? data.initiator_score : data.opponent_score,
      opponent: isInitiator ? data.opponent_score : data.initiator_score
    })

    const serverMyAnswerId = Number(serverRoundStatus?.my_answer_id || 0)
    if (serverRoundStatus?.my_answered) {
      hasAnsweredRef.current = true
      if (serverMyAnswerId > 0 && selectedAnswerRef.current === null) {
        setSelectedAnswer(serverMyAnswerId)
      }
    }

    if (data.status === 'cancelled' && data.cancelled_without_match) {
      resetToLobbyByCancellation(data.cancel_reason)
      return
    }

    const derivedState = deriveDuelViewState(data, {
      currentState,
      selectedAnswer: selectedAnswerRef.current,
      answeredRoundId: answeredRoundId.current,
    })

    if (derivedState === STATES.FINISHED) {
      clearNextRoundTimers()
      queueFinishedRewardsIfNeeded(data)
      setState(STATES.FINISHED)
      return
    }

    if (currentState === STATES.FOUND && Date.now() < foundScreenUntilRef.current) {
      return
    }
    if (currentState === STATES.SHOWING_RESULT && Date.now() < roundResultUntilRef.current) {
      return
    }

    if (
      (currentState === STATES.WAITING_OPPONENT || currentState === STATES.INVITE || currentState === STATES.SEARCHING) &&
      (data.opponent || data.status === 'matched' || data.status === 'in_progress')
    ) {
      if (data.opponent) {
        setOpponent({
          name: data.opponent.name || 'Соперник',
          rating: data.opponent.rating ?? 0,
          photo_url: data.opponent.photo_url
        })
      }
      enterFoundState()
      hapticFeedback('success')
      if (duelIdForFollowup > 0) {
        scheduleLoadAfterFound(duelIdForFollowup)
      }
      return
    }

    if (currentState === STATES.WAITING_OPPONENT_ANSWER) {
      const currentRoundId = data.round_status?.round_id
      const lastClosedRound = data.last_closed_round

      const roundClosed = (
        (answeredRoundId.current && lastClosedRound &&
          lastClosedRound.round_id === answeredRoundId.current) ||
        data.round_status?.opponent_answered ||
        (answeredRoundId.current && currentRoundId &&
          currentRoundId !== answeredRoundId.current)
      )

      if (roundClosed) {
        const opponentCorrect = lastClosedRound?.round_id === answeredRoundId.current
          ? lastClosedRound.opponent_correct
          : data.round_status?.opponent_correct
        const correctAnswerId = lastClosedRound?.round_id === answeredRoundId.current
          ? lastClosedRound.correct_answer_id
          : data.round_status?.correct_answer_id
        const myTimeTaken = lastClosedRound?.round_id === answeredRoundId.current
          ? (Number.isFinite(lastClosedRound.my_time_taken) ? Number(lastClosedRound.my_time_taken) : null)
          : (Number.isFinite(data.round_status?.my_time_taken) ? Number(data.round_status.my_time_taken) : null)
        const opponentTimeTaken = lastClosedRound?.round_id === answeredRoundId.current
          ? (Number.isFinite(lastClosedRound.opponent_time_taken) ? Number(lastClosedRound.opponent_time_taken) : null)
          : (Number.isFinite(data.round_status?.opponent_time_taken) ? Number(data.round_status.opponent_time_taken) : null)
        const myTimedOut = lastClosedRound?.round_id === answeredRoundId.current
          ? Boolean(lastClosedRound.my_timed_out)
          : Boolean(data.round_status?.my_timed_out)
        const myReason = lastClosedRound?.round_id === answeredRoundId.current
          ? (lastClosedRound.my_reason ?? null)
          : (data.round_status?.my_reason ?? null)

        setLastResult((prev) => ({
          ...(prev || {}),
          my_time_taken: myTimeTaken,
          my_timed_out: myTimedOut,
          my_reason: myReason,
          speed_delta_seconds: myTimeTaken !== null && opponentTimeTaken !== null
            ? (opponentTimeTaken - myTimeTaken)
            : null
        }))

        setOpponentAnswer({
          answered: true,
          correct: opponentCorrect ?? false,
          timedOut: lastClosedRound?.round_id === answeredRoundId.current
            ? Boolean(lastClosedRound.opponent_timed_out)
            : Boolean(data.round_status?.opponent_timed_out),
          timeTaken: lastClosedRound?.round_id === answeredRoundId.current
            ? (Number.isFinite(lastClosedRound.opponent_time_taken) ? Number(lastClosedRound.opponent_time_taken) : null)
            : (Number.isFinite(data.round_status?.opponent_time_taken) ? Number(data.round_status?.opponent_time_taken) : null),
          reason: lastClosedRound?.round_id === answeredRoundId.current
            ? (lastClosedRound.opponent_reason ?? null)
            : (data.round_status?.opponent_reason ?? null)
        })

        if (correctAnswerId) {
          setCorrectAnswer((prev) => prev || correctAnswerId)
        }

        if (lastClosedRound?.round_id === answeredRoundId.current) {
          addRoundToHistory(lastClosedRound)
        }

        enterRoundResultState(duelIdForFollowup)
        hapticFeedback(opponentCorrect ? 'warning' : 'success')
      } else if (derivedState !== currentState && derivedState !== STATES.FOUND) {
        setState(derivedState)
      }
      return
    }

    if (data.question) {
      if (currentQuestionId.current !== data.question.id) {
        clearNextRoundTimers()
        currentQuestionId.current = data.question.id
        answeredRoundId.current = null

        setQuestion(data.question)
        setRoundStatus(serverRoundStatus)
        setSelectedAnswer(null)
        hasAnsweredRef.current = false
        setCorrectAnswer(null)
        setOpponentAnswer(null)
        setLastResult(null)
        setHiddenAnswers([])

        const timeLimit = data.round_status?.time_limit || 30
        if (data.round_status?.question_sent_at) {
          const sentAt = new Date(data.round_status.question_sent_at)
          const elapsed = Math.floor((Date.now() - sentAt.getTime()) / 1000)
          const newTimeLeft = Math.max(0, timeLimit - (isNaN(elapsed) ? 0 : elapsed))
          setTimeLeft(newTimeLeft)
        } else {
          setTimeLeft(timeLimit)
        }

        setState(derivedState)
      } else if (derivedState !== duelStateRef.current && derivedState !== STATES.FOUND) {
        setState(derivedState)
      }
    } else if (derivedState !== currentState && derivedState !== STATES.FOUND) {
      setState(derivedState)
    }
  }
  applyDuelSnapshotRef.current = applyDuelSnapshot

  const { syncDuelSnapshot } = useDuelSnapshotSync({
    getDuel: api.getDuel,
    applySnapshotRef: applyDuelSnapshotRef,
    onSyncErrorRef: handleUnauthorizedErrorRef,
  })

  const checkDuelStatus = async (duelId) => {
    await syncDuelSnapshot(duelId, { mergeDuel: true, source: 'check duel status' })
  }
  checkDuelStatusRef.current = checkDuelStatus

  useDuelPolling({
    duelId: duel?.duel_id,
    state,
    wsConnected,
    roundStatus,
    checkDuelStatusRef,
    loadDuelRef,
    setError,
  })

  useDuelSearchTimeout({
    state,
    duelId: duel?.duel_id,
    isRematchWaiting: Boolean(duel?.is_rematch || duel?.mode === 'rematch'),
    duelStateRef,
    checkDuelStatusRef,
    setSearchTimeLeft,
    setGhostFallbackPending,
    ghostPoolAvailableRef,
    setError,
    setDuel,
    navigateHome: () => navigate('/'),
    cancelDuel: (duelId) => api.cancelDuel(duelId),
    cancelRematch: (duelId) => api.cancelRematch(duelId),
    onErrorFeedback: () => hapticFeedback('error'),
    onWarningFeedback: () => hapticFeedback('warning'),
  })

  const loadDuel = async (duelId) => {
    await syncDuelSnapshot(duelId, { mergeDuel: false, source: 'load duel' })
  }

  loadDuelRef.current = loadDuel

  const startSearch = async () => {
    setState(STATES.SEARCHING)
    setLoading(true)
    setError(null)
    setIncomingRematch(null)
    finishedRewardsShownRef.current = new Set()
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
          enterFoundState()
          hapticFeedback('success')
          scheduleLoadAfterFound(data.duel_id)
        } else {
          setState(STATES.WAITING_OPPONENT)
        }
      } else {
        setError(response.error || 'Не удалось создать дуэль')
        navigate('/')
      }
    } catch (err) {
      console.error('Failed to create duel:', err)
      if (String(err?.message || '').toLowerCase().includes('билет')) {
        showNoTicketsModal()
      }
      setError(`Ошибка: ${err.message}`)
      navigate('/')
    } finally {
      setLoading(false)
    }
  }

  // Создать дуэль для приглашения друга
  const inviteFriend = async () => {
    setLoading(true)
    setError(null)
    setIncomingRematch(null)
    finishedRewardsShownRef.current = new Set()
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
        navigate('/')
      }
    } catch (err) {
      console.error('Failed to create invite duel:', err)
      if (String(err?.message || '').toLowerCase().includes('билет')) {
        showNoTicketsModal()
      }
      setError(`Ошибка: ${err.message}`)
      navigate('/')
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

  const joinByCodeValue = async (value) => {
    const normalizedCode = value.replace(/\D+/g, '').slice(0, 5)
    if (!/^\d{5}$/.test(normalizedCode)) {
      setError('Код должен содержать 5 цифр')
      hapticFeedback('error')
      return
    }

    setLoading(true)
    setError(null)
    finishedRewardsShownRef.current = new Set()
    hapticFeedback('medium')
    
    try {
      const response = await api.joinDuel(normalizedCode)
      
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
        
        enterFoundState()
        hapticFeedback('success')
        scheduleLoadAfterFound(data.duel_id)
      } else {
        setError(response.error || 'Дуэль не найдена')
        hapticFeedback('error')
      }
    } catch (err) {
      console.error('Failed to join duel:', err)
      if (String(err?.message || '').toLowerCase().includes('билет')) {
        showNoTicketsModal()
      }
      setError(`Ошибка: ${err.message}`)
      hapticFeedback('error')
    } finally {
      setLoading(false)
    }
  }

  // Присоединиться по коду
  const joinByCode = async () => {
    await joinByCodeValue(inviteCode)
  }

  const sendRematchInvite = async () => {
    if (!duel) return
    const targetId = duel.is_initiator ? duel.opponent?.id : duel.initiator?.id
    if (!targetId) {
      setError('Не удалось определить соперника для реванша')
      return
    }

    setLoading(true)
    setError(null)
    setState(STATES.SEARCHING)
    try {
      const response = await api.createDuel('rematch', {
        target_user_id: targetId,
        source_duel_id: duel.duel_id,
      })

      if (!response.success) {
        throw new Error(response.error || 'Не удалось отправить приглашение на реванш')
      }

      setDuel(response.data)
      setState(STATES.WAITING_OPPONENT)
      hapticFeedback('medium')
    } catch (err) {
      if (String(err?.message || '').toLowerCase().includes('билет')) {
        showNoTicketsModal()
      }
      setError(String(err?.message || 'Не удалось отправить приглашение на реванш'))
      setState(STATES.MENU)
      hapticFeedback('error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (state !== STATES.ENTER_CODE) return
    if (!autoJoinAttemptedRef.current) return
    if (!/^\d{5}$/.test(inviteCode)) return
    if (loading) return

    joinByCodeValue(inviteCode)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state, inviteCode])

  const handleAnswerSelect = async (answerId) => {
    if (selectedAnswer !== null || !duel || !question) return
    
    setSelectedAnswer(answerId)
    hasAnsweredRef.current = true
    setState(STATES.WAITING_OPPONENT_ANSWER)
    setOpponentAnswer({
      answered: false,
      correct: null,
      timedOut: null,
      timeTaken: null,
      reason: null
    })
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

        if (Number(data.my_answer_id || 0) > 0) {
          setSelectedAnswer(Number(data.my_answer_id))
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
            correct: data.opponent_correct,
            timedOut: Boolean(data.opponent_timed_out),
            timeTaken: Number.isFinite(data.opponent_time_taken) ? Number(data.opponent_time_taken) : null,
            reason: data.opponent_reason ?? null
          })
        
        enterRoundResultState(duel.duel_id)
        }
      }
    } catch (err) {
      console.error('Failed to submit answer:', err)
      setError(`Ошибка отправки ответа: ${err.message}`)
      if (duel?.duel_id) {
        checkDuelStatus(duel.duel_id)
      }
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
      <DuelMenuView
        navigate={navigate}
        error={error}
        incomingRematch={incomingRematch}
        loading={loading}
        startSearch={startSearch}
        inviteFriend={inviteFriend}
        onEnterCode={() => setState(STATES.ENTER_CODE)}
        acceptIncomingRematch={acceptIncomingRematch}
        declineIncomingRematch={declineIncomingRematch}
        renderRealtimeBadge={renderRealtimeBadge}
      />
    )
  }

  // Экран ввода кода
  if (state === STATES.ENTER_CODE) {
    return (
      <DuelEnterCodeView
        inviteCode={inviteCode}
        setInviteCode={setInviteCode}
        joinByCode={joinByCode}
        loading={loading}
        navigate={navigate}
      />
    )
  }

  // Экраны ожидания/поиска
  if (state === STATES.SEARCHING || state === STATES.WAITING_OPPONENT || state === STATES.INVITE) {
    return (
      <DuelWaitingView
        state={state}
        duel={duel}
        searchTimeLeft={searchTimeLeft}
        ghostFallbackPending={ghostFallbackPending}
        shareInvite={shareInvite}
        onCancel={async () => {
          const isRematchWaiting = Boolean(state !== STATES.INVITE && (duel?.is_rematch || duel?.mode === 'rematch'))
          if (duel) {
            if (isRematchWaiting) {
              await api.cancelRematch(duel.duel_id).catch(() => api.cancelDuel(duel.duel_id).catch(console.error))
            } else {
              await api.cancelDuel(duel.duel_id).catch(console.error)
            }
          }
          if (isRematchWaiting) {
            setError('Приглашение на реванш отменено.')
          }
          clearFoundLoadTimer()
          setDuel(null)
          navigate('/')
        }}
      />
    )
  }

  // Соперник найден
  if (state === STATES.FOUND) {
    return (
      <DuelFoundView
        user={user}
        myRating={myRating}
        opponent={opponent}
        duel={duel}
      />
    )
  }
  
  // ИГРОВОЙ ПРОЦЕСС
  if ((state === STATES.PLAYING || state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.SHOWING_RESULT) && question) {
    return (
      <DuelPlayingView
        state={state}
        question={question}
        lastResult={lastResult}
        selectedAnswer={selectedAnswer}
        correctAnswer={correctAnswer}
        opponentAnswer={opponentAnswer}
        roundStatus={roundStatus}
        timeLeft={timeLeft}
        round={round}
        totalRounds={totalRounds}
        score={score}
        user={user}
        opponent={opponent}
        duel={duel}
        hiddenAnswers={hiddenAnswers}
        hintUsed={hintUsed}
        coins={coins}
        nextRoundCountdown={nextRoundCountdown}
        renderRealtimeBadge={renderRealtimeBadge}
        onAnswerSelect={handleAnswerSelect}
        onUseHint={useHint}
      />
    )
  }
  
  // FINISH SCREEN
  if (state === STATES.FINISHED) {
    return (
      <DuelFinishedView
        score={score}
        totalRounds={totalRounds}
        duel={duel}
        user={user}
        opponent={opponent}
        roundHistory={roundHistory}
        onRematch={() => {
          setRoundHistory([])
          sendRematchInvite()
        }}
        onGoHome={() => {
          setRoundHistory([])
          setState(STATES.MENU)
          setDuel(null)
          setScore({ player: 0, opponent: 0 })
          navigate('/')
        }}
      />
    )
  }

  return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
          <div className="spinner" />
      </div>
  )
}

export default DuelPage
