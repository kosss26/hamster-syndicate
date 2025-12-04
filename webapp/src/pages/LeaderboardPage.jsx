import { useState, useEffect } from 'react'
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
  
  // Найти позицию текущего пользователя
  const currentUserPosition = data.findIndex(p => p.username === user?.username) + 1
  const currentUserData = data.find(p => p.username === user?.username)

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-game flex items-center justify-center">
        <div className="text-center">
          <div className="w-12 h-12 border-3 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-white/50 text-sm">Загрузка рейтинга...</p>
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

  const getPositionStyle = (position) => {
    if (position === 1) return 'from-yellow-500/30 to-amber-500/20 border-yellow-500/40'
    if (position === 2) return 'from-gray-400/30 to-gray-500/20 border-gray-400/40'
    if (position === 3) return 'from-amber-600/30 to-orange-600/20 border-amber-600/40'
    return 'from-white/5 to-white/5 border-white/10'
  }

  return (
    <div className="min-h-screen bg-gradient-game">
      {/* Background decorations */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[500px] h-[250px] bg-gradient-to-b from-indigo-500/10 to-transparent rounded-full blur-3xl"></div>
      </div>

      <div className="relative z-10 p-4 pb-8">
        {/* Header */}
        <div className="text-center pt-4 mb-6">
          <div className="text-4xl mb-2">🏆</div>
          <h1 className="text-2xl font-bold text-white mb-1">Рейтинг</h1>
          <p className="text-white/40 text-sm">Лучшие игроки</p>
        </div>

        {/* Tabs */}
        <div className="flex p-1 bg-white/5 rounded-xl mb-6">
          <button
            onClick={() => handleTabChange('duel')}
            className={`flex-1 py-3 px-4 rounded-lg font-medium text-sm transition-all ${
              activeTab === 'duel'
                ? 'bg-gradient-to-r from-indigo-500 to-purple-500 text-white shadow-lg shadow-indigo-500/25'
                : 'text-white/50 hover:text-white/70'
            }`}
          >
            ⚔️ Дуэли
          </button>
          <button
            onClick={() => handleTabChange('truefalse')}
            className={`flex-1 py-3 px-4 rounded-lg font-medium text-sm transition-all ${
              activeTab === 'truefalse'
                ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-lg shadow-purple-500/25'
                : 'text-white/50 hover:text-white/70'
            }`}
          >
            🧠 Правда/Ложь
          </button>
        </div>

        {/* Leaderboard List */}
        <div className="space-y-2">
          {data.map((player, index) => {
            const position = index + 1
            const isCurrentUser = player.username === user?.username
            const medal = getMedalEmoji(position)
            const isTop3 = position <= 3
            
            return (
              <div
                key={player.username || index}
                className={`rounded-xl p-3 flex items-center gap-3 border transition-all ${
                  isCurrentUser 
                    ? 'ring-2 ring-indigo-500 bg-indigo-500/15 border-indigo-500/30' 
                    : `bg-gradient-to-r ${getPositionStyle(position)} ${isTop3 ? 'border' : 'border-transparent'}`
                }`}
                style={{ backdropFilter: 'blur(10px)' }}
              >
                {/* Position */}
                <div className={`w-9 h-9 rounded-lg flex items-center justify-center font-bold ${
                  isTop3 
                    ? 'text-xl' 
                    : 'bg-white/5 text-white/50 text-sm'
                }`}>
                  {medal || position}
                </div>

                {/* Avatar */}
                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white ${
                  position === 1 ? 'bg-gradient-to-br from-yellow-400 to-amber-500' :
                  position === 2 ? 'bg-gradient-to-br from-gray-300 to-gray-400 text-gray-700' :
                  position === 3 ? 'bg-gradient-to-br from-amber-500 to-orange-600' :
                  'bg-gradient-to-br from-indigo-500/60 to-purple-500/60'
                }`}>
                  {player.name?.[0]?.toUpperCase() || '?'}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                  <p className={`font-medium text-sm truncate ${isTop3 ? 'text-white' : 'text-white/90'}`}>
                    {player.name}
                    {isCurrentUser && (
                      <span className="ml-2 text-[10px] text-indigo-400 font-normal bg-indigo-500/20 px-2 py-0.5 rounded-full">Ты</span>
                    )}
                  </p>
                  {/* Username только для админов */}
                  {isAdmin && player.username && (
                    <p className="text-white/30 text-xs truncate">@{player.username}</p>
                  )}
                </div>

                {/* Score */}
                <div className="text-right">
                  <p className={`font-bold ${
                    position === 1 ? 'text-yellow-400 text-xl' :
                    position === 2 ? 'text-gray-300 text-lg' :
                    position === 3 ? 'text-amber-400 text-lg' :
                    activeTab === 'duel' ? 'text-indigo-400' : 'text-purple-400'
                  }`}>
                    {activeTab === 'duel' ? player.rating : player.record}
                  </p>
                  <p className="text-[10px] text-white/30">
                    {activeTab === 'duel' 
                      ? (typeof player.rank === 'object' ? player.rank.name : player.rank?.split?.(' ')[0] || '')
                      : 'серия'
                    }
                  </p>
                </div>
              </div>
            )
          })}
        </div>

        {data.length === 0 && !loading && (
          <div className="text-center py-12">
            <div className="text-4xl mb-3">🏜️</div>
            <p className="text-white/40">Пока нет данных</p>
          </div>
        )}

        {/* Current user position card */}
        {currentUserPosition > 0 && (
          <div className="mt-6 glass rounded-xl p-4 border border-indigo-500/20">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-lg font-bold text-white">
                  {user?.first_name?.[0]?.toUpperCase() || '?'}
                </div>
                <div>
                  <p className="font-medium text-white">{user?.first_name || 'Ты'}</p>
                  <p className="text-xs text-white/40">Твоя позиция в рейтинге</p>
                </div>
              </div>
              <div className="text-right">
                <p className="text-3xl font-bold text-indigo-400">#{currentUserPosition}</p>
                <p className="text-xs text-white/30">
                  {activeTab === 'duel' 
                    ? `${currentUserData?.rating || 0} очков` 
                    : `${currentUserData?.record || 0} серия`
                  }
                </p>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default LeaderboardPage
