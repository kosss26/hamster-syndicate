import { motion, AnimatePresence } from 'framer-motion'
import AvatarWithFrame from '../../components/AvatarWithFrame'
import { STATES } from './constants'

export function DuelPlayingView({
  state,
  question,
  lastResult,
  selectedAnswer,
  correctAnswer,
  opponentAnswer,
  roundStatus,
  timeLeft,
  round,
  totalRounds,
  score,
  user,
  opponent,
  duel,
  hiddenAnswers,
  hintUsed,
  coins,
  nextRoundCountdown,
  renderRealtimeBadge,
  onAnswerSelect,
  onUseHint,
}) {
  const isCorrect = lastResult?.is_correct === true
  const isTimeout = Boolean(lastResult?.timeout || lastResult?.my_timed_out || lastResult?.my_reason === 'timeout')
  const isAnswerLocked = selectedAnswer !== null
  const isReveal = state === STATES.SHOWING_RESULT
  const correctAnswerText = question.answers.find((answer) => answer.id === correctAnswer)?.text ?? null
  const myTimeTaken = Number.isFinite(lastResult?.my_time_taken) ? Number(lastResult?.my_time_taken) : null
  const opponentTimeTaken = Number.isFinite(opponentAnswer?.timeTaken) ? Number(opponentAnswer?.timeTaken) : null
  const speedDelta = Number.isFinite(lastResult?.speed_delta_seconds)
    ? Number(lastResult.speed_delta_seconds)
    : (myTimeTaken !== null && opponentTimeTaken !== null ? opponentTimeTaken - myTimeTaken : null)

  const myResultLabel = isCorrect ? 'Верно' : isTimeout ? 'Таймаут' : 'Ошибка'
  const myResultClass = isCorrect ? 'text-emerald-300' : 'text-red-300'
  const opponentResultLabel = opponentAnswer?.answered
    ? (opponentAnswer.timedOut ? 'Таймаут' : opponentAnswer.correct ? 'Верно' : 'Ошибка')
    : 'Ожидаем'
  const opponentResultClass = opponentAnswer?.answered
    ? (opponentAnswer.timedOut ? 'text-red-200' : opponentAnswer.correct ? 'text-emerald-300' : 'text-red-300')
    : 'text-amber-200'
  const speedLabel = speedDelta === null
    ? null
    : speedDelta > 0
      ? `Вы быстрее на ${speedDelta.toFixed(1)}с`
      : speedDelta < 0
        ? `Соперник быстрее на ${Math.abs(speedDelta).toFixed(1)}с`
        : 'Одинаковая скорость ответа'

  const opponentLiveStatus = state === STATES.WAITING_OPPONENT_ANSWER
    ? 'Соперник думает'
    : opponentAnswer?.answered
      ? (opponentAnswer.timedOut ? 'Таймаут соперника' : opponentAnswer.correct ? 'Ответил верно' : 'Ответил неверно')
      : roundStatus?.opponent_answered
        ? (roundStatus?.opponent_timed_out ? 'Таймаут соперника' : roundStatus?.opponent_correct ? 'Ответил верно' : 'Ответил неверно')
        : 'Соперник думает'

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col">
      <div className="noise-overlay" />

      <div className="relative z-20 pt-4 px-4 pb-2 bg-gradient-to-b from-black/40 to-transparent">
        <div className="flex justify-between items-center max-w-md mx-auto w-full">
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

          <div className="relative flex flex-col items-center">
            <div className="relative w-14 h-14 flex items-center justify-center">
              <svg className="w-full h-full -rotate-90 absolute inset-0">
                <circle cx="28" cy="28" r="26" stroke="rgba(255,255,255,0.1)" strokeWidth="4" fill="none" />
                <motion.circle
                  cx="28"
                  cy="28"
                  r="26"
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
            <div className="mt-2">{renderRealtimeBadge(true)}</div>
            {isAnswerLocked && (
              <div className={`mt-2 px-2 py-1 rounded-full border text-[10px] font-semibold ${
                state === STATES.WAITING_OPPONENT_ANSWER
                  ? 'border-amber-300/40 bg-amber-500/10 text-amber-100'
                  : 'border-emerald-300/40 bg-emerald-500/10 text-emerald-100'
              }`}>
                {state === STATES.WAITING_OPPONENT_ANSWER ? 'Ответ зафиксирован' : 'Раунд закрыт'}
              </div>
            )}
          </div>

          <div className="flex items-center gap-3 flex-row-reverse">
            <AvatarWithFrame
              photoUrl={opponent?.photo_url}
              name={opponent?.name || 'Соперник'}
              frameKey={opponent?.equipped_frame}
              size={48}
              showGlow={false}
            />
            <div className="flex flex-col items-end">
              <span className="text-2xl font-black text-white">{score.opponent}</span>
              <span className="text-[10px] text-white/60 uppercase tracking-wide">{opponentLiveStatus}</span>
            </div>
          </div>
        </div>
      </div>

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

      <AnimatePresence>
        {state === STATES.WAITING_OPPONENT_ANSWER && (
          <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            className="relative z-20 mx-auto mb-2 bg-black/70 backdrop-blur-md px-5 py-2 rounded-full border border-white/10 text-white/90 text-sm font-medium flex items-center gap-3"
          >
            <div className="w-2 h-2 rounded-full bg-yellow-400 animate-pulse" />
            Ожидаем соперника...
          </motion.div>
        )}
      </AnimatePresence>

      <div className="p-4 relative z-10 pb-8">
        <div className="grid grid-cols-2 gap-3 max-w-md mx-auto mb-4">
          {question.answers
            .filter(answer => !hiddenAnswers.includes(answer.id))
            .map((answer, idx) => {
              const isSelected = selectedAnswer === answer.id
              const isCorrectAnswer = correctAnswer === answer.id

              let statusClass = 'bg-white/5 border-white/10 text-white'
              if (isSelected) statusClass = 'bg-indigo-500/20 border-indigo-300/60 text-white shadow-[0_0_18px_rgba(99,102,241,0.3)]'
              if (isCorrectAnswer) statusClass = 'bg-emerald-500/20 border-emerald-400 text-emerald-100'
              if (isSelected && lastResult && !lastResult.is_correct) statusClass = 'bg-red-500/20 border-red-400 text-red-100'
              if (selectedAnswer !== null && !isSelected && !isCorrectAnswer) statusClass = 'opacity-70 bg-white/5 border-white/10 text-white/70'

              return (
                <motion.button
                  key={answer.id}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: idx * 0.1 }}
                  onClick={() => onAnswerSelect(answer.id)}
                  disabled={selectedAnswer !== null}
                  className={`relative min-h-[104px] rounded-2xl p-4 flex flex-col items-center justify-center text-center text-sm font-semibold border backdrop-blur-md transition-all active:scale-95 ${statusClass}`}
                >
                  {answer.text}

                  {isCorrectAnswer && (
                    <div className="absolute top-2 right-2 text-emerald-300">✓</div>
                  )}
                  {isSelected && lastResult && !lastResult.is_correct && (
                    <div className="absolute top-2 right-2 text-red-300">✗</div>
                  )}
                </motion.button>
              )
            })}
        </div>

        {state === STATES.PLAYING && !selectedAnswer && !hintUsed && (
          <div className="flex justify-center">
            <button
              onClick={onUseHint}
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

      <AnimatePresence>
        {isReveal && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 z-30 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          >
            <motion.div
              initial={{ scale: 0.5, y: 50 }}
              animate={{ scale: 1, y: 0 }}
              className="bg-[#0F172A] border border-white/10 rounded-3xl p-6 text-left shadow-2xl w-[92%] max-w-sm"
            >
              <div className="flex items-center justify-between mb-4">
                <div className="text-white font-bold text-lg">Итог раунда</div>
                <div className="text-xs text-white/50">R{round}/{totalRounds}</div>
              </div>
              <div className="grid grid-cols-2 gap-3 mb-4">
                <div className="rounded-xl border border-white/10 bg-white/5 p-3">
                  <div className="text-[11px] text-white/60 uppercase tracking-wide mb-1">Вы</div>
                  <div className={`text-sm font-semibold ${myResultClass}`}>{myResultLabel}</div>
                  <div className="text-[11px] text-white/50 mt-1">
                    {myTimeTaken !== null ? `${myTimeTaken.toFixed(1)}с` : '—'}
                  </div>
                </div>
                <div className="rounded-xl border border-white/10 bg-white/5 p-3">
                  <div className="text-[11px] text-white/60 uppercase tracking-wide mb-1">Соперник</div>
                  <div className={`text-sm font-semibold ${opponentResultClass}`}>{opponentResultLabel}</div>
                  <div className="text-[11px] text-white/50 mt-1">
                    {opponentTimeTaken !== null ? `${opponentTimeTaken.toFixed(1)}с` : '—'}
                  </div>
                </div>
              </div>
              <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 mb-4">
                <div className="text-[11px] text-emerald-100/80 uppercase tracking-wide mb-1">Правильный ответ</div>
                <div className="text-sm text-emerald-100 font-medium">{correctAnswerText ?? 'Загружаем...'}</div>
              </div>
              {speedLabel && (
                <div className="rounded-xl border border-indigo-400/30 bg-indigo-500/10 p-3 mb-4">
                  <div className="text-xs text-indigo-100/90">{speedLabel}</div>
                </div>
              )}
              <div className="text-xs text-white/55">
                {nextRoundCountdown ? `Следующий раунд через ${nextRoundCountdown}с` : 'Переход к следующему раунду...'}
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export function DuelFinishedView({ score, totalRounds, duel, user, opponent, roundHistory, onRematch, onGoHome }) {
  const isWin = score.player > score.opponent
  const isDraw = score.player === score.opponent
  const ratingChangeVal = duel?.rating_change ?? (isWin ? 10 : isDraw ? 0 : -10)
  const ratingChange = ratingChangeVal > 0 ? `+${ratingChangeVal}` : `${ratingChangeVal}`
  const ratingClass = ratingChangeVal > 0 ? 'text-emerald-300' : ratingChangeVal < 0 ? 'text-red-300' : 'text-white'
  const roundPills = Array.from({ length: totalRounds }, (_, index) => {
    const roundNumber = index + 1
    const historyItem = roundHistory.find((item) => item.round_number === roundNumber)

    if (!historyItem) {
      return { roundNumber, state: 'pending' }
    }

    if (historyItem.my_correct === historyItem.opponent_correct) {
      return { roundNumber, state: 'draw' }
    }

    return {
      roundNumber,
      state: historyItem.my_correct ? 'win' : 'lose'
    }
  })

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6 text-center">
      <div className="noise-overlay" />

      {isWin && (
        <>
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-game-primary/20 blur-[100px] rounded-full" />
        </>
      )}

      <motion.div
        initial={{ scale: 0.8, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className="relative z-10 bg-black/40 backdrop-blur-xl border border-white/10 rounded-[40px] p-7 w-full max-w-sm"
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
            <AvatarWithFrame
              photoUrl={opponent?.photo_url}
              name={opponent?.name || 'Соперник'}
              frameKey={opponent?.equipped_frame}
              size={64}
              showGlow={false}
              className="mx-auto"
            />
            <div className="text-3xl font-bold text-white/60 mt-2">{score.opponent}</div>
          </div>
        </div>

        <div className="bg-white/5 rounded-2xl p-4 mb-6">
          <div className="text-sm text-white/40 uppercase font-bold tracking-wider mb-1">Рейтинг</div>
          <div className={`text-2xl font-bold ${ratingClass}`}>
            {ratingChange} <span className="text-sm font-normal text-white/40">MMR</span>
          </div>
        </div>

        <div className="bg-white/5 rounded-2xl p-4 mb-6 text-left">
          <div className="text-sm text-white/40 uppercase font-bold tracking-wider mb-3 text-center">Ход дуэли</div>
          <div className="grid grid-cols-5 gap-2">
            {roundPills.map((pill) => (
              <div
                key={`round-pill-${pill.roundNumber}`}
                className={`h-7 rounded-lg border flex items-center justify-center text-[11px] font-semibold ${
                  pill.state === 'win'
                    ? 'bg-emerald-500/20 border-emerald-400/50 text-emerald-200'
                    : pill.state === 'lose'
                      ? 'bg-red-500/20 border-red-400/50 text-red-200'
                      : pill.state === 'draw'
                        ? 'bg-amber-500/15 border-amber-300/40 text-amber-100'
                        : 'bg-white/5 border-white/10 text-white/40'
                }`}
              >
                {pill.state === 'win' ? '✓' : pill.state === 'lose' ? '✕' : pill.state === 'draw' ? '•' : '·'}
              </div>
            ))}
          </div>
        </div>

        <button
          onClick={onRematch}
          className="w-full py-4 bg-white rounded-2xl text-black font-bold text-lg mb-3 hover:bg-white/90 transition-colors"
        >
          Реванш
        </button>

        <button
          onClick={onGoHome}
          className="w-full py-3 border border-white/20 rounded-2xl text-white font-semibold text-base hover:bg-white/10 transition-colors"
        >
          На главную
        </button>
      </motion.div>
    </div>
  )
}
