import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'

function HomePage() {
  const { user, tg } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [onlineCount, setOnlineCount] = useState(0)
  const [isAdmin, setIsAdmin] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadData()
    // Обновляем онлайн каждые 30 секунд
    const interval = setInterval(loadOnline, 30000)
    return () => clearInterval(interval)
  }, [])

  const loadData = async () => {
    setLoading(true)
    try {
      await Promise.all([
        loadProfile(),
        loadOnline(),
        checkAdmin()
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
      // Игнорируем ошибку проверки админа
    }
  }

  const handlePlay = () => {
    hapticFeedback('heavy')
    navigate('/duel?mode=random')
  }

  const handleInvite = () => {
    hapticFeedback('medium')
    navigate('/referral')
  }

  // Анимация кругов на фоне
  const backgroundCircles = {
    animate: {
      scale: [1, 1.2, 1],
      opacity: [0.3, 0.5, 0.3],
      transition: {
        duration: 8,
        repeat: Infinity,
        ease: "easeInOut"
      }
    }
  }

  return (
    <div className="min-h-dvh bg-aurora relative flex flex-col overflow-hidden">
      {/* Background Effects */}
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="noise-overlay" />
      
      {/* Top Header */}
      <div className="relative z-10 px-6 pt-6 flex justify-between items-center safe-top">
        <div className="flex items-center gap-3">
          <AvatarWithFrame 
            photoUrl={user?.photo_url} 
            name={user?.first_name} 
            size={48}
            frameKey={profile?.equipped_frame}
          />
          <div>
            <p className="text-white/60 text-xs uppercase tracking-wider">Привет,</p>
            <h2 className="text-white font-bold text-lg leading-tight">{user?.first_name || 'Игрок'}</h2>
          </div>
        </div>

        {/* Currency Display */}
        {profile && (
          <div className="flex items-center gap-2 bg-black/20 backdrop-blur-md rounded-full px-3 py-1.5 border border-white/5">
            <span className="text-xl">💰</span>
            <span className="font-bold text-white text-sm">{profile.coins}</span>
          </div>
        )}
      </div>

      {/* Main Content - Centered */}
      <div className="flex-1 flex flex-col items-center justify-center relative z-10 px-6 overflow-y-auto pb-24">
        
        {/* Animated Play Button Container */}
        <div className="relative mb-8 group shrink-0">
          {/* Pulsing Circles */}
          <motion.div 
            className="absolute inset-0 bg-game-primary/30 rounded-full blur-3xl"
            variants={backgroundCircles}
            animate="animate"
          />
          
          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={handlePlay}
            className="relative w-48 h-48 rounded-full bg-gradient-to-br from-game-primary to-purple-600 shadow-glow flex flex-col items-center justify-center border-4 border-white/10 z-20 group-hover:shadow-[0_0_60px_rgba(99,102,241,0.6)] transition-shadow duration-300"
          >
            <div className="text-6xl mb-2">⚔️</div>
            <span className="text-white font-bold text-xl tracking-wider">В БОЙ</span>
            <span className="text-white/60 text-xs mt-1">Случайный игрок</span>
          </motion.button>

          {/* Decorative Rings */}
          <div className="absolute inset-0 border border-white/10 rounded-full scale-125 animate-spin-slow pointer-events-none" />
          <div className="absolute inset-0 border border-white/5 rounded-full scale-150 animate-reverse-spin pointer-events-none" />
        </div>

        {/* Online Status */}
        <div className="mb-8 flex items-center gap-2 bg-white/5 px-4 py-2 rounded-full backdrop-blur-sm shrink-0">
          <span className="relative flex h-3 w-3">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
          </span>
          <span className="text-white/60 text-sm font-medium">
            {onlineCount > 0 ? `${onlineCount} онлайн` : 'Загрузка...'}
          </span>
        </div>

        {/* Secondary Actions Grid */}
        <div className="grid grid-cols-2 gap-3 w-full max-w-sm shrink-0">
          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={handleInvite}
            className="bento-card p-4 flex flex-col items-center justify-center bg-white/5 hover:bg-white/10 transition-colors h-24"
          >
            <div className="text-2xl mb-1">👥</div>
            <div className="font-semibold text-white text-xs">Пригласить</div>
            <div className="text-white/40 text-[10px]">друга</div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/truefalse')}
            className="bento-card p-4 flex flex-col items-center justify-center bg-gradient-to-br from-blue-500/10 to-cyan-500/5 hover:from-blue-500/20 transition-colors h-24"
          >
            <div className="text-2xl mb-1">🧠</div>
            <div className="font-semibold text-white text-xs">Правда / Ложь</div>
            <div className="text-white/40 text-[10px]">Режим игры</div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/wheel')}
            className="col-span-2 bento-card p-3 flex items-center justify-center gap-3 bg-gradient-to-r from-amber-500/10 to-orange-500/5 hover:from-amber-500/20 transition-colors"
          >
            <div className="text-2xl">🎡</div>
            <div className="text-left">
              <div className="font-semibold text-white text-sm">Колесо Фортуны</div>
              <div className="text-white/40 text-xs">Ежедневный бонус</div>
            </div>
          </motion.button>

          {isAdmin && (
            <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => navigate('/admin')}
              className="col-span-2 mt-2 bg-red-500/10 border border-red-500/20 rounded-xl p-2 text-red-400 text-xs font-medium"
            >
              🛠 Админ панель
            </motion.button>
          )}
        </div>
      </div>
      
      {/* Spacer is handled by padding-bottom in main content */}
    </div>
  )
}

export default HomePage
