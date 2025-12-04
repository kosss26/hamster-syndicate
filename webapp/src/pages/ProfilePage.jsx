import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton } from '../hooks/useTelegram'
import api from '../api/client'

function ProfilePage() {
  const { user } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    showBackButton(true)
    loadProfile()
  }, [])

  const loadProfile = async () => {
    try {
      setLoading(true)
      setError(null)
      
      // Отладка: проверяем initData
      const initData = window.Telegram?.WebApp?.initData
      console.log('initData:', initData)
      
      const response = await api.getProfile()
      console.log('API response:', response)
      
      if (response.success) {
        setProfile(response.data)
      } else {
        setError(`API error: ${response.error}`)
      }
    } catch (err) {
      console.error('Failed to load profile:', err)
      setError(`Ошибка: ${err.message}`)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <div className="w-12 h-12 border-4 border-game-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-telegram-hint">Загрузка...</p>
        </div>
      </div>
    )
  }

  if (error || !profile) {
    const initData = window.Telegram?.WebApp?.initData
    const tgUser = window.Telegram?.WebApp?.initDataUnsafe?.user
    
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <div className="text-center">
          <div className="text-4xl mb-4">😔</div>
          <p className="text-telegram-hint mb-4">{error || 'Профиль не найден'}</p>
          <div className="text-xs text-left bg-black/30 p-3 rounded-lg max-w-xs mx-auto">
            <p className="mb-2"><b>Debug info:</b></p>
            <p>TG User: {tgUser ? `${tgUser.first_name} (${tgUser.id})` : 'null'}</p>
            <p>initData: {initData ? `${initData.substring(0, 50)}...` : 'empty'}</p>
          </div>
          <button 
            onClick={loadProfile}
            className="mt-4 px-4 py-2 bg-game-primary rounded-lg"
          >
            Повторить
          </button>
        </div>
      </div>
    )
  }

  const totalGames = profile.stats.duel_wins + profile.stats.duel_losses + profile.stats.duel_draws
  const winRate = totalGames > 0 ? Math.round((profile.stats.duel_wins / totalGames) * 100) : 0

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
        <h1 className="text-xl font-bold text-white">{user?.first_name} {user?.last_name || ''}</h1>
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
            <p className="text-3xl font-bold text-game-primary">{profile.rating}</p>
          </div>
          <div className="text-right">
            <p className="text-telegram-hint text-sm">Ранг</p>
            <p className="text-xl font-semibold">{profile.rank}</p>
          </div>
        </div>
        
        {/* Progress to next rank */}
        <div>
          <div className="flex justify-between text-xs text-telegram-hint mb-1">
            <span>До следующего ранга</span>
            <span>—</span>
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
              <p className="font-bold">{profile.coins}</p>
              <p className="text-xs text-telegram-hint">Монеты</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-game-warning/20 flex items-center justify-center text-xl">
              🔥
            </div>
            <div>
              <p className="font-bold">{profile.win_streak}</p>
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
            <p className="text-xl font-bold text-game-success">{profile.stats.duel_wins}</p>
            <p className="text-xs text-telegram-hint">Побед</p>
          </div>
          <div>
            <p className="text-xl font-bold text-game-danger">{profile.stats.duel_losses}</p>
            <p className="text-xs text-telegram-hint">Поражений</p>
          </div>
          <div>
            <p className="text-xl font-bold text-telegram-hint">{profile.stats.duel_draws}</p>
            <p className="text-xs text-telegram-hint">Ничьих</p>
          </div>
          <div>
            <p className="text-xl font-bold">{totalGames}</p>
            <p className="text-xs text-telegram-hint">Всего</p>
          </div>
        </div>
        
        {/* Win Rate */}
        <div className="pt-3 border-t border-white/10">
          <div className="flex justify-between items-center">
            <span className="text-sm text-telegram-hint">Процент побед</span>
            <span className="font-bold text-game-success">{winRate}%</span>
          </div>
          <div className="h-2 bg-white/10 rounded-full mt-2 overflow-hidden">
            <motion.div
              initial={{ width: 0 }}
              animate={{ width: `${winRate}%` }}
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
        className="glass rounded-2xl p-4 mb-4"
      >
        <h3 className="text-sm text-telegram-hint mb-3">Правда или ложь</h3>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center text-2xl">
              🧠
            </div>
            <div>
              <p className="font-bold text-lg">{profile.true_false_record}</p>
              <p className="text-xs text-telegram-hint">Лучшая серия</p>
            </div>
          </div>
        </div>
      </motion.div>

      {/* Statistics Button */}
      <motion.button
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        onClick={() => navigate('/stats')}
        className="w-full glass rounded-2xl p-4 flex items-center justify-between group hover:bg-white/10 transition-colors"
      >
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-game-primary/30 to-purple-500/30 flex items-center justify-center text-2xl">
            📊
          </div>
          <div className="text-left">
            <p className="font-semibold text-white">Подробная статистика</p>
            <p className="text-xs text-telegram-hint">Сильные и слабые стороны, аналитика</p>
          </div>
        </div>
        <div className="text-telegram-hint group-hover:text-white transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
          </svg>
        </div>
      </motion.button>
    </div>
  )
}

export default ProfilePage

