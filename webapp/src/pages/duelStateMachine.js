import { STATES } from './duel/constants.js'

export const DUEL_STATES = STATES

/**
 * Централизованный derive состояния UI дуэли из серверного payload.
 */
export function deriveDuelViewState(data, context = {}) {
  const currentState = context.currentState || DUEL_STATES.MENU
  const selectedAnswer = context.selectedAnswer
  const answeredRoundId = context.answeredRoundId

  if (!data) return currentState

  const status = String(data.status || '')
  if (status === 'finished' || status === 'cancelled') {
    return DUEL_STATES.FINISHED
  }

  const hasQuestion = Boolean(data.question?.id)
  const roundStatus = data.round_status || {}

  if (!hasQuestion) {
    if (status === 'waiting') {
      if (currentState === DUEL_STATES.INVITE) return DUEL_STATES.INVITE
      return DUEL_STATES.WAITING_OPPONENT
    }

    if (status === 'matched') {
      return DUEL_STATES.FOUND
    }

    if (status === 'in_progress') {
      if (
        currentState === DUEL_STATES.SHOWING_RESULT ||
        currentState === DUEL_STATES.WAITING_OPPONENT_ANSWER ||
        currentState === DUEL_STATES.PLAYING ||
        currentState === DUEL_STATES.FOUND
      ) {
        return currentState
      }
      return DUEL_STATES.WAITING_OPPONENT
    }

    return currentState
  }

  const myAnswered = Boolean(roundStatus.my_answered) || (
    selectedAnswer !== null &&
    answeredRoundId !== null &&
    roundStatus.round_id === answeredRoundId
  )
  const opponentAnswered = Boolean(roundStatus.opponent_answered)

  const optimisticSameRound = (
    selectedAnswer !== null &&
    answeredRoundId !== null &&
    roundStatus.round_id === answeredRoundId
  )

  if (!myAnswered && currentState === DUEL_STATES.WAITING_OPPONENT_ANSWER && optimisticSameRound) {
    return DUEL_STATES.WAITING_OPPONENT_ANSWER
  }

  if (myAnswered && opponentAnswered) return DUEL_STATES.SHOWING_RESULT
  if (myAnswered) return DUEL_STATES.WAITING_OPPONENT_ANSWER
  return DUEL_STATES.PLAYING
}
