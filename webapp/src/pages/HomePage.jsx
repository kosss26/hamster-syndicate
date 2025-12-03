import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

const menuItems = [
  {
    id: 'duel',
    icon: '⚔️',
    title: 'Дуэль',
    subtitle: 'Сразись с соперником',
    path: '/duel',
    gradient: 'from-red-500 to-orange-500'
  },
  {
    id: 'truefalse',
    icon: '🧠',
    title: 'Правда или ложь',
    subtitle: 'Проверь свои знания',
    path: '/truefalse',
    gradient: 'from-purple-500 to-pink-500'
  },
  {
    id: 'profile',
    icon: '📊',
    title: 'Профиль',
    subtitle: 'Твоя статистика',
    path: '/profile',
    gradient: 'from-blue-500 to-cyan-500'
  },
  {
    id: 'leaderboard',
    icon: '🏆',
    title: 'Рейтинг',
    subtitle: 'Лучшие игроки',
    path: '/leaderboard',
    gradient: 'from-yellow-500 to-amber-500'
  }
]

function HomePage() {
  const { user } = useTelegram()
  const [stats, setStats] = useState(null)

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
    }
  }

  const handleMenuClick = () => {
    hapticFeedback('light')
  }

  return (
    <div className="min-h-screen p-4 pb-8">
      {/* Header */}
      <motion.div 
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center mb-8 pt-4"
      >
        <div className="text-5xl mb-3">⚔️</div>
        <h1 className="text-2xl font-bold mb-1">Битва знаний</h1>
        <p className="text-xs text-red-500">v3 - {new Date().toLocaleTimeString()}</p>
        {user && (
          <p className="text-telegram-hint">
            Привет, {user.first_name}! 👋
          </p>
        )}
      </motion.div>

      {/* Menu Grid */}
      <div className="grid grid-cols-2 gap-3">
        {menuItems.map((item, index) => (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, scale: 0.8 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: index * 0.1 }}
          >
            <Link
              to={item.path}
              onClick={handleMenuClick}
              className="block"
            >
              <div className="glass rounded-2xl p-4 h-full hover:scale-105 transition-transform active:scale-95">
                <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${item.gradient} flex items-center justify-center text-2xl mb-3`}>
                  {item.icon}
                </div>
                <h3 className="font-semibold text-sm mb-1">{item.title}</h3>
                <p className="text-xs text-telegram-hint">{item.subtitle}</p>
              </div>
            </Link>
          </motion.div>
        ))}
      </div>

      {/* Quick Action - Случайная дуэль */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="mt-6"
      >
        <Link to="/duel?mode=random" onClick={handleMenuClick}>
          <button className="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-game-primary to-purple-600 text-white font-semibold text-lg shadow-lg shadow-game-primary/30 hover:shadow-xl hover:shadow-game-primary/40 transition-all active:scale-95">
            <span className="flex items-center justify-center gap-2">
              <span>🎲</span>
              <span>Случайная дуэль</span>
            </span>
          </button>
        </Link>
      </motion.div>

      {/* Stats Preview */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.7 }}
        className="mt-6 glass rounded-2xl p-4"
      >
        <h3 className="text-sm text-telegram-hint mb-3">Твоя статистика</h3>
        <div className="grid grid-cols-3 gap-4 text-center">
          <div>
            <div className="text-2xl font-bold text-game-primary">{stats?.rating ?? '—'}</div>
            <div className="text-xs text-telegram-hint">Рейтинг</div>
          </div>
          <div>
            <div className="text-2xl font-bold text-game-success">{stats?.stats?.duel_wins ?? '—'}</div>
            <div className="text-xs text-telegram-hint">Победы</div>
          </div>
          <div>
            <div className="text-2xl font-bold text-game-warning">{stats?.win_streak ?? '—'}</div>
            <div className="text-xs text-telegram-hint">Серия</div>
          </div>
        </div>
      </motion.div>
    </div>
  )
}

export default HomePage

