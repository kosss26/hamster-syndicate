export const DUEL_STATES = {
  MENU: 'menu',
  SEARCHING: 'searching',
  INVITE: 'invite',
  ENTER_CODE: 'enter_code',
  FOUND: 'found',
  PLAYING: 'playing',
  WAITING_OPPONENT: 'waiting_opponent',
  WAITING_OPPONENT_ANSWER: 'waiting_opponent_answer',
  SHOWING_RESULT: 'showing_result',
  FINISHED: 'finished',
}

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
      if (currentState === DUEL_STATES.SHOWING_RESULT || currentState === DUEL_STATES.WAITING_OPPONENT_ANSWER) {
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

  if (myAnswered && opponentAnswered) return DUEL_STATES.SHOWING_RESULT
  if (myAnswered) return DUEL_STATES.WAITING_OPPONENT_ANSWER
  return DUEL_STATES.PLAYING
}

