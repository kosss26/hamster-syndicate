import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'

function ProfilePage() {
  const { user } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [showcasedAchievements, setShowcasedAchievements] = useState([])
  const [achievementStats, setAchievementStats] = useState(null)
  const [collections, setCollections] = useState([])

  useEffect(() => {
    showBackButton(true)
    loadProfile()
    loadAchievements()
    loadCollections()
  }, [])

  const loadProfile = async () => {
    try {
      setLoading(true)
      setError(null)
      const response = await api.getProfile()
      if (response.success) {
        setProfile(response.data)
      } else {
        setError(response.error || 'Ошибка API')
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const loadAchievements = async () => {
    try {
      const [showcasedRes, statsRes] = await Promise.all([
        api.getShowcasedAchievements(),
        api.getAchievementStats()
      ])
      if (showcasedRes.success) {
        setShowcasedAchievements(showcasedRes.data.showcased || [])
      }
      if (statsRes.success) {
        setAchievementStats(statsRes.data)
      }
    } catch (err) {
      console.error('Failed to load achievements:', err)
    }
  }

  const loadCollections = async () => {
    try {
      const response = await api.getCollections()
      if (response.success) {
        setCollections(response.data.collections || [])
      }
    } catch (err) {
      console.error('Failed to load collections:', err)
    }
  }

  const getRankDisplay = (rank) => {
    if (typeof rank === 'object') {
      return `${rank.emoji || ''} ${rank.name || ''}`
    }
    return rank
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/40">Загрузка профиля...</p>
        </div>
      </div>
    )
  }

  if (error || !profile) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-6">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            className="text-7xl mb-4"
          >
            😔
          </motion.div>
          <p className="text-white/50 mb-6">{error || 'Профиль не найден'}</p>
          <button 
            onClick={loadProfile}
            className="px-6 py-3 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl text-white font-medium shadow-glow"
          >
            Попробовать снова
          </button>
        </div>
      </div>
    )
  }

  const totalGames = (profile.stats?.duel_wins || 0) + (profile.stats?.duel_losses || 0) + (profile.stats?.duel_draws || 0)
  const winRate = totalGames > 0 ? Math.round((profile.stats?.duel_wins / totalGames) * 100) : 0

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden">
      {/* Aurora Background */}
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="aurora-blob aurora-blob-3" />
      <div className="noise-overlay" />

      <div className="relative z-10 p-4 pb-8">
        {/* Header with Avatar */}
        <motion.div 
          initial={{ opacity: 0, y: -30 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center pt-4 mb-6"
        >
          {/* Avatar with frame */}
          <div className="relative inline-block mb-4">
            <motion.div 
              className="relative"
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ type: "spring", stiffness: 200 }}
            >
              <AvatarWithFrame
                photoUrl={user?.photo_url}
                name={user?.first_name || 'User'}
                frameKey={profile?.equipped_frame || 'default'}
                size={112}
                animated={false}
                showGlow={true}
              />
              
              {/* Online indicator */}
              <div className="absolute bottom-1 right-1 w-6 h-6 bg-game-success rounded-full border-4 border-dark-950 shadow-glow-success" />
            </motion.div>
          </div>

          <motion.h1 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.2 }}
            className="text-2xl font-bold text-white mb-1"
          >
            {user?.first_name} {user?.last_name || ''}
          </motion.h1>
          
          {user?.username && (
            <motion.p 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.3 }}
              className="text-white/40 text-sm"
            >
              @{user.username}
            </motion.p>
          )}
        </motion.div>

        {/* Rating Card - Main */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="relative mb-4 overflow-hidden"
        >
          <div className="bento-card p-6">
            <div className="bento-glow bg-gradient-to-br from-game-primary/30 via-purple-500/20 to-transparent blur-2xl" />
            
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-white/40 text-xs uppercase tracking-wider mb-1">Рейтинг</p>
                <div className="flex items-baseline gap-2">
                  <span className="text-5xl font-bold text-gradient-primary">
                    {profile.rating}
                  </span>
                  <span className="text-white/30 text-sm">очков</span>
                </div>
              </div>
              <div className="text-right">
                <p className="text-white/40 text-xs uppercase tracking-wider mb-1">Ранг</p>
                <p className="text-xl font-semibold text-white">{getRankDisplay(profile.rank)}</p>
              </div>
            </div>
            
            {/* Progress bar */}
            <div className="relative mt-5">
              <div className="flex justify-between text-xs text-white/30 mb-2">
                <span>Прогресс</span>
                <span>До следующего ранга</span>
              </div>
              <div className="h-2 bg-white/5 rounded-full overflow-hidden">
                <motion.div
                  initial={{ width: 0 }}
                  animate={{ width: '65%' }}
                  transition={{ delay: 0.5, duration: 1 }}
                  className="h-full bg-gradient-to-r from-game-primary via-purple-500 to-game-pink rounded-full shadow-glow"
                />
              </div>
            </div>
          </div>
        </motion.div>

        {/* Duel Statistics */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="glass rounded-3xl p-5 mb-4"
        >
          <div className="flex items-center gap-2 mb-4">
            <span className="text-xl">⚔️</span>
            <h3 className="text-white/60 text-sm font-medium">Статистика дуэлей</h3>
          </div>
          
          <div className="grid grid-cols-4 gap-3 text-center mb-5">
            <div>
              <p className="text-2xl font-bold text-game-success">{profile.stats?.duel_wins || 0}</p>
              <p className="text-2xs text-white/30 uppercase tracking-wider">Побед</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-game-danger">{profile.stats?.duel_losses || 0}</p>
              <p className="text-2xs text-white/30 uppercase tracking-wider">Поражений</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-white/40">{profile.stats?.duel_draws || 0}</p>
              <p className="text-2xs text-white/30 uppercase tracking-wider">Ничьих</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-white">{totalGames}</p>
              <p className="text-2xs text-white/30 uppercase tracking-wider">Всего</p>
            </div>
          </div>

          {/* Win Rate Bar */}
          <div className="pt-4 border-t border-white/5">
            <div className="flex justify-between items-center mb-2">
              <span className="text-xs text-white/40">Процент побед</span>
              <span className="text-sm font-bold text-game-success">{winRate}%</span>
            </div>
            <div className="h-2 bg-white/5 rounded-full overflow-hidden">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${winRate}%` }}
                transition={{ delay: 0.6, duration: 1 }}
                className="h-full bg-gradient-to-r from-game-success to-emerald-400 rounded-full shadow-glow-success"
              />
            </div>
          </div>
        </motion.div>

        {/* True/False Record */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
          className="bento-card p-5 mb-4"
        >
          <div className="bento-glow bg-gradient-to-br from-purple-500/20 via-pink-500/10 to-transparent blur-2xl" />
          
          <div className="relative flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500/30 to-pink-500/30 flex items-center justify-center">
                <span className="text-3xl">🧠</span>
              </div>
              <div>
                <p className="text-white/40 text-xs uppercase tracking-wider">Правда или ложь</p>
                <p className="text-2xl font-bold text-white">Рекорд: {profile.true_false_record}</p>
              </div>
            </div>
            <motion.div 
              className="text-4xl"
              animate={{ rotate: [0, 10, -10, 0] }}
              transition={{ duration: 2, repeat: Infinity, repeatDelay: 3 }}
            >
              🏅
            </motion.div>
          </div>
        </motion.div>

        {/* Achievements Section */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
          className="glass rounded-3xl p-5 mb-4"
        >
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <span className="text-xl">🏆</span>
              <h3 className="text-white/60 text-sm font-medium">Достижения</h3>
            </div>
            <Link to="/achievements" className="text-xs text-game-primary hover:text-game-primary/80">
              Все →
            </Link>
          </div>

          {/* Stats Grid */}
          {achievementStats && (
            <div className="grid grid-cols-3 gap-3 mb-4">
              <div className="text-center">
                <p className="text-2xl font-bold text-white">{achievementStats.completed}</p>
                <p className="text-2xs text-white/30 uppercase tracking-wider">Получено</p>
              </div>
              <div className="text-center">
                <p className="text-2xl font-bold text-game-primary">{achievementStats.completion_percent}%</p>
                <p className="text-2xs text-white/30 uppercase tracking-wider">Прогресс</p>
              </div>
              <div className="text-center">
                <p className="text-2xl font-bold text-white">{achievementStats.total}</p>
                <p className="text-2xs text-white/30 uppercase tracking-wider">Всего</p>
              </div>
            </div>
          )}

          {/* Showcased Achievements */}
          {showcasedAchievements.length > 0 ? (
            <div className="space-y-2">
              <p className="text-xs text-white/40 mb-2">Витрина:</p>
              {showcasedAchievements.slice(0, 3).map((achievement) => (
                <div 
                  key={achievement.id}
                  className="bg-white/5 rounded-xl p-3 flex items-center gap-3"
                >
                  <div className="text-2xl">{achievement.icon}</div>
                  <div className="flex-1">
                    <h4 className="text-sm font-semibold text-white">{achievement.title}</h4>
                    <p className="text-xs text-white/40">{achievement.description}</p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-4 text-white/30 text-sm">
              Получите достижения, чтобы показать их здесь
            </div>
          )}
        </motion.div>

        {/* Collections Section */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.7 }}
          className="bento-card p-5 mb-4"
        >
          <div className="bento-glow bg-gradient-to-br from-pink-500/20 via-purple-500/10 to-transparent blur-2xl" />
          
          <div className="relative">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <span className="text-xl">📚</span>
                <h3 className="text-white/60 text-sm font-medium">Коллекции</h3>
              </div>
              <Link to="/collections" className="text-xs text-game-primary hover:text-game-primary/80">
                Все →
              </Link>
            </div>

            {collections.length > 0 ? (
              <div className="grid grid-cols-2 gap-3">
                {collections.slice(0, 2).map((collection) => {
                  const progress = collection.progress_percent || 0
                  return (
                    <Link 
                      key={collection.id}
                      to={`/collections/${collection.id}`}
                      className="bg-white/5 rounded-xl p-3 hover:bg-white/10 transition-colors"
                    >
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-2xl">{collection.icon}</span>
                        <div className="flex-1">
                          <h4 className="text-xs font-semibold text-white line-clamp-1">{collection.title}</h4>
                          <p className="text-2xs text-white/40">{collection.owned_items}/{collection.total_items}</p>
                        </div>
                      </div>
                      <div className="h-1 bg-white/10 rounded-full overflow-hidden">
                        <div 
                          className="h-full bg-gradient-to-r from-purple-500 to-pink-500"
                          style={{ width: `${progress}%` }}
                        />
                      </div>
                    </Link>
                  )
                })}
              </div>
            ) : (
              <div className="text-center py-4 text-white/30 text-sm">
                Коллекции скоро появятся
              </div>
            )}
          </div>
        </motion.div>

        {/* Detailed Stats Button */}
        <motion.button
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
          onClick={() => navigate('/stats')}
          className="w-full bento-card p-5 flex items-center justify-between group"
        >
          <div className="bento-glow bg-gradient-to-br from-game-primary/20 via-purple-500/10 to-transparent blur-2xl" />
          
          <div className="relative flex items-center gap-4">
            <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-game-primary/30 to-purple-500/30 flex items-center justify-center group-hover:scale-110 transition-transform">
              <span className="text-3xl">📊</span>
            </div>
            <div className="text-left">
              <p className="font-semibold text-white">Подробная статистика</p>
              <p className="text-xs text-white/40">Аналитика по категориям</p>
            </div>
          </div>
          
          <div className="relative text-white/30 group-hover:text-white/60 group-hover:translate-x-1 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
            </svg>
          </div>
        </motion.button>
      </div>
    </div>
  )
}

function StatBox({ icon, value, label, gradient, glowColor }) {
  return (
    <motion.div 
      className="relative overflow-hidden rounded-2xl p-4 text-center"
      whileHover={{ scale: 1.05 }}
      transition={{ type: "spring", stiffness: 400 }}
    >
      <div className={`absolute inset-0 bg-gradient-to-br ${gradient}`} />
      <div className="absolute inset-0 glass" />
      
      <div className="relative">
        <span className="text-2xl">{icon}</span>
        <p className={`text-2xl font-bold text-white mt-1`}>{value}</p>
        <p className="text-2xs text-white/40 uppercase tracking-wider">{label}</p>
      </div>
    </motion.div>
  )
}

export default ProfilePage
