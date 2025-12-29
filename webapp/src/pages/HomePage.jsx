import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'

// Telegram ID администраторов
const ADMIN_IDS = [1763619724]

function HomePage() {
  const { user } = useTelegram()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    showBackButton(false)
    loadStats()
  }, [])

  const loadStats = async () => {
    try {
      const response = await api.getProfile()
      if (response.success) {
        setStats(response.data)
      }
    } catch (err) {
      console.error('Failed to load stats:', err)
    } finally {
      setLoading(false)
    }
  }

  const handleMenuClick = () => {
    hapticFeedback('light')
  }

  // Animation variants
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.08,
        delayChildren: 0.1
      }
    }
  }

  const itemVariants = {
    hidden: { opacity: 0, y: 20, scale: 0.95 },
    visible: { 
      opacity: 1, 
      y: 0, 
      scale: 1,
      transition: {
        type: "spring",
        stiffness: 300,
        damping: 24
      }
    }
  }

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden">
      {/* Aurora Background Blobs */}
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="aurora-blob aurora-blob-3" />
      <div className="aurora-blob aurora-blob-4" />
      
      {/* Noise Overlay */}
      <div className="noise-overlay" />

      {/* Content */}
      <div className="relative z-10 p-4 pb-8">
        {/* Header */}
        <motion.div 
          initial={{ opacity: 0, y: -30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ type: "spring", stiffness: 200, damping: 20 }}
          className="text-center mb-6 pt-4"
        >
          <motion.div 
            className="text-6xl mb-3"
            animate={{ 
              rotate: [0, -10, 10, -10, 0],
              scale: [1, 1.1, 1]
            }}
            transition={{ 
              duration: 2, 
              repeat: Infinity, 
              repeatDelay: 3 
            }}
          >
            ⚔️
          </motion.div>
          <h1 className="text-3xl font-bold mb-2 text-gradient-primary">
            Битва знаний
          </h1>
          {user && (
            <motion.p 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.3 }}
              className="text-white/50 text-sm"
            >
              Привет, {user.first_name}! 👋
            </motion.p>
          )}
        </motion.div>

        {/* Bento Grid Menu */}
        <motion.div 
          className="grid grid-cols-4 gap-3 auto-rows-[85px] mb-5"
          variants={containerVariants}
          initial="hidden"
          animate="visible"
        >
          {/* Дуэль - большая карточка 2x2 */}
          <motion.div 
            variants={itemVariants}
            className="col-span-2 row-span-2"
          >
            <Link
              to="/duel"
              onClick={handleMenuClick}
              className="block h-full"
            >
              <div className="bento-card card-shine h-full p-5 flex flex-col justify-between group cursor-pointer">
                {/* Glow effect */}
                <div className="bento-glow bg-gradient-to-br from-red-500/20 via-orange-500/10 to-transparent blur-2xl" />
                
                <div className="relative">
                  <motion.div 
                    className="text-4xl mb-2"
                    whileHover={{ scale: 1.2, rotate: 15 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    ⚔️
                  </motion.div>
                  <h3 className="text-xl font-bold text-white mb-1">Дуэль</h3>
                  <p className="text-white/40 text-sm">Сразись с соперником</p>
                </div>

                <div className="relative flex items-center gap-2 text-xs text-white/30">
                  <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                  <span>Онлайн</span>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Правда или ложь */}
          <motion.div variants={itemVariants} className="col-span-2">
            <Link
              to="/truefalse"
              onClick={handleMenuClick}
              className="block h-full"
            >
              <div className="bento-card card-shine h-full p-4 flex items-center gap-3 group cursor-pointer">
                <div className="bento-glow bg-gradient-to-br from-purple-500/20 via-pink-500/10 to-transparent blur-2xl" />
                
                <motion.div 
                  className="text-3xl"
                  whileHover={{ scale: 1.2 }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  🧠
                </motion.div>
                <div className="relative">
                  <h3 className="font-semibold text-white text-sm">Правда или ложь</h3>
                  <p className="text-white/40 text-xs">Проверь знания</p>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Профиль */}
          <motion.div variants={itemVariants} className="col-span-2">
            <Link
              to="/profile"
              onClick={handleMenuClick}
              className="block h-full"
            >
              <div className="bento-card card-shine h-full p-4 flex items-center gap-3 group cursor-pointer">
                <div className="bento-glow bg-gradient-to-br from-blue-500/20 via-cyan-500/10 to-transparent blur-2xl" />
                
                <motion.div 
                  className="text-3xl"
                  whileHover={{ scale: 1.2 }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  📊
                </motion.div>
                <div className="relative">
                  <h3 className="font-semibold text-white text-sm">Профиль</h3>
                  <p className="text-white/40 text-xs">Твоя статистика</p>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Пригласить друзей - широкая карточка */}
          <motion.div variants={itemVariants} className="col-span-4">
            <Link
              to="/referral"
              onClick={handleMenuClick}
              className="block h-full"
            >
              <div className="bento-card card-shine h-full p-4 flex items-center justify-between group cursor-pointer">
                <div className="bento-glow bg-gradient-to-br from-pink-500/20 via-purple-500/10 to-transparent blur-2xl" />
                
                <div className="relative flex items-center gap-4">
                  <motion.div 
                    className="text-3xl"
                    whileHover={{ scale: 1.2, rotate: 10 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    🎁
                  </motion.div>
                  <div>
                    <h3 className="font-semibold text-white">Пригласить друзей</h3>
                    <p className="text-white/40 text-xs">Получи награды за рефералов</p>
                  </div>
                </div>

                {/* Coins preview */}
                <div className="relative flex items-center gap-2 bg-white/5 px-3 py-1.5 rounded-full">
                  <CoinIcon size={24} />
                  <span className="text-white/80 text-sm font-medium">+100</span>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Магазин */}
          <motion.div variants={itemVariants}>
            <Link to="/shop" onClick={handleMenuClick} className="block h-full">
              <div className="bento-card card-shine h-full p-3 flex flex-col items-center justify-center text-center group cursor-pointer aspect-square">
                <div className="bento-glow bg-gradient-to-br from-blue-500/20 via-cyan-500/10 to-transparent blur-2xl" />
                <div className="relative">
                  <motion.div className="text-3xl mb-1" whileHover={{ scale: 1.2 }}>🏪</motion.div>
                  <h3 className="font-semibold text-white text-xs">Магазин</h3>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Колесо фортуны */}
          <motion.div variants={itemVariants}>
            <Link to="/wheel" onClick={handleMenuClick} className="block h-full">
              <div className="bento-card card-shine h-full p-3 flex flex-col items-center justify-center text-center group cursor-pointer aspect-square">
                <div className="bento-glow bg-gradient-to-br from-purple-500/20 via-pink-500/10 to-transparent blur-2xl" />
                <div className="relative">
                  <motion.div className="text-3xl mb-1" whileHover={{ rotate: 180 }} transition={{ duration: 0.5 }}>🎰</motion.div>
                  <h3 className="font-semibold text-white text-xs">Колесо</h3>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Инвентарь */}
          <motion.div variants={itemVariants}>
            <Link to="/inventory" onClick={handleMenuClick} className="block h-full">
              <div className="bento-card card-shine h-full p-3 flex flex-col items-center justify-center text-center group cursor-pointer aspect-square">
                <div className="bento-glow bg-gradient-to-br from-green-500/20 via-emerald-500/10 to-transparent blur-2xl" />
                <div className="relative">
                  <motion.div className="text-3xl mb-1" whileHover={{ scale: 1.2 }}>🎒</motion.div>
                  <h3 className="font-semibold text-white text-xs">Инвентарь</h3>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Лутбоксы */}
          <motion.div variants={itemVariants}>
            <Link to="/lootbox" onClick={handleMenuClick} className="block h-full">
              <div className="bento-card card-shine h-full p-3 flex flex-col items-center justify-center text-center group cursor-pointer aspect-square">
                <div className="bento-glow bg-gradient-to-br from-orange-500/20 via-yellow-500/10 to-transparent blur-2xl" />
                <div className="relative">
                  <motion.div className="text-3xl mb-1" whileHover={{ scale: 1.2, rotate: 15 }}>🎁</motion.div>
                  <h3 className="font-semibold text-white text-xs">Лутбоксы</h3>
                </div>
              </div>
            </Link>
          </motion.div>

          {/* Рейтинг - широкая карточка */}
          <motion.div variants={itemVariants} className="col-span-4">
            <Link
              to="/leaderboard"
              onClick={handleMenuClick}
              className="block h-full"
            >
              <div className="bento-card card-shine h-full p-4 flex items-center justify-between group cursor-pointer">
                <div className="bento-glow bg-gradient-to-br from-yellow-500/20 via-amber-500/10 to-transparent blur-2xl" />
                
                <div className="relative flex items-center gap-4">
                  <motion.div 
                    className="text-3xl"
                    whileHover={{ scale: 1.2, rotate: -15 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    🏆
                  </motion.div>
                  <div>
                    <h3 className="font-semibold text-white">Рейтинг</h3>
                    <p className="text-white/40 text-xs">Лучшие игроки</p>
                  </div>
                </div>

                {/* Top 3 avatars preview */}
                <div className="relative flex -space-x-2">
                  <div className="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-amber-500 border-2 border-dark-950 flex items-center justify-center text-xs">🥇</div>
                  <div className="w-8 h-8 rounded-full bg-gradient-to-br from-gray-300 to-gray-400 border-2 border-dark-950 flex items-center justify-center text-xs">🥈</div>
                  <div className="w-8 h-8 rounded-full bg-gradient-to-br from-amber-600 to-orange-700 border-2 border-dark-950 flex items-center justify-center text-xs">🥉</div>
                </div>
              </div>
            </Link>
          </motion.div>
        </motion.div>

        {/* Quick Action - Случайная дуэль */}
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5, type: "spring", stiffness: 200 }}
        >
          <Link to="/duel?mode=random" onClick={handleMenuClick}>
            <motion.button 
              className="w-full py-4 px-6 rounded-3xl bg-gradient-to-r from-game-primary via-purple-500 to-game-pink text-white font-bold text-lg relative overflow-hidden group"
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
            >
              {/* Animated shine */}
              <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700" />
              
              {/* Glow */}
              <div className="absolute inset-0 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-r from-game-primary/50 to-purple-500/50 blur-xl -z-10" />
              
              <span className="relative flex items-center justify-center gap-3">
                <motion.span
                  animate={{ rotate: [0, 360] }}
                  transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
                >
                  🎲
                </motion.span>
                <span>Случайная дуэль</span>
              </span>
            </motion.button>
          </Link>
        </motion.div>

        {/* Stats Preview */}
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6, type: "spring", stiffness: 200 }}
          className="mt-5"
        >
          <div className="glass rounded-3xl p-5">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-white/50 text-sm font-medium">Твоя статистика</h3>
              <Link to="/stats" className="text-game-primary text-xs hover:text-game-primary-light transition-colors">
                Подробнее →
              </Link>
            </div>

            {loading ? (
              <div className="grid grid-cols-3 gap-4">
                {[1, 2, 3].map(i => (
                  <div key={i} className="text-center">
                    <div className="skeleton h-8 w-16 mx-auto mb-2" />
                    <div className="skeleton h-3 w-12 mx-auto" />
                  </div>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-3 gap-4">
                <StatItem 
                  value={stats?.rating ?? '—'} 
                  label="Рейтинг" 
                  color="text-gradient-primary"
                  icon="📈"
                />
                <StatItem 
                  value={stats?.stats?.duel_wins ?? '—'} 
                  label="Победы" 
                  color="text-game-success"
                  icon="🏆"
                />
                <StatItem 
                  value={stats?.win_streak ?? '—'} 
                  label="Серия" 
                  color="text-game-warning"
                  icon="🔥"
                />
              </div>
            )}
          </div>
        </motion.div>

        {/* Admin Button */}
        {user && ADMIN_IDS.includes(user.id) && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.8 }}
            className="mt-4"
          >
            <Link to="/admin" onClick={handleMenuClick}>
              <div className="glass rounded-2xl p-4 flex items-center gap-4 border border-red-500/20 hover:border-red-500/40 transition-colors group">
                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-orange-500 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                  ⚙️
                </div>
                <div>
                  <h3 className="font-semibold text-white">Админ-панель</h3>
                  <p className="text-xs text-white/40">Управление ботом</p>
                </div>
              </div>
            </Link>
          </motion.div>
        )}
      </div>
    </div>
  )
}

function StatItem({ value, label, color, icon }) {
  return (
    <motion.div 
      className="text-center"
      whileHover={{ scale: 1.05 }}
      transition={{ type: "spring", stiffness: 400 }}
    >
      <div className="flex items-center justify-center gap-1 mb-1">
        <span className="text-sm">{icon}</span>
        <span className={`text-2xl font-bold ${color}`}>{value}</span>
      </div>
      <p className="text-xs text-white/40">{label}</p>
    </motion.div>
  )
}

export default HomePage
