import { useState, useEffect } from 'react'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

function LeaderboardPage() {
  const { user } = useTelegram()
  const [activeTab, setActiveTab] = useState('duel')
  const [leaderboard, setLeaderboard] = useState({ duel: [], truefalse: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

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

  return (
    <div className="min-h-screen bg-gradient-game">
      {/* Background decorations */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-gradient-to-b from-yellow-500/10 to-transparent rounded-full blur-3xl"></div>
      </div>

      <div className="relative z-10 p-4 pb-8">
        {/* Header */}
        <div className="text-center pt-4 mb-6">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-yellow-500/20 to-amber-500/20 mb-3">
            <span className="text-4xl">🏆</span>
          </div>
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
            <span className="mr-2">⚔️</span>
            Дуэли
          </button>
          <button
            onClick={() => handleTabChange('truefalse')}
            className={`flex-1 py-3 px-4 rounded-lg font-medium text-sm transition-all ${
              activeTab === 'truefalse'
                ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-lg shadow-purple-500/25'
                : 'text-white/50 hover:text-white/70'
            }`}
          >
            <span className="mr-2">🧠</span>
            Правда/Ложь
          </button>
        </div>

        {/* Top 3 Podium */}
        {data.length >= 3 && (
          <div className="flex items-end justify-center gap-2 mb-6 px-2">
            {/* 2nd place */}
            <div className="flex-1 max-w-[100px]">
              <div className="text-center mb-2">
                <div className="w-14 h-14 mx-auto rounded-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center text-xl font-bold text-gray-700 border-2 border-gray-300/50">
                  {data[1]?.name?.[0] || '?'}
                </div>
                <p className="text-white text-xs font-medium mt-1 truncate">{data[1]?.name}</p>
                <p className="text-white/40 text-[10px]">
                  {activeTab === 'duel' ? data[1]?.rating : data[1]?.record}
                </p>
              </div>
              <div className="h-16 bg-gradient-to-t from-gray-400/30 to-gray-300/30 rounded-t-lg flex items-center justify-center">
                <span className="text-2xl">🥈</span>
              </div>
            </div>

            {/* 1st place */}
            <div className="flex-1 max-w-[110px]">
              <div className="text-center mb-2">
                <div className="relative">
                  <div className="w-18 h-18 mx-auto rounded-full bg-gradient-to-br from-yellow-400 to-amber-500 flex items-center justify-center text-2xl font-bold text-yellow-900 border-3 border-yellow-300/50 shadow-lg shadow-yellow-500/30"
                       style={{ width: '72px', height: '72px' }}>
                    {data[0]?.name?.[0] || '?'}
                  </div>
                  <div className="absolute -top-2 left-1/2 -translate-x-1/2">
                    <span className="text-lg">👑</span>
                  </div>
                </div>
                <p className="text-white text-sm font-semibold mt-1 truncate">{data[0]?.name}</p>
                <p className="text-yellow-400/80 text-xs font-medium">
                  {activeTab === 'duel' ? data[0]?.rating : data[0]?.record}
                </p>
              </div>
              <div className="h-24 bg-gradient-to-t from-yellow-500/30 to-amber-400/30 rounded-t-lg flex items-center justify-center">
                <span className="text-3xl">🥇</span>
              </div>
            </div>

            {/* 3rd place */}
            <div className="flex-1 max-w-[100px]">
              <div className="text-center mb-2">
                <div className="w-14 h-14 mx-auto rounded-full bg-gradient-to-br from-amber-600 to-orange-700 flex items-center justify-center text-xl font-bold text-amber-100 border-2 border-amber-500/50">
                  {data[2]?.name?.[0] || '?'}
                </div>
                <p className="text-white text-xs font-medium mt-1 truncate">{data[2]?.name}</p>
                <p className="text-white/40 text-[10px]">
                  {activeTab === 'duel' ? data[2]?.rating : data[2]?.record}
                </p>
              </div>
              <div className="h-12 bg-gradient-to-t from-amber-700/30 to-orange-600/30 rounded-t-lg flex items-center justify-center">
                <span className="text-2xl">🥉</span>
              </div>
            </div>
          </div>
        )}

        {/* Leaderboard List */}
        <div className="space-y-2">
          {data.slice(3).map((player, index) => {
            const position = index + 4
            const isCurrentUser = player.username === user?.username
            
            return (
              <div
                key={player.username || index}
                className={`glass rounded-xl p-3 flex items-center gap-3 ${
                  isCurrentUser ? 'ring-2 ring-indigo-500/50 bg-indigo-500/10' : ''
                }`}
              >
                {/* Position */}
                <div className="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-sm font-bold text-white/60">
                  {position}
                </div>

                {/* Avatar */}
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500/50 to-purple-500/50 flex items-center justify-center text-sm font-bold text-white">
                  {player.name?.[0] || '?'}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-white text-sm truncate">
                    {player.name}
                    {isCurrentUser && (
                      <span className="ml-2 text-[10px] text-indigo-400 font-normal">• Ты</span>
                    )}
                  </p>
                  <p className="text-white/30 text-xs truncate">@{player.username}</p>
                </div>

                {/* Score */}
                <div className="text-right">
                  <p className={`font-bold text-lg ${
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

        {/* Current user position (if not in top) */}
        <div className="mt-6 glass rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-lg font-bold text-white">
                {user?.first_name?.[0] || '?'}
              </div>
              <div>
                <p className="font-medium text-white">{user?.first_name || 'Ты'}</p>
                <p className="text-xs text-white/40">Твоя позиция</p>
              </div>
            </div>
            <div className="text-right">
              <p className="text-3xl font-bold text-white">#—</p>
              <p className="text-[10px] text-white/30">из {data.length || '—'}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default LeaderboardPage
