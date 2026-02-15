import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'
import CoinIcon from '../components/CoinIcon'

function HomePage() {
  const { user } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [onlineCount, setOnlineCount] = useState(null)
  const [isAdmin, setIsAdmin] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    checkActiveDuel()
    loadData()

    const interval = setInterval(() => {
      loadOnline()
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

  return (
    <div className="min-h-dvh bg-aurora relative flex flex-col overflow-hidden">
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-5 pt-5 safe-top">
        <div className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4 mb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3 min-w-0">
              <AvatarWithFrame
                photoUrl={user?.photo_url}
                name={user?.first_name}
                size={46}
                frameKey={profile?.equipped_frame}
              />
              <div className="min-w-0">
                <p className="text-white/50 text-[11px] uppercase tracking-wider">Привет</p>
                <h2 className="text-white font-bold text-base truncate">{user?.first_name || 'Игрок'}</h2>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1.5 rounded-full border border-emerald-300/30 bg-emerald-500/10 px-3 py-1.5">
                <span className="relative flex h-2.5 w-2.5">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-400"></span>
                </span>
                <span className="text-white font-semibold text-sm">{onlineCount !== null ? onlineCount : '...'}</span>
              </div>
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
        </div>
      </div>

      <div className="relative z-10 px-5 pb-24 overflow-y-auto">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-[34px] border border-cyan-300/20 bg-[radial-gradient(circle_at_15%_20%,rgba(56,189,248,0.22),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(16,185,129,0.22),transparent_40%),linear-gradient(165deg,rgba(15,23,42,0.78),rgba(3,7,18,0.9))] backdrop-blur-xl p-5 mb-4 overflow-hidden"
        >
          <div className="absolute -top-20 -right-20 w-52 h-52 rounded-full bg-cyan-400/25 blur-3xl" />
          <div className="absolute -bottom-24 -left-16 w-56 h-56 rounded-full bg-emerald-400/20 blur-3xl" />
          <div className="relative text-center">
            <h1 className="text-white text-3xl font-black leading-tight mb-2">Дуэль</h1>
            <p className="text-white/70 text-sm mb-4">Выбери формат и начни игру.</p>
          </div>

          <div className="grid grid-cols-1 gap-2.5 mb-3">
            <button
              onClick={handlePlay}
              className="w-full rounded-2xl bg-white text-slate-900 font-black py-4 text-base active:scale-[0.99] transition-transform"
            >
              ⚔️ Выбрать режим
            </button>
          </div>

          <div className="grid grid-cols-2 gap-2.5 relative">
            <button
              onClick={handleQuickRandom}
              className="rounded-xl border border-indigo-300/45 bg-indigo-500/25 text-white py-3 text-sm font-semibold"
            >
              🎲 Случайный
            </button>
            <button
              onClick={handleQuickFriend}
              className="rounded-xl border border-cyan-300/45 bg-cyan-500/25 text-white py-3 text-sm font-semibold"
            >
              👥 С другом
            </button>
          </div>
        </motion.div>

        <div className="grid grid-cols-2 gap-3">
          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/truefalse')}
            className="col-span-2 rounded-2xl border border-cyan-300/25 bg-cyan-500/10 p-4 text-left"
          >
            <div className="flex items-start justify-between gap-4">
              <div className="min-w-0">
                <div className="flex items-center gap-2 mb-2">
                  <span className="text-2xl">🧠</span>
                  <div className="text-white font-semibold text-sm">Правда или ложь</div>
                </div>
                <div className="text-white/70 text-xs leading-relaxed">Быстрые факты, мгновенная проверка и серия без пауз</div>
              </div>
              <div className="shrink-0 text-right">
                <div className="text-[10px] uppercase tracking-wider text-white/45 mb-1">Рекорд</div>
                <div className="text-xl font-black text-cyan-200 leading-none">{profile?.true_false_record ?? 0}</div>
              </div>
            </div>
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
