import { useCallback, useEffect, useRef, useState } from 'react'
import { WS_STATUS, STATES } from './constants'

export function useDuelRealtime({
  duelId,
  wsConfigured,
  wsBaseUrl,
  duelStateRef,
  checkDuelStatusRef,
  getDuelWsTicket,
}) {
  const [wsConnected, setWsConnected] = useState(false)
  const [wsConnectionState, setWsConnectionState] = useState(WS_STATUS.OFFLINE)
  const [wsRetrying, setWsRetrying] = useState(false)
  const [wsReconnectAttempt, setWsReconnectAttempt] = useState(0)

  const wsRef = useRef(null)
  const wsReconnectRef = useRef(null)
  const wsReconnectAttemptsRef = useRef(0)
  const wsPingIntervalRef = useRef(null)
  const wsStopReconnectRef = useRef(false)
  const connectDuelSocketRef = useRef(null)

  const closeDuelSocket = useCallback((preventReconnect = true, resetAttempts = preventReconnect) => {
    if (wsReconnectRef.current) {
      clearTimeout(wsReconnectRef.current)
      wsReconnectRef.current = null
    }

    wsStopReconnectRef.current = preventReconnect
    if (resetAttempts) {
      wsReconnectAttemptsRef.current = 0
      setWsReconnectAttempt(0)
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

  const scheduleSocketReconnect = useCallback((nextDuelId) => {
    if (duelStateRef.current === STATES.FINISHED || !nextDuelId || wsStopReconnectRef.current || !wsConfigured) {
      return
    }

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
    setWsReconnectAttempt(attempt)
    const baseDelayMs = Math.min(1500 * (2 ** (attempt - 1)), 15000)
    const jitterMs = Math.floor(Math.random() * 500)
    const delayMs = baseDelayMs + jitterMs

    setWsConnectionState(WS_STATUS.CONNECTING)
    wsReconnectRef.current = setTimeout(() => {
      if (typeof connectDuelSocketRef.current === 'function') {
        connectDuelSocketRef.current(nextDuelId)
      }
    }, delayMs)
  }, [duelStateRef, wsConfigured])

  const connectDuelSocket = useCallback(async (nextDuelId) => {
    if (!nextDuelId) return

    if (!wsConfigured || !wsBaseUrl) {
      setWsConnected(false)
      setWsConnectionState(WS_STATUS.DISABLED)
      setWsRetrying(false)
      return
    }

    try {
      wsStopReconnectRef.current = false
      setWsConnectionState(WS_STATUS.CONNECTING)

      const ticketResponse = await getDuelWsTicket(nextDuelId)
      if (!ticketResponse.success || !ticketResponse.data?.ticket) {
        scheduleSocketReconnect(nextDuelId)
        return
      }

      closeDuelSocket(false, false)

      const ws = new WebSocket(`${wsBaseUrl}?ticket=${encodeURIComponent(ticketResponse.data.ticket)}`)
      wsRef.current = ws

      ws.onopen = () => {
        wsReconnectAttemptsRef.current = 0
        setWsReconnectAttempt(0)
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

        checkDuelStatusRef.current?.(nextDuelId)
      }

      ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data)
          if (message.type === 'duel_update' && message.payload?.duel_id) {
            checkDuelStatusRef.current?.(message.payload.duel_id)
          }

          if (message.type === 'duel_closed' && message.duel_id) {
            closeDuelSocket()
            checkDuelStatusRef.current?.(message.duel_id)
          }

          if (message.type === 'error') {
            const wsErrorMessage = message.message || 'unknown_error'
            if (wsErrorMessage === 'duel_closed' || wsErrorMessage === 'duel_access_denied') {
              wsStopReconnectRef.current = true
              closeDuelSocket()
              checkDuelStatusRef.current?.(nextDuelId)
              return
            }

            if (wsErrorMessage === 'invalid_ticket') {
              wsStopReconnectRef.current = true
              closeDuelSocket()
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

        scheduleSocketReconnect(nextDuelId)
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
      if (
        message.includes('Дуэль уже завершена') ||
        message.includes('Доступ запрещён') ||
        message.includes('Не авторизован')
      ) {
        wsStopReconnectRef.current = true
        return
      }

      scheduleSocketReconnect(nextDuelId)
    }
  }, [wsConfigured, wsBaseUrl, getDuelWsTicket, scheduleSocketReconnect, closeDuelSocket, checkDuelStatusRef])

  connectDuelSocketRef.current = connectDuelSocket

  const handleRealtimeRetry = useCallback(() => {
    if (!wsConfigured || !duelId || wsConnectionState === WS_STATUS.CONNECTING || wsRetrying) {
      return
    }

    setWsRetrying(true)
    setWsConnectionState(WS_STATUS.CONNECTING)
    connectDuelSocket(duelId)
  }, [wsConfigured, duelId, wsConnectionState, wsRetrying, connectDuelSocket])

  useEffect(() => {
    if (!duelId) return

    if (!wsConfigured || !wsBaseUrl) {
      setWsConnectionState(WS_STATUS.DISABLED)
      return
    }

    connectDuelSocket(duelId)

    return () => {
      closeDuelSocket()
    }
  }, [duelId, wsConfigured, wsBaseUrl, connectDuelSocket, closeDuelSocket])

  return {
    wsConnected,
    wsConnectionState,
    wsRetrying,
    wsReconnectAttempt,
    closeDuelSocket,
    handleRealtimeRetry,
  }
}
