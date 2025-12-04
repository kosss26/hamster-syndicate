import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'

const TABS = [
  { id: 'duel', label: 'Дуэли', icon: '⚔️' },
  { id: 'truefalse', label: 'Правда/Ложь', icon: '🧠' }
]

function LeaderboardPage() {
  const { user } = useTelegram()
  const [activeTab, setActiveTab] = useState('duel')
  const [leaderboard, setLeaderboard] = useState({ duel: [], truefalse: [] })
  const [loading, setLoading] = useState(true)

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
          [type]: response.data.players
        }))
      }
    } catch (err) {
      console.error('Failed to load leaderboard:', err)
    } finally {
      setLoading(false)
    }
  }

  const handleTabChange = (tabId) => {
    setActiveTab(tabId)
    hapticFeedback('light')
  }

  const getPositionStyle = (position) => {
    switch (position) {
      case 1:
        return 'bg-gradient-to-r from-game-gold to-yellow-600 text-black'
      case 2:
        return 'bg-gradient-to-r from-gray-300 to-gray-400 text-black'
      case 3:
        return 'bg-gradient-to-r from-game-bronze to-orange-700 text-white'
      default:
        return 'bg-white/10'
    }
  }

  const getPositionIcon = (position) => {
    switch (position) {
      case 1:
        return '🥇'
      case 2:
        return '🥈'
      case 3:
        return '🥉'
      default:
        return position
    }
  }

  const data = activeTab === 'duel' ? leaderboard.duel : leaderboard.truefalse

  return (
    <div className="min-h-screen p-4 pb-8">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center pt-4 mb-6"
      >
        <div className="text-4xl mb-2">🏆</div>
        <h1 className="text-2xl font-bold text-white">Рейтинг</h1>
        <p className="text-telegram-hint">Лучшие игроки</p>
      </motion.div>

      {/* Tabs */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="flex gap-2 mb-6"
      >
        {TABS.map((tab) => (
          <button
            key={tab.id}
            onClick={() => handleTabChange(tab.id)}
            className={`flex-1 py-3 px-4 rounded-xl font-medium transition-all ${
              activeTab === tab.id
                ? 'bg-game-primary text-white'
                : 'bg-white/10 text-telegram-hint'
            }`}
          >
            <span className="mr-2">{tab.icon}</span>
            {tab.label}
          </button>
        ))}
      </motion.div>

      {/* Leaderboard */}
      <AnimatePresence mode="wait">
        <motion.div
          key={activeTab}
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          exit={{ opacity: 0, x: -20 }}
          className="space-y-2"
        >
          {data.map((player, index) => (
            <motion.div
              key={player.username}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.05 }}
              className={`glass rounded-xl p-3 flex items-center gap-3 ${
                player.username === user?.username ? 'ring-2 ring-game-primary' : ''
              }`}
            >
              {/* Position */}
              <div className={`w-10 h-10 rounded-lg flex items-center justify-center font-bold ${getPositionStyle(player.position)}`}>
                {getPositionIcon(player.position)}
              </div>

              {/* Player Info */}
              <div className="flex-1 min-w-0">
                <p className="font-semibold truncate text-white">
                  {player.name}
                  {player.username === user?.username && (
                    <span className="ml-2 text-game-primary text-xs">• Ты</span>
                  )}
                </p>
                <p className="text-sm text-telegram-hint truncate">
                  @{player.username}
                </p>
              </div>

              {/* Score */}
              <div className="text-right">
                {activeTab === 'duel' ? (
                  <>
                    <p className="font-bold text-game-primary">{player.rating}</p>
                    <p className="text-xs text-telegram-hint">{player.rank?.split(' ')[0]}</p>
                  </>
                ) : (
                  <>
                    <p className="font-bold text-purple-400">{player.record}</p>
                    <p className="text-xs text-telegram-hint">серия</p>
                  </>
                )}
              </div>
            </motion.div>
          ))}
        </motion.div>
      </AnimatePresence>

      {/* Your Position */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.6 }}
        className="mt-6 glass rounded-xl p-4"
      >
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-game-primary flex items-center justify-center font-bold">
              {user?.first_name?.[0] || '?'}
            </div>
            <div>
              <p className="font-semibold text-white">{user?.first_name || 'Ты'}</p>
              <p className="text-sm text-telegram-hint">Твоя позиция</p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-2xl font-bold">#42</p>
            <p className="text-xs text-telegram-hint">из 1,234</p>
          </div>
        </div>
      </motion.div>
    </div>
  )
}

export default LeaderboardPage

