import { useState, useEffect, useCallback, useRef } from 'react'
import { useNavigate, useSearchParams, useParams } from 'react-router-dom'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api, { getWsBaseUrl } from '../api/client'
import { deriveDuelViewState } from './duelStateMachine'
import { addNotificationItems } from '../utils/notificationInbox'
import { WS_STATUS, FOUND_SCREEN_MIN_MS, ROUND_RESULT_MIN_MS, STATES } from './duel/constants'
import { DuelMenuView, DuelEnterCodeView, DuelWaitingView, DuelFoundView } from './duel/DuelLobbyViews'
import { DuelPlayingView, DuelFinishedView } from './duel/DuelGameViews'

function DuelPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { id: duelIdParam } = useParams()
  const { user, webApp } = useTelegram()
  const wsConfigured = Boolean(getWsBaseUrl())
  
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
  const [wsConnected, setWsConnected] = useState(false)
  const [wsConnectionState, setWsConnectionState] = useState(WS_STATUS.OFFLINE)
  const [wsRetrying, setWsRetrying] = useState(false)
  
  const currentQuestionId = useRef(null)
  const timerRef = useRef(null)
  const answeredRoundId = useRef(null)
  const searchTimerRef = useRef(null)
  const hasAnsweredRef = useRef(false) // Для проверки в таймере
  const wsRef = useRef(null)
  const wsReconnectRef = useRef(null)
  const wsReconnectAttemptsRef = useRef(0)
  const wsPingIntervalRef = useRef(null)
  const wsStopReconnectRef = useRef(false)
  const duelStateRef = useRef(STATES.MENU)
  const nextRoundTimerRef = useRef(null)
  const nextRoundIntervalRef = useRef(null)
  const loadDuelRef = useRef(null)
  const autoJoinAttemptedRef = useRef(false)
  const autoModeHandledRef = useRef(false)
  const seenAchievementRewardsRef = useRef(new Set())
  const foundScreenUntilRef = useRef(0)
  const roundResultUntilRef = useRef(0)
  const finishedRewardsShownRef = useRef(new Set())
  const ghostPoolAvailableRef = useRef(null)
  const waitWatchdogRef = useRef({ roundId: null, since: 0, lastSyncAt: 0 })

  const enterFoundState = useCallback(() => {
    foundScreenUntilRef.current = Date.now() + FOUND_SCREEN_MIN_MS
    setState(STATES.FOUND)
  }, [])

  const queueRewardNotifications = useCallback((payload) => {
    if (!payload) return

    const next = []
    const achievementUnlocks = Array.isArray(payload.achievement_unlocks) ? payload.achievement_unlocks : []
    const collectionDrops = Array.isArray(payload.collection_drops) ? payload.collection_drops : []

    achievementUnlocks.forEach((unlock, index) => {
      const achievement = unlock?.achievement
      if (!achievement?.title) return
      const dedupeKey = `achievement_${achievement.id || achievement.key || achievement.title}`
      if (seenAchievementRewardsRef.current.has(dedupeKey)) {
        return
      }
      seenAchievementRewardsRef.current.add(dedupeKey)

      next.push({
        id: `ach_${Date.now()}_${index}_${achievement.id || achievement.key || 'x'}`,
        type: 'achievement',
        icon: achievement.icon || '🏆',
        title: achievement.title,
        subtitle: achievement.description || '',
        rarity: achievement.rarity || 'common',
      })
    })

    collectionDrops.forEach((drop, index) => {
      const item = drop?.item
      if (!item?.name) return
      const isDuplicate = Boolean(drop?.is_duplicate)
      const coinBonus = isDuplicate
        ? Number(drop?.duplicate_compensation?.coins || 0)
        : Number(drop?.new_card_bonus?.coins || 0)
      const subtitle = isDuplicate
        ? `Дубликат обменян: +${coinBonus} монет`
        : `Редкость: ${item.rarity_label || drop.rarity_label || 'Обычная'}${coinBonus > 0 ? ` · +${coinBonus} монет` : ''}`

      next.push({
        id: `card_${Date.now()}_${index}_${item.id || item.key || 'x'}`,
        type: 'card',
        icon: isDuplicate ? '♻️' : '🃏',
        title: isDuplicate ? `Дубликат: ${item.name}` : `Карточка: ${item.name}`,
        subtitle,
        rarity: item.rarity || 'common',
      })
    })

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

  useEffect(() => {
    duelStateRef.current = state
  }, [state])

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

  const closeDuelSocket = useCallback((preventReconnect = true, resetAttempts = preventReconnect) => {
    if (wsReconnectRef.current) {
      clearTimeout(wsReconnectRef.current)
      wsReconnectRef.current = null
    }

    wsStopReconnectRef.current = preventReconnect
    if (resetAttempts) {
      wsReconnectAttemptsRef.current = 0
    }
    setWsConnected(false)
    setWsConnectionState(WS_STATUS.OFFLINE)
    setWsRetrying(false)

    if (wsPingIntervalRef.current) {
      clearInterval(wsPingIntervalRef.current)
      wsPingIntervalRef.current = null
    }

    if (wsRef.current) {
      wsRef.current.onopen = null
      wsRef.current.onclose = null
      wsRef.current.onmessage = null
      wsRef.current.onerror = null
      wsRef.current.close()
      wsRef.current = null
    }
  }, [])

  const scheduleSocketReconnect = useCallback((duelId) => {
    if (duelStateRef.current === STATES.FINISHED || !duelId || wsStopReconnectRef.current || !wsConfigured) return

    if (wsReconnectRef.current) {
      clearTimeout(wsReconnectRef.current)
      wsReconnectRef.current = null
    }

    const maxAttempts = 8
    const attempt = wsReconnectAttemptsRef.current + 1

    if (attempt > maxAttempts) {
      console.warn('WS reconnect limit reached; falling back to polling')
      wsStopReconnectRef.current = true
      return
    }

    wsReconnectAttemptsRef.current = attempt
    const baseDelayMs = Math.min(1500 * (2 ** (attempt - 1)), 15000)
    const jitterMs = Math.floor(Math.random() * 500)
    const delayMs = baseDelayMs + jitterMs

    setWsConnectionState(WS_STATUS.CONNECTING)
    wsReconnectRef.current = setTimeout(() => {
      connectDuelSocket(duelId)
    }, delayMs)
  }, [wsConfigured])

  const connectDuelSocket = useCallback(async (duelId) => {
    const wsBase = getWsBaseUrl()
    if (!duelId) return

    if (!wsBase) {
      setWsConnected(false)
      setWsConnectionState(WS_STATUS.DISABLED)
      setWsRetrying(false)
      return
    }

    try {
      wsStopReconnectRef.current = false
      setWsConnectionState(WS_STATUS.CONNECTING)

      const ticketResponse = await api.getDuelWsTicket(duelId)
      if (!ticketResponse.success || !ticketResponse.data?.ticket) {
        scheduleSocketReconnect(duelId)
        return
      }

      closeDuelSocket(false, false)

      const ws = new WebSocket(`${wsBase}?ticket=${encodeURIComponent(ticketResponse.data.ticket)}`)
      wsRef.current = ws

      ws.onopen = () => {
        wsReconnectAttemptsRef.current = 0
        setWsConnected(true)
        setWsConnectionState(WS_STATUS.CONNECTED)
        setWsRetrying(false)
        ws.send(JSON.stringify({ type: 'ping' }))

        if (wsPingIntervalRef.current) {
          clearInterval(wsPingIntervalRef.current)
        }

        wsPingIntervalRef.current = setInterval(() => {
          if (ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ type: 'ping' }))
          }
        }, 20000)

        // Восстановление состояния сразу после reconnect без ожидания следующего события/поллинга.
        checkDuelStatus(duelId)
      }

      ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data)
          if (message.type === 'duel_update' && message.payload?.duel_id) {
            checkDuelStatus(message.payload.duel_id)
          }

          if (message.type === 'duel_closed' && message.duel_id) {
            closeDuelSocket()
            checkDuelStatus(message.duel_id)
          }

          if (message.type === 'error') {
            const wsErrorMessage = message.message || 'unknown_error'

            if (wsErrorMessage === 'duel_closed' || wsErrorMessage === 'duel_access_denied') {
              wsStopReconnectRef.current = true
              closeDuelSocket()
              checkDuelStatus(duelId)
              return
            }

            if (wsErrorMessage === 'invalid_ticket') {
              wsStopReconnectRef.current = true
              closeDuelSocket()
              return
            }
          }
        } catch (e) {
          console.error('Failed to parse ws message', e)
        }
      }

      ws.onclose = () => {
        setWsConnected(false)
        setWsConnectionState(WS_STATUS.OFFLINE)

        if (wsPingIntervalRef.current) {
          clearInterval(wsPingIntervalRef.current)
          wsPingIntervalRef.current = null
        }

        scheduleSocketReconnect(duelId)
      }

      ws.onerror = () => {
        setWsConnected(false)
        setWsConnectionState(WS_STATUS.OFFLINE)

        if (wsPingIntervalRef.current) {
          clearInterval(wsPingIntervalRef.current)
          wsPingIntervalRef.current = null
        }

        ws.close()
      }
    } catch (e) {
      console.error('Failed to establish duel websocket', e)
      setWsConnected(false)
      setWsConnectionState(WS_STATUS.OFFLINE)

      const message = String(e?.message || '')
      if (message.includes('Дуэль уже завершена') || message.includes('Доступ запрещён') || message.includes('Не авторизован')) {
        wsStopReconnectRef.current = true
        return
      }

      scheduleSocketReconnect(duelId)
    }
  }, [closeDuelSocket, scheduleSocketReconnect])

  // Загрузка профиля для получения монет

  const handleRealtimeRetry = () => {
    if (!wsConfigured || !duel?.duel_id || wsConnectionState === WS_STATUS.CONNECTING || wsRetrying) return

    setWsRetrying(true)
    setWsConnectionState(WS_STATUS.CONNECTING)
    connectDuelSocket(duel.duel_id)
  }

  const renderRealtimeBadge = (compact = false) => {
    const isOnline = wsConnected
    const isConnecting = wsConnectionState === WS_STATUS.CONNECTING
    const isDisabled = wsConnectionState === WS_STATUS.DISABLED || !wsConfigured
    const attemptLabel = isConnecting && wsReconnectAttemptsRef.current > 0 ? ` · #${wsReconnectAttemptsRef.current}` : ''
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
      setTimeout(() => {
        if (typeof loadDuelRef.current === 'function') {
          loadDuelRef.current(incomingRematch.duel_id)
        }
      }, FOUND_SCREEN_MIN_MS)
    } catch (err) {
      setError(String(err?.message || 'Не удалось принять реванш'))
      hapticFeedback('error')
      loadIncomingRematch()
    } finally {
      setLoading(false)
    }
  }, [incomingRematch, enterFoundState, loadIncomingRematch])

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
    }
  }, [clearNextRoundTimers])

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

  useEffect(() => {
    if (!duel || state === STATES.FINISHED) return

    const interval = (state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.WAITING_OPPONENT)
      ? 900
      : 1500

    const checkInterval = setInterval(() => {
      checkDuelStatus(duel.duel_id)
    }, interval)

    return () => clearInterval(checkInterval)
  }, [duel?.duel_id, state])

  useEffect(() => {
    if (state !== STATES.WAITING_OPPONENT_ANSWER || !duel?.duel_id) {
      waitWatchdogRef.current = { roundId: null, since: 0, lastSyncAt: 0 }
      return
    }

    const trackedRoundId = Number(roundStatus?.round_id || 0)
    if (!trackedRoundId) return

    const now = Date.now()
    if (waitWatchdogRef.current.roundId !== trackedRoundId) {
      waitWatchdogRef.current = { roundId: trackedRoundId, since: now, lastSyncAt: 0 }
    }

    const watchdog = setInterval(() => {
      const snapshot = waitWatchdogRef.current
      if (snapshot.roundId !== trackedRoundId || snapshot.since <= 0) return

      const elapsedMs = Date.now() - snapshot.since
      const canForceSync = Date.now() - snapshot.lastSyncAt >= 12000

      // Если застряли в ожидании слишком долго, принудительно синхронизируемся.
      if (elapsedMs >= 45000 && canForceSync) {
        waitWatchdogRef.current.lastSyncAt = Date.now()
        if (typeof loadDuelRef.current === 'function') {
          loadDuelRef.current(duel.duel_id)
        }
      }

      // Мягкий guardrail для пользователя.
      if (elapsedMs >= 90000) {
        setError('Синхронизация раунда потеряна. Попробуй переподключиться.')
      }
    }, 3000)

    return () => clearInterval(watchdog)
  }, [state, duel?.duel_id, roundStatus?.round_id])


  useEffect(() => {
    if (!duel?.duel_id) return

    if (!wsConfigured) {
      setWsConnectionState(WS_STATUS.DISABLED)
      return
    }

    connectDuelSocket(duel.duel_id)

    return () => {
      closeDuelSocket()
    }
  }, [duel?.duel_id, wsConfigured, connectDuelSocket, closeDuelSocket])

  // Таймер поиска соперника - 30 секунд максимум
  useEffect(() => {
    if (state !== STATES.WAITING_OPPONENT) {
      if (searchTimerRef.current) {
        clearInterval(searchTimerRef.current)
        searchTimerRef.current = null
      }
      setGhostFallbackPending(false)
      ghostPoolAvailableRef.current = null
      return
    }

    setSearchTimeLeft(30)
    setGhostFallbackPending(false)
    const isRematchWaiting = Boolean(duel?.is_rematch || duel?.mode === 'rematch')
    
    searchTimerRef.current = setInterval(() => {
      setSearchTimeLeft(prev => {
        if (prev <= 1) {
          // Время вышло: сначала проверяем fallback на призрака.
          clearInterval(searchTimerRef.current)
          searchTimerRef.current = null

          const currentDuelId = duel?.duel_id
          if (!currentDuelId) {
            setError(isRematchWaiting ? 'Соперник не принял реванш.' : 'Соперник не найден. Попробуйте ещё раз.')
            navigate('/')
            hapticFeedback('error')
            return 0
          }

          if (isRematchWaiting) {
            api.cancelRematch(currentDuelId).catch(() => api.cancelDuel(currentDuelId).catch(console.error))
            setError('Соперник не принял реванш за 30 секунд.')
            setDuel(null)
            navigate('/')
            hapticFeedback('warning')
            return 0
          }

          setGhostFallbackPending(true)

          ;(async () => {
            await checkDuelStatus(currentDuelId)

            setTimeout(() => {
              if (duelStateRef.current !== STATES.WAITING_OPPONENT) {
                return
              }
              checkDuelStatus(currentDuelId).finally(() => {
                if (duelStateRef.current !== STATES.WAITING_OPPONENT) {
                  return
                }
                api.cancelDuel(currentDuelId).catch(console.error)
                setError(ghostPoolAvailableRef.current === false
                  ? 'Соперник не найден: пока нет записей призраков. Сыграйте несколько реальных дуэлей.'
                  : 'Соперник не найден. Попробуйте ещё раз.')
                navigate('/')
                hapticFeedback('error')
              })
            }, 3500)
          })().catch(() => {
            if (duelStateRef.current !== STATES.WAITING_OPPONENT) {
              return
            }
            api.cancelDuel(currentDuelId).catch(console.error)
            setError('Соперник не найден. Попробуйте ещё раз.')
            navigate('/')
            hapticFeedback('error')
          })

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
  }, [state, duel?.duel_id, duel?.is_rematch, duel?.mode])

  const checkDuelStatus = async (duelId) => {
    try {
      const response = await api.getDuel(duelId)
      
      if (response.success) {
        const data = response.data
        setDuel(prev => ({ ...(prev || {}), ...data }))
        if (typeof data.ghost_pool_available === 'boolean') {
          ghostPoolAvailableRef.current = data.ghost_pool_available
        }
        if (data.ghost_fallback_attempted && !data.ghost_fallback_assigned && !data.is_ghost_match) {
          setGhostFallbackPending(true)
        }
        const currentState = duelStateRef.current
        const serverRoundStatus = data.round_status || null
        setRoundStatus(serverRoundStatus)

        const serverMyAnswerId = Number(serverRoundStatus?.my_answer_id || 0)
        if (serverRoundStatus?.my_answered) {
          hasAnsweredRef.current = true
          if (serverMyAnswerId > 0 && selectedAnswer === null) {
            setSelectedAnswer(serverMyAnswerId)
          }
        }
        
        const isInitiator = data.is_initiator
        setScore({
          player: isInitiator ? data.initiator_score : data.opponent_score,
          opponent: isInitiator ? data.opponent_score : data.initiator_score
        })

        if (data.status === 'cancelled' && data.cancelled_without_match) {
          resetToLobbyByCancellation(data.cancel_reason)
          return
        }
        
        const derivedState = deriveDuelViewState(data, {
          currentState,
          selectedAnswer,
          answeredRoundId: answeredRoundId.current,
        })

        if (derivedState === STATES.FINISHED) {
          clearNextRoundTimers()
          queueFinishedRewardsIfNeeded(data)
          setState(STATES.FINISHED)
        } else if (currentState === STATES.FOUND && Date.now() < foundScreenUntilRef.current) {
          return
        } else if (currentState === STATES.SHOWING_RESULT && Date.now() < roundResultUntilRef.current) {
          return
        } else if (
          (currentState === STATES.WAITING_OPPONENT || currentState === STATES.INVITE || currentState === STATES.SEARCHING) &&
          (data.opponent || data.status === 'matched' || data.status === 'in_progress')
        ) {
          // Соперник найден/дуэль стартовала. Важно для режима INVITE:
          // иначе создатель комнаты может запоздать к началу раунда.
          if (data.opponent) {
            setOpponent({
              name: data.opponent.name || 'Соперник',
              rating: data.opponent.rating ?? 0,
              photo_url: data.opponent.photo_url
            })
          }
          enterFoundState()
          hapticFeedback('success')
          setTimeout(() => {
            loadDuel(duelId)
          }, FOUND_SCREEN_MIN_MS)
        } else if (currentState === STATES.WAITING_OPPONENT_ANSWER) {
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
            
            if (correctAnswerId && !correctAnswer) {
              setCorrectAnswer(correctAnswerId)
            }

            if (lastClosedRound?.round_id === answeredRoundId.current) {
              addRoundToHistory(lastClosedRound)
            }
            
            enterRoundResultState(duelId)
            hapticFeedback(opponentCorrect ? 'warning' : 'success')
          }
        } else if (derivedState !== currentState && derivedState !== STATES.FOUND) {
          setState(derivedState)
        }
      }
    } catch (err) {
      console.error('Failed to check duel status:', err)
      handleUnauthorizedError(err)
    }
  }

  const loadDuel = async (duelId) => {
    try {
      const response = await api.getDuel(duelId)
      
      if (response.success) {
        const data = response.data
        setDuel(data)
        const serverRoundStatus = data.round_status || null
        setRoundStatus(serverRoundStatus)
        setRound(data.current_round)
        setTotalRounds(data.total_rounds)

        if (data.last_closed_round) {
          addRoundToHistory(data.last_closed_round)
        }
        
        const isInitiator = data.is_initiator
        setScore({
          player: isInitiator ? data.initiator_score : data.opponent_score,
          opponent: isInitiator ? data.opponent_score : data.initiator_score
        })

        if (data.status === 'cancelled' && data.cancelled_without_match) {
          resetToLobbyByCancellation(data.cancel_reason)
          return
        }

        const derivedState = deriveDuelViewState(data, {
          currentState: duelStateRef.current,
          selectedAnswer,
          answeredRoundId: answeredRoundId.current,
        })

        if (derivedState === STATES.FINISHED) {
          clearNextRoundTimers()
          queueFinishedRewardsIfNeeded(data)
          setState(STATES.FINISHED)
        } else if (duelStateRef.current === STATES.FOUND && Date.now() < foundScreenUntilRef.current) {
          return
        } else if (duelStateRef.current === STATES.SHOWING_RESULT && Date.now() < roundResultUntilRef.current) {
          return
        } else if (data.question) {
          // Новый вопрос — сбрасываем состояние и запускаем таймер
          if (currentQuestionId.current !== data.question.id) {
            clearNextRoundTimers()
            currentQuestionId.current = data.question.id
            answeredRoundId.current = null
            
            // Ответы уже перемешаны на сервере (одинаково для обоих игроков)
            setQuestion(data.question)
            setRoundStatus(data.round_status || null)
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
            
            // При новом вопросе используем derive-логику, чтобы корректно восстановиться после reconnect.
            setState(derivedState)
          } else if (derivedState !== duelStateRef.current && derivedState !== STATES.FOUND) {
            // Восстановление экрана без жесткого ресета UI при reconnect.
            setState(derivedState)
          }

          const serverMyAnswerId = Number(serverRoundStatus?.my_answer_id || 0)
          if (serverRoundStatus?.my_answered) {
            hasAnsweredRef.current = true
            if (serverMyAnswerId > 0 && selectedAnswer === null) {
              setSelectedAnswer(serverMyAnswerId)
            }
          }
        } else if (derivedState !== duelStateRef.current && derivedState !== STATES.FOUND) {
          setState(derivedState)
        }
      }
    } catch (err) {
      console.error('Failed to load duel:', err)
      handleUnauthorizedError(err)
    }
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
          
          setTimeout(() => {
            loadDuel(data.duel_id)
          }, FOUND_SCREEN_MIN_MS)
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
        
        setTimeout(() => {
          loadDuel(data.duel_id)
        }, FOUND_SCREEN_MIN_MS)
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
