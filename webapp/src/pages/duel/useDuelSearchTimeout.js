import { useEffect, useRef } from 'react'
import { STATES } from './constants'

export function useDuelSearchTimeout({
  state,
  duelId,
  isRematchWaiting,
  duelStateRef,
  checkDuelStatusRef,
  setSearchTimeLeft,
  setGhostFallbackPending,
  ghostPoolAvailableRef,
  setError,
  setDuel,
  navigateHome,
  cancelDuel,
  cancelRematch,
  onErrorFeedback,
  onWarningFeedback,
}) {
  const searchTimerRef = useRef(null)
  const fallbackTimerRef = useRef(null)

  useEffect(() => {
    const clearTimers = () => {
      if (searchTimerRef.current) {
        clearInterval(searchTimerRef.current)
        searchTimerRef.current = null
      }
      if (fallbackTimerRef.current) {
        clearTimeout(fallbackTimerRef.current)
        fallbackTimerRef.current = null
      }
    }

    if (state !== STATES.WAITING_OPPONENT) {
      clearTimers()
      setGhostFallbackPending(false)
      ghostPoolAvailableRef.current = null
      return () => {
        clearTimers()
      }
    }

    let cancelled = false
    const isStillWaiting = () => !cancelled && duelStateRef.current === STATES.WAITING_OPPONENT

    setSearchTimeLeft(30)
    setGhostFallbackPending(false)

    searchTimerRef.current = setInterval(() => {
      setSearchTimeLeft((prev) => {
        if (prev > 1) {
          return prev - 1
        }

        clearTimers()

        const currentDuelId = Number(duelId || 0)
        if (!currentDuelId) {
          setError(isRematchWaiting ? 'Соперник не принял реванш.' : 'Соперник не найден. Попробуйте ещё раз.')
          navigateHome()
          onErrorFeedback()
          return 0
        }

        if (isRematchWaiting) {
          cancelRematch(currentDuelId).catch(() => cancelDuel(currentDuelId).catch(console.error))
          setError('Соперник не принял реванш за 30 секунд.')
          setDuel(null)
          navigateHome()
          onWarningFeedback()
          return 0
        }

        setGhostFallbackPending(true)

        ;(async () => {
          await checkDuelStatusRef.current?.(currentDuelId)

          fallbackTimerRef.current = setTimeout(() => {
            if (!isStillWaiting()) {
              return
            }

            Promise.resolve(checkDuelStatusRef.current?.(currentDuelId)).finally(() => {
              if (!isStillWaiting()) {
                return
              }

              cancelDuel(currentDuelId).catch(console.error)
              setError(
                ghostPoolAvailableRef.current === false
                  ? 'Соперник не найден: пока нет записей призраков. Сыграйте несколько реальных дуэлей.'
                  : 'Соперник не найден. Попробуйте ещё раз.'
              )
              navigateHome()
              onErrorFeedback()
            })
          }, 3500)
        })().catch(() => {
          if (!isStillWaiting()) {
            return
          }

          cancelDuel(currentDuelId).catch(console.error)
          setError('Соперник не найден. Попробуйте ещё раз.')
          navigateHome()
          onErrorFeedback()
        })

        return 0
      })
    }, 1000)

    return () => {
      cancelled = true
      clearTimers()
    }
  }, [
    state,
    duelId,
    isRematchWaiting,
    duelStateRef,
    checkDuelStatusRef,
    setSearchTimeLeft,
    setGhostFallbackPending,
    ghostPoolAvailableRef,
    setError,
    setDuel,
    navigateHome,
    cancelDuel,
    cancelRematch,
    onErrorFeedback,
    onWarningFeedback,
  ])
}
