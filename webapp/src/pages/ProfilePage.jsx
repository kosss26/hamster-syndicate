import { useEffect } from 'react'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton } from '../hooks/useTelegram'

// Моковые данные профиля
const MOCK_PROFILE = {
  rating: 1250,
  rank: '⭐⭐ Мастер',
  coins: 1500,
  winStreak: 5,
  stats: {
    wins: 42,
    losses: 18,
    draws: 3,
    totalGames: 63,
    winRate: 67
  },
  trueFalse: {
    record: 15,
    gamesPlayed: 28
  }
}

function ProfilePage() {
  const { user } = useTelegram()

  useEffect(() => {
    showBackButton(true)
  }, [])

  return (
    <div className="min-h-screen p-4 pb-8">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center pt-4 mb-6"
      >
        <div className="w-20 h-20 rounded-full bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center text-3xl font-bold mx-auto mb-3">
          {user?.first_name?.[0] || '?'}
        </div>
        <h1 className="text-xl font-bold">{user?.first_name} {user?.last_name || ''}</h1>
        {user?.username && (
          <p className="text-telegram-hint">@{user.username}</p>
        )}
      </motion.div>

      {/* Rating Card */}
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ delay: 0.1 }}
        className="glass rounded-2xl p-5 mb-4"
      >
        <div className="flex items-center justify-between mb-4">
          <div>
            <p className="text-telegram-hint text-sm">Рейтинг</p>
            <p className="text-3xl font-bold text-game-primary">{MOCK_PROFILE.rating}</p>
          </div>
          <div className="text-right">
            <p className="text-telegram-hint text-sm">Ранг</p>
            <p className="text-xl font-semibold">{MOCK_PROFILE.rank}</p>
          </div>
        </div>
        
        {/* Progress to next rank */}
        <div>
          <div className="flex justify-between text-xs text-telegram-hint mb-1">
            <span>До следующего ранга</span>
            <span>150 очков</span>
          </div>
          <div className="h-2 bg-white/10 rounded-full overflow-hidden">
            <motion.div
              initial={{ width: 0 }}
              animate={{ width: '60%' }}
              transition={{ delay: 0.5, duration: 0.8 }}
              className="h-full bg-gradient-to-r from-game-primary to-purple-600 rounded-full"
            />
          </div>
        </div>
      </motion.div>

      {/* Resources */}
      <motion.div
        initial={{ opacity: 0, x: -20 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ delay: 0.2 }}
        className="glass rounded-2xl p-4 mb-4"
      >
        <h3 className="text-sm text-telegram-hint mb-3">Ресурсы</h3>
        <div className="grid grid-cols-2 gap-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-game-gold/20 flex items-center justify-center text-xl">
              💰
            </div>
            <div>
              <p className="font-bold">{MOCK_PROFILE.coins}</p>
              <p className="text-xs text-telegram-hint">Монеты</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-game-warning/20 flex items-center justify-center text-xl">
              🔥
            </div>
            <div>
              <p className="font-bold">{MOCK_PROFILE.winStreak}</p>
              <p className="text-xs text-telegram-hint">Серия побед</p>
            </div>
          </div>
        </div>
      </motion.div>

      {/* Duel Stats */}
      <motion.div
        initial={{ opacity: 0, x: 20 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ delay: 0.3 }}
        className="glass rounded-2xl p-4 mb-4"
      >
        <h3 className="text-sm text-telegram-hint mb-3">Статистика дуэлей</h3>
        <div className="grid grid-cols-4 gap-2 text-center mb-4">
          <div>
            <p className="text-xl font-bold text-game-success">{MOCK_PROFILE.stats.wins}</p>
            <p className="text-xs text-telegram-hint">Побед</p>
          </div>
          <div>
            <p className="text-xl font-bold text-game-danger">{MOCK_PROFILE.stats.losses}</p>
            <p className="text-xs text-telegram-hint">Поражений</p>
          </div>
          <div>
            <p className="text-xl font-bold text-telegram-hint">{MOCK_PROFILE.stats.draws}</p>
            <p className="text-xs text-telegram-hint">Ничьих</p>
          </div>
          <div>
            <p className="text-xl font-bold">{MOCK_PROFILE.stats.totalGames}</p>
            <p className="text-xs text-telegram-hint">Всего</p>
          </div>
        </div>
        
        {/* Win Rate */}
        <div className="pt-3 border-t border-white/10">
          <div className="flex justify-between items-center">
            <span className="text-sm text-telegram-hint">Процент побед</span>
            <span className="font-bold text-game-success">{MOCK_PROFILE.stats.winRate}%</span>
          </div>
          <div className="h-2 bg-white/10 rounded-full mt-2 overflow-hidden">
            <motion.div
              initial={{ width: 0 }}
              animate={{ width: `${MOCK_PROFILE.stats.winRate}%` }}
              transition={{ delay: 0.6, duration: 0.8 }}
              className="h-full bg-game-success rounded-full"
            />
          </div>
        </div>
      </motion.div>

      {/* True/False Stats */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
        className="glass rounded-2xl p-4"
      >
        <h3 className="text-sm text-telegram-hint mb-3">Правда или ложь</h3>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center text-2xl">
              🧠
            </div>
            <div>
              <p className="font-bold text-lg">{MOCK_PROFILE.trueFalse.record}</p>
              <p className="text-xs text-telegram-hint">Лучшая серия</p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-lg font-semibold">{MOCK_PROFILE.trueFalse.gamesPlayed}</p>
            <p className="text-xs text-telegram-hint">Игр сыграно</p>
          </div>
        </div>
      </motion.div>
    </div>
  )
}

export default ProfilePage

