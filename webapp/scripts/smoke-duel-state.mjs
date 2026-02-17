import { DUEL_STATES, deriveDuelViewState } from '../src/pages/duelStateMachine.js'

const failures = []

function assertEqual(actual, expected, message) {
  if (actual !== expected) {
    failures.push(`${message}: expected=${expected}, actual=${actual}`)
  } else {
    console.log(`[OK] ${message}`)
  }
}

function runTransitionSequence(initialState, steps) {
  let state = initialState
  for (const step of steps) {
    state = deriveDuelViewState(step.data, {
      currentState: state,
      selectedAnswer: step.selectedAnswer ?? null,
      answeredRoundId: step.answeredRoundId ?? null,
    })
  }
  return state
}

assertEqual(
  deriveDuelViewState(null, { currentState: DUEL_STATES.SEARCHING }),
  DUEL_STATES.SEARCHING,
  'No payload keeps current state'
)

assertEqual(
  deriveDuelViewState({ status: 'waiting' }, { currentState: DUEL_STATES.MENU }),
  DUEL_STATES.WAITING_OPPONENT,
  'Waiting without question from menu -> waiting_opponent'
)

assertEqual(
  deriveDuelViewState({ status: 'waiting' }, { currentState: DUEL_STATES.INVITE }),
  DUEL_STATES.INVITE,
  'Waiting without question from invite -> invite'
)

assertEqual(
  deriveDuelViewState({ status: 'matched' }, { currentState: DUEL_STATES.SEARCHING }),
  DUEL_STATES.FOUND,
  'Matched without question -> found'
)

assertEqual(
  deriveDuelViewState({ status: 'in_progress' }, { currentState: DUEL_STATES.SHOWING_RESULT }),
  DUEL_STATES.SHOWING_RESULT,
  'In progress without question keeps showing_result when already there'
)

assertEqual(
  deriveDuelViewState(
    { status: 'in_progress', question: { id: 1 }, round_status: { my_answered: false, opponent_answered: false } },
    { currentState: DUEL_STATES.FOUND, selectedAnswer: null, answeredRoundId: null }
  ),
  DUEL_STATES.PLAYING,
  'Question shown and no answers -> playing'
)

assertEqual(
  deriveDuelViewState(
    { status: 'in_progress', question: { id: 1 }, round_status: { my_answered: true, opponent_answered: false, round_id: 77 } },
    { currentState: DUEL_STATES.PLAYING, selectedAnswer: 3, answeredRoundId: 77 }
  ),
  DUEL_STATES.WAITING_OPPONENT_ANSWER,
  'My answer only -> waiting_opponent_answer'
)

assertEqual(
  deriveDuelViewState(
    { status: 'in_progress', question: { id: 1 }, round_status: { my_answered: true, opponent_answered: true, round_id: 78 } },
    { currentState: DUEL_STATES.WAITING_OPPONENT_ANSWER, selectedAnswer: 2, answeredRoundId: 78 }
  ),
  DUEL_STATES.SHOWING_RESULT,
  'Both answers -> showing_result'
)

assertEqual(
  deriveDuelViewState(
    { status: 'in_progress', question: { id: 1 }, round_status: { my_answered: false, opponent_answered: false, round_id: 79 } },
    { currentState: DUEL_STATES.WAITING_OPPONENT_ANSWER, selectedAnswer: 4, answeredRoundId: 79 }
  ),
  DUEL_STATES.WAITING_OPPONENT_ANSWER,
  'Optimistic local answered state is preserved'
)

assertEqual(
  deriveDuelViewState(
    { status: 'in_progress', question: { id: 2 }, round_status: { my_answered: false, opponent_answered: false, round_id: 80 } },
    { currentState: DUEL_STATES.WAITING_OPPONENT_ANSWER, selectedAnswer: 4, answeredRoundId: 79 }
  ),
  DUEL_STATES.PLAYING,
  'Stale optimistic answer from previous round does not lock waiting state'
)

assertEqual(
  deriveDuelViewState({ status: 'finished' }, { currentState: DUEL_STATES.PLAYING }),
  DUEL_STATES.FINISHED,
  'Finished status -> finished state'
)

assertEqual(
  deriveDuelViewState({ status: 'cancelled' }, { currentState: DUEL_STATES.WAITING_OPPONENT }),
  DUEL_STATES.FINISHED,
  'Cancelled status -> finished state'
)

assertEqual(
  runTransitionSequence(DUEL_STATES.SEARCHING, [
    { data: { status: 'matched' } },
    { data: { status: 'in_progress', question: { id: 101 }, round_status: { my_answered: false, opponent_answered: false, round_id: 501 } } },
    { data: { status: 'in_progress', question: { id: 101 }, round_status: { my_answered: true, opponent_answered: false, round_id: 501 } }, selectedAnswer: 11, answeredRoundId: 501 },
    { data: { status: 'in_progress', question: { id: 102 }, round_status: { my_answered: false, opponent_answered: false, round_id: 502 } }, selectedAnswer: 11, answeredRoundId: 501 },
  ]),
  DUEL_STATES.PLAYING,
  'Reconnect/late snapshot sequence keeps state on new round instead of stale waiting'
)

if (failures.length > 0) {
  console.error(`\nState machine smoke failed with ${failures.length} issue(s):`)
  for (const failure of failures) {
    console.error(`- ${failure}`)
  }
  process.exit(1)
}

console.log('\nState machine smoke passed.')
