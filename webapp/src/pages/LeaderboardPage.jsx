import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

// Telegram ID администраторов
const ADMIN_IDS = [1763619724]

function LeaderboardPage() {
  const { user } = useTelegram()
  const [activeTab, setActiveTab] = useState('duel')
  const [leaderboard, setLeaderboard] = useState({ duel: [], truefalse: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const isAdmin = user && ADMIN_IDS.includes(user.id)

  useEffect(() => {
    showBackButton(true)
    loadLeaderboard('duel')
    loadLeaderboard('truefalse')
  }, [])

  const loadLeaderboard = async (type) => {
    try {
      const response = await api.getLeaderboard(type)
      if (response.success) {
        setLeaderboard(prev => ({
          ...prev,
          [type]: response.data.players || []
        }))
      } else {
        setError(response.error)
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleTabChange = (tabId) => {
    setActiveTab(tabId)
    hapticFeedback('light')
  }

  const data = activeTab === 'duel' ? leaderboard.duel : leaderboard.truefalse
  const currentUserPosition = data.findIndex(p => p.username === user?.username) + 1
  const currentUserData = data.find(p => p.username === user?.username)

  if (loading) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/40">Загрузка рейтинга...</p>
        </div>
      </div>
    )
  }

  const getMedalEmoji = (position) => {
    if (position === 1) return '🥇'
    if (position === 2) return '🥈'
    if (position === 3) return '🥉'
    return null
  }

  const getPositionGradient = (position) => {
    if (position === 1) return 'from-yellow-500/20 via-amber-500/10 to-transparent'
    if (position === 2) return 'from-gray-400/20 via-gray-500/10 to-transparent'
    if (position === 3) return 'from-amber-600/20 via-orange-600/10 to-transparent'
    return ''
  }

  const getAvatarGradient = (position) => {
    if (position === 1) return 'from-yellow-400 to-amber-500'
    if (position === 2) return 'from-gray-300 to-gray-400'
    if (position === 3) return 'from-amber-500 to-orange-600'
    return 'from-game-primary/60 to-purple-500/60'
  }

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden">
      {/* Aurora Background */}
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="aurora-blob aurora-blob-4" />
      <div className="noise-overlay" />

      <div className="relative z-10 p-4 pb-8">
        {/* Header */}
        <motion.div 
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center pt-4 mb-6"
        >
          <motion.div 
            className="text-5xl mb-3"
            animate={{ 
              rotate: [0, -10, 10, -10, 0],
              scale: [1, 1.1, 1]
            }}
            transition={{ duration: 2, repeat: Infinity, repeatDelay: 3 }}
          >
            🏆
          </motion.div>
          <h1 className="text-3xl font-bold text-gradient-gold mb-1">Рейтинг</h1>
          <p className="text-white/40 text-sm">Лучшие игроки</p>
        </motion.div>

        {/* Tabs */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="flex p-1.5 glass rounded-2xl mb-6"
        >
          <button
            onClick={() => handleTabChange('duel')}
            className={`flex-1 py-3 px-4 rounded-xl font-medium text-sm transition-all ${
              activeTab === 'duel'
                ? 'bg-gradient-to-r from-game-primary to-purple-500 text-white shadow-glow'
                : 'text-white/40 hover:text-white/60'
            }`}
          >
            ⚔️ Дуэли
          </button>
          <button
            onClick={() => handleTabChange('truefalse')}
            className={`flex-1 py-3 px-4 rounded-xl font-medium text-sm transition-all ${
              activeTab === 'truefalse'
                ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-glow'
                : 'text-white/40 hover:text-white/60'
            }`}
          >
            🧠 Правда/Ложь
          </button>
        </motion.div>

        {/* Leaderboard List */}
        <div className="space-y-2">
          <AnimatePresence mode="wait">
            {data.map((player, index) => {
              const position = index + 1
              const isCurrentUser = player.username === user?.username
              const medal = getMedalEmoji(position)
              const isTop3 = position <= 3
              
              return (
                <motion.div
                  key={player.username || index}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.05 }}
                  className={`relative rounded-2xl p-4 flex items-center gap-3 transition-all ${
                    isCurrentUser 
                      ? 'ring-2 ring-game-primary bg-game-primary/10' 
                      : isTop3 
                        ? 'bento-card' 
                        : 'glass'
                  }`}
                >
                  {/* Top 3 glow */}
                  {isTop3 && (
                    <div className={`bento-glow bg-gradient-to-br ${getPositionGradient(position)} blur-2xl`} />
                  )}

                  {/* Position */}
                  <div className={`relative w-10 h-10 rounded-xl flex items-center justify-center font-bold ${
                    isTop3 
                      ? 'text-2xl' 
                      : 'bg-white/5 text-white/40 text-sm'
                  }`}>
                    {medal || position}
                  </div>

                  {/* Avatar */}
                  <div className={`relative w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold text-white bg-gradient-to-br ${getAvatarGradient(position)} ${
                    position === 2 ? 'text-gray-700' : ''
                  } overflow-hidden`}>
                    {isTop3 && (
                      <div className={`absolute -inset-1 rounded-full bg-gradient-to-br ${getAvatarGradient(position)} opacity-30 blur-md`} />
                    )}
                    {player.photo_url ? (
                      <img 
                        src={player.photo_url} 
                        alt={player.name}
                        className="absolute inset-0 w-full h-full object-cover"
                      />
                    ) : (
                      <span className="relative">{player.name?.[0]?.toUpperCase() || '?'}</span>
                    )}
                  </div>

                  {/* Info */}
                  <div className="relative flex-1 min-w-0">
                    <p className={`font-medium text-sm truncate ${isTop3 ? 'text-white' : 'text-white/80'}`}>
                      {player.name}
                      {isCurrentUser && (
                        <span className="ml-2 text-2xs text-game-primary font-normal bg-game-primary/20 px-2 py-0.5 rounded-full">
                          Ты
                        </span>
                      )}
                    </p>
                    {isAdmin && player.username && (
                      <p className="text-white/30 text-xs truncate">@{player.username}</p>
                    )}
                  </div>

                  {/* Score */}
                  <div className="relative text-right">
                    <p className={`font-bold ${
                      position === 1 ? 'text-yellow-400 text-xl' :
                      position === 2 ? 'text-gray-300 text-lg' :
                      position === 3 ? 'text-amber-400 text-lg' :
                      activeTab === 'duel' ? 'text-game-primary' : 'text-purple-400'
                    }`}>
                      {activeTab === 'duel' ? player.rating : player.record}
                    </p>
                    <p className="text-2xs text-white/30">
                      {activeTab === 'duel' 
                        ? (typeof player.rank === 'object' ? player.rank.name : player.rank?.split?.(' ')[0] || '')
                        : 'серия'
                      }
                    </p>
                  </div>
                </motion.div>
              )
            })}
          </AnimatePresence>
        </div>

        {data.length === 0 && !loading && (
          <motion.div 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="text-center py-12"
          >
            <div className="text-5xl mb-4">🏜️</div>
            <p className="text-white/40">Пока нет данных</p>
          </motion.div>
        )}

        {/* Current user position card */}
        {currentUserPosition > 0 && (
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="mt-6 bento-card p-5"
          >
            <div className="bento-glow bg-gradient-to-br from-game-primary/20 via-purple-500/10 to-transparent blur-2xl" />
            
            <div className="relative flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 rounded-full bg-gradient-to-br from-game-primary to-purple-600 flex items-center justify-center text-xl font-bold text-white shadow-glow">
                  {user?.first_name?.[0]?.toUpperCase() || '?'}
                </div>
                <div>
                  <p className="font-medium text-white">{user?.first_name || 'Ты'}</p>
                  <p className="text-xs text-white/40">Твоя позиция в рейтинге</p>
                </div>
              </div>
              <div className="text-right">
                <p className="text-4xl font-bold text-gradient-primary">#{currentUserPosition}</p>
                <p className="text-xs text-white/30">
                  {activeTab === 'duel' 
                    ? `${currentUserData?.rating || 0} очков` 
                    : `${currentUserData?.record || 0} серия`
                  }
                </p>
              </div>
            </div>
          </motion.div>
        )}
      </div>
    </div>
  )
}

export default LeaderboardPage
