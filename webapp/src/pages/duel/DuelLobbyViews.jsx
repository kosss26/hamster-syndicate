import { motion } from 'framer-motion'
import AvatarWithFrame from '../../components/AvatarWithFrame'
import ReferralIcon from '../../components/ReferralIcon'
import { STATES } from './constants'

export function DuelMenuView({
  navigate,
  error,
  incomingRematch,
  loading,
  startSearch,
  inviteFriend,
  onEnterCode,
  acceptIncomingRematch,
  declineIncomingRematch,
  renderRealtimeBadge,
}) {
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
        <div className="mt-3">{renderRealtimeBadge()}</div>
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

      {incomingRematch && (
        <motion.div
          initial={{ opacity: 0, y: -8 }}
          animate={{ opacity: 1, y: 0 }}
          className="relative z-10 bg-cyan-500/10 border border-cyan-400/30 rounded-2xl p-4 mb-4 backdrop-blur-md"
        >
          <p className="text-cyan-100 font-semibold text-sm mb-1">Входящий реванш</p>
          <p className="text-white/75 text-xs mb-3">
            {incomingRematch?.initiator?.name || 'Соперник'} зовёт сыграть ещё раз
            {Number.isFinite(incomingRematch?.expires_in) ? ` · ${Math.max(0, Number(incomingRematch.expires_in))}с` : ''}
          </p>
          <div className="grid grid-cols-2 gap-2">
            <button
              onClick={acceptIncomingRematch}
              disabled={loading}
              className="py-2.5 rounded-xl bg-emerald-400 text-slate-900 font-bold disabled:opacity-60"
            >
              Принять
            </button>
            <button
              onClick={declineIncomingRematch}
              disabled={loading}
              className="py-2.5 rounded-xl border border-white/20 text-white font-semibold disabled:opacity-60"
            >
              Отказаться
            </button>
          </div>
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
              <span role="img" aria-label="Случайный бой">🎲</span>
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
              <ReferralIcon className="w-9 h-9" />
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
          onClick={onEnterCode}
          className="w-full py-4 text-center text-white/40 font-medium hover:text-white transition-colors"
        >
          Ввести код приглашения
        </motion.button>
      </div>
    </div>
  )
}

export function DuelEnterCodeView({ inviteCode, setInviteCode, joinByCode, loading, navigate }) {
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
          type="tel"
          value={inviteCode}
          onChange={(e) => setInviteCode(e.target.value.replace(/\D+/g, '').slice(0, 5))}
          placeholder="12345"
          maxLength={5}
          inputMode="numeric"
          className="w-full bg-white/5 border border-white/10 rounded-2xl py-6 text-center text-4xl font-mono font-bold text-white placeholder-white/20 outline-none focus:border-game-primary transition-colors mb-6 tracking-widest"
          autoFocus
        />

        <button
          onClick={joinByCode}
          disabled={loading || !/^\d{5}$/.test(inviteCode)}
          className="w-full py-4 bg-game-primary rounded-xl font-bold text-white shadow-lg shadow-game-primary/30 disabled:opacity-50 disabled:shadow-none transition-all active:scale-95 mb-4"
        >
          {loading ? 'Поиск...' : 'Присоединиться'}
        </button>

        <button
          onClick={() => navigate('/')}
          className="w-full py-3 text-white/40 font-medium"
        >
          Отмена
        </button>
      </motion.div>
    </div>
  )
}

export function DuelWaitingView({
  state,
  duel,
  searchTimeLeft,
  ghostFallbackPending,
  shareInvite,
  onCancel,
}) {
  const isInvite = state === STATES.INVITE
  const isSearching = state === STATES.SEARCHING
  const isRematchWaiting = Boolean(!isInvite && (duel?.is_rematch || duel?.mode === 'rematch'))

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6 text-center">
      <div className="noise-overlay" />

      <div className="relative z-10 w-full max-w-sm">
        <div className="relative w-64 h-64 mx-auto mb-12 flex items-center justify-center">
          <motion.div
            animate={{ scale: [1, 2], opacity: [0.5, 0] }}
            transition={{ duration: 2, repeat: Infinity, ease: 'easeOut' }}
            className="absolute inset-0 border border-game-primary/30 rounded-full"
          />
          <motion.div
            animate={{ scale: [1, 2], opacity: [0.5, 0] }}
            transition={{ duration: 2, repeat: Infinity, ease: 'easeOut', delay: 0.5 }}
            className="absolute inset-0 border border-game-primary/30 rounded-full"
          />

          <div className="w-32 h-32 bg-white/5 backdrop-blur-xl rounded-full border border-white/10 flex items-center justify-center relative overflow-hidden">
            <motion.div
              animate={{ rotate: 360 }}
              transition={{ duration: 3, repeat: Infinity, ease: 'linear' }}
              className="absolute inset-0 bg-gradient-to-t from-game-primary/20 to-transparent w-full h-1/2 origin-bottom"
            />
            <div className="text-4xl relative z-10">
              {isInvite ? '📨' : isRematchWaiting ? '♻️' : '🎲'}
            </div>
          </div>
        </div>

        <h2 className="text-2xl font-bold text-white mb-2">
          {isInvite ? 'Ожидание друга' : isRematchWaiting ? 'Ожидание реванша' : isSearching ? 'Поиск оппонента' : 'Ожидание...'}
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
            {isRematchWaiting
              ? searchTimeLeft > 0 ? `00:${searchTimeLeft.toString().padStart(2, '0')}` : 'Истекло'
              : ghostFallbackPending
                ? 'Подбираю призрака...'
                : searchTimeLeft > 0
                  ? `00:${searchTimeLeft.toString().padStart(2, '0')}`
                  : 'Отмена...'}
          </p>
        )}

        {!isInvite && !isRematchWaiting && ghostFallbackPending && (
          <p className="text-cyan-200/80 text-xs mb-5 text-center">
            Ищем асинхронного соперника из реальных матчей
          </p>
        )}

        {isRematchWaiting && (
          <p className="text-cyan-200/80 text-xs mb-5 text-center">
            Приглашение отправлено. Награды за этот матч будут с коэффициентом 0.5
          </p>
        )}

        <button
          onClick={onCancel}
          className="text-white/40 text-sm hover:text-white"
        >
          {isRematchWaiting ? 'Отменить приглашение' : 'Отменить поиск'}
        </button>
      </div>
    </div>
  )
}

export function DuelFoundView({ user, myRating, opponent, duel }) {
  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col items-center justify-center p-6">
      <div className="noise-overlay" />

      <div className="relative z-10 w-full flex flex-col items-center gap-8">
        <motion.div
          initial={{ x: -100, opacity: 0 }}
          animate={{ x: 0, opacity: 1 }}
          transition={{ type: 'spring', stiffness: 100 }}
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
          transition={{ delay: 0.3, type: 'spring' }}
          className="text-6xl font-black italic text-transparent bg-clip-text bg-gradient-to-r from-red-500 to-orange-500"
        >
          VS
        </motion.div>

        <motion.div
          initial={{ x: 100, opacity: 0 }}
          animate={{ x: 0, opacity: 1 }}
          transition={{ type: 'spring', stiffness: 100, delay: 0.1 }}
          className="flex flex-col items-center"
        >
          <AvatarWithFrame
            photoUrl={opponent?.photo_url}
            name={opponent?.name || 'Соперник'}
            frameKey={opponent?.equipped_frame}
            size={96}
            showGlow
          />
          <p className="mt-4 font-bold text-xl text-white">{opponent?.name || 'Соперник'}</p>
          {duel?.is_ghost_match && (
            <p className="mt-1 text-[11px] uppercase tracking-wide text-cyan-200/90">Асинхронный призрак</p>
          )}
          <div className="px-3 py-1 bg-white/10 rounded-full text-xs font-mono mt-2 text-white/60">
            {opponent?.rating || '???'} MMR
          </div>
        </motion.div>
      </div>
    </div>
  )
}
