import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'
import CoinIcon from '../components/CoinIcon'

const RANK_STEPS = [
  { min: 0, max: 399, name: 'Новичок', emoji: '🥉' },
  { min: 400, max: 599, name: 'Ученик', emoji: '📚' },
  { min: 600, max: 799, name: 'Знаток', emoji: '📖' },
  { min: 800, max: 999, name: 'Студент', emoji: '🎓' },
  { min: 1000, max: 1199, name: 'Эксперт', emoji: '⭐' },
  { min: 1200, max: 1399, name: 'Мастер', emoji: '⭐⭐' },
  { min: 1400, max: 1599, name: 'Гранд-мастер', emoji: '⭐⭐⭐' },
  { min: 1600, max: 1799, name: 'Элита', emoji: '💎' },
  { min: 1800, max: 1999, name: 'Легенда', emoji: '👑' },
  { min: 2000, max: Infinity, name: 'Иммортал', emoji: '🌟' },
]

function HomePage() {
  const { user } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [onlineCount, setOnlineCount] = useState(null)
  const [wheelStatus, setWheelStatus] = useState(null)
  const [isAdmin, setIsAdmin] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    checkActiveDuel()
    loadData()

    const interval = setInterval(() => {
      loadOnline()
      loadWheelStatus()
    }, 30000)

    return () => clearInterval(interval)
  }, [])

  const checkActiveDuel = async () => {
    try {
      const response = await api.getActiveDuel()
      if (response.success && response.data.duel_id) {
        navigate(`/duel/${response.data.duel_id}`)
      }
    } catch (err) {
      console.error('Failed to check active duel:', err)
    }
  }

  const loadData = async () => {
    setLoading(true)
    try {
      await Promise.all([
        loadProfile(),
        loadOnline(),
        loadWheelStatus(),
        checkAdmin(),
      ])
    } finally {
      setLoading(false)
    }
  }

  const loadProfile = async () => {
    try {
      const response = await api.getProfile()
      if (response.success) {
        setProfile(response.data)
      }
    } catch (err) {
      console.error('Failed to load profile:', err)
    }
  }

  const loadOnline = async () => {
    try {
      const response = await api.getOnline()
      if (response.success) {
        setOnlineCount(response.data.online)
      }
    } catch (err) {
      console.error('Failed to load online:', err)
    }
  }

  const loadWheelStatus = async () => {
    try {
      const response = await api.getWheelStatus()
      if (response.success) {
        setWheelStatus(response.data)
      }
    } catch (err) {
      console.error('Failed to load wheel status:', err)
    }
  }

  const checkAdmin = async () => {
    try {
      const response = await api.isAdmin()
      if (response.success) {
        setIsAdmin(response.data.is_admin)
      }
    } catch (err) {
      // ignore
    }
  }

  const handlePlay = () => {
    hapticFeedback('heavy')
    navigate('/duel')
  }

  const handleQuickRandom = () => {
    hapticFeedback('medium')
    navigate('/duel?mode=random')
  }

  const handleQuickFriend = () => {
    hapticFeedback('medium')
    navigate('/duel')
  }

  const rankInfo = useMemo(() => {
    const rating = Number(profile?.rating || 0)
    const current = RANK_STEPS.find((step) => rating >= step.min && rating <= step.max) || RANK_STEPS[0]
    const next = RANK_STEPS.find((step) => step.min > current.min) || null

    if (!next || current.max === Infinity) {
      return {
        current,
        progress: 100,
        toNext: 0,
        next,
      }
    }

    const span = current.max - current.min + 1
    const clamped = Math.max(0, rating - current.min)
    const progress = Math.min(100, Math.round((clamped / span) * 100))
    const toNext = Math.max(0, next.min - rating)

    return {
      current,
      progress,
      toNext,
      next,
    }
  }, [profile?.rating])

  const wheelHint = useMemo(() => {
    if (!wheelStatus) return 'Загрузка статуса...'
    if (wheelStatus.can_spin_free) return 'Бесплатный спин доступен'
    const hours = Number(wheelStatus.hours_left || 0)
    const minutes = Number(wheelStatus.minutes_left || 0)
    return `Через ${hours}ч ${minutes}м`
  }, [wheelStatus])

  return (
    <div className="min-h-dvh bg-aurora relative flex flex-col overflow-hidden">
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-5 pt-5 safe-top">
        <div className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4 mb-4">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3 min-w-0">
              <AvatarWithFrame
                photoUrl={user?.photo_url}
                name={user?.first_name}
                size={46}
                frameKey={profile?.equipped_frame}
              />
              <div className="min-w-0">
                <p className="text-white/50 text-[11px] uppercase tracking-wider">Профиль</p>
                <h2 className="text-white font-bold text-base truncate">{user?.first_name || 'Игрок'}</h2>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1.5 rounded-full border border-amber-300/30 bg-amber-500/10 px-3 py-1.5">
                <CoinIcon className="w-4 h-4" />
                <span className="text-white font-semibold text-sm">{profile?.coins ?? '...'}</span>
              </div>
              <div className="flex items-center gap-1.5 rounded-full border border-cyan-300/30 bg-cyan-500/10 px-3 py-1.5">
                <span className="text-sm">💎</span>
                <span className="text-white font-semibold text-sm">{profile?.gems ?? '...'}</span>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
            <div className="flex items-center justify-between mb-2">
              <div className="text-xs text-white/55 uppercase tracking-wide">Ранг</div>
              <div className="text-xs text-white/70 font-semibold">{rankInfo.current.emoji} {rankInfo.current.name}</div>
            </div>
            <div className="h-2 rounded-full bg-white/10 overflow-hidden mb-2">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${rankInfo.progress}%` }}
                transition={{ duration: 0.8, ease: 'easeOut' }}
                className="h-full bg-gradient-to-r from-indigo-400 via-cyan-400 to-emerald-400"
              />
            </div>
            <div className="text-[11px] text-white/55">
              {rankInfo.toNext > 0 ? `До следующего ранга: ${rankInfo.toNext} MMR` : 'Максимальный ранг достигнут'}
            </div>
          </div>
        </div>
      </div>

      <div className="relative z-10 px-5 pb-24 overflow-y-auto">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-[30px] border border-white/10 bg-gradient-to-br from-indigo-500/20 via-sky-500/10 to-transparent backdrop-blur-xl p-5 mb-4"
        >
          <div className="text-white/70 text-xs uppercase tracking-wide mb-1">Главный режим</div>
          <h1 className="text-white text-2xl font-black leading-tight mb-2">Дуэли знаний</h1>
          <p className="text-white/60 text-sm mb-4">Выбери формат боя и начни матч за рейтинг и награды.</p>

          <button
            onClick={handlePlay}
            className="w-full rounded-2xl bg-white text-slate-900 font-bold py-3.5 mb-3 active:scale-[0.99] transition-transform"
          >
            ⚔️ Выбрать режим боя
          </button>

          <div className="grid grid-cols-2 gap-2.5">
            <button
              onClick={handleQuickRandom}
              className="rounded-xl border border-indigo-300/40 bg-indigo-500/20 text-white py-2.5 text-sm font-semibold"
            >
              🎲 Случайный
            </button>
            <button
              onClick={handleQuickFriend}
              className="rounded-xl border border-cyan-300/40 bg-cyan-500/20 text-white py-2.5 text-sm font-semibold"
            >
              👥 С другом
            </button>
          </div>
        </motion.div>

        <div className="grid grid-cols-2 gap-3 mb-4">
          <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
            <div className="text-[11px] text-white/55 uppercase tracking-wide mb-1">Онлайн</div>
            <div className="text-white text-lg font-bold">{onlineCount !== null ? onlineCount : '...'}</div>
            <div className="text-[11px] text-emerald-300">Игроков в сети</div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
            <div className="text-[11px] text-white/55 uppercase tracking-wide mb-1">Рекорд Т/Л</div>
            <div className="text-white text-lg font-bold">{profile?.true_false_record ?? 0}</div>
            <div className="text-[11px] text-cyan-200">Лучшая серия</div>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/truefalse')}
            className="rounded-2xl border border-cyan-300/25 bg-cyan-500/10 p-4 text-left"
          >
            <div className="text-2xl mb-2">🧠</div>
            <div className="text-white font-semibold text-sm mb-1">Правда или ложь</div>
            <div className="text-white/55 text-xs">Побей свой рекорд</div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/wheel')}
            className="rounded-2xl border border-amber-300/25 bg-amber-500/10 p-4 text-left"
          >
            <div className="text-2xl mb-2">🎡</div>
            <div className="text-white font-semibold text-sm mb-1">Колесо фортуны</div>
            <div className="text-white/55 text-xs">{wheelHint}</div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/referral')}
            className="col-span-2 rounded-2xl border border-emerald-300/25 bg-emerald-500/10 p-4 text-left"
          >
            <div className="flex items-center justify-between">
              <div>
                <div className="text-white font-semibold text-sm mb-1">👥 Приглашай друзей</div>
                <div className="text-white/55 text-xs">Увеличивай награды и развивай аккаунт быстрее</div>
              </div>
              <div className="text-emerald-200 text-xs font-semibold whitespace-nowrap">Бонусы</div>
            </div>
          </motion.button>

          {isAdmin && (
            <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => navigate('/admin')}
              className="col-span-2 mt-1 rounded-2xl border border-red-400/30 bg-red-500/10 p-3 text-red-300 text-sm font-semibold"
            >
              🛠 Админ панель
            </motion.button>
          )}
        </div>

        {loading && (
          <div className="text-center text-white/40 text-xs mt-4">Обновляю данные...</div>
        )}
      </div>
    </div>
  )
}

export default HomePage
