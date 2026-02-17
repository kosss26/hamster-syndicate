import { useEffect, useRef } from 'react'
import { STATES } from './constants'

export function useDuelPolling({
  duelId,
  state,
  wsConnected,
  roundStatus,
  checkDuelStatusRef,
  loadDuelRef,
  setError,
}) {
  const waitWatchdogRef = useRef({ roundId: null, since: 0, lastSyncAt: 0 })

  useEffect(() => {
    if (!duelId || state === STATES.FINISHED) return

    const interval = wsConnected
      ? (
        (state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.WAITING_OPPONENT)
          ? 1200
          : 2500
      )
      : (
        (state === STATES.WAITING_OPPONENT_ANSWER || state === STATES.WAITING_OPPONENT)
          ? 900
          : 1500
      )

    const checkInterval = setInterval(() => {
      checkDuelStatusRef.current?.(duelId)
    }, interval)

    return () => clearInterval(checkInterval)
  }, [duelId, state, wsConnected, checkDuelStatusRef])

  useEffect(() => {
    if (state !== STATES.WAITING_OPPONENT_ANSWER || !duelId) {
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

      if (elapsedMs >= 45000 && canForceSync) {
        waitWatchdogRef.current.lastSyncAt = Date.now()
        if (typeof loadDuelRef.current === 'function') {
          loadDuelRef.current(duelId)
        }
      }

      if (elapsedMs >= 90000) {
        setError('Синхронизация раунда потеряна. Попробуй переподключиться.')
      }
    }, 3000)

    return () => clearInterval(watchdog)
  }, [state, duelId, roundStatus?.round_id, loadDuelRef, setError])
}
