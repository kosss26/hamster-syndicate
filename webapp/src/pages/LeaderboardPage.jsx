import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'

// Моковые данные рейтинга
const MOCK_LEADERBOARD = {
  duel: [
    { position: 1, name: 'Александр', username: 'alex_quiz', rating: 2150, rank: '🌟 Иммортал' },
    { position: 2, name: 'Мария', username: 'masha_brain', rating: 1890, rank: '👑 Легенда' },
    { position: 3, name: 'Дмитрий', username: 'dima_smart', rating: 1720, rank: '💎 Элита' },
    { position: 4, name: 'Елена', username: 'lena_quiz', rating: 1580, rank: '⭐⭐⭐ Гранд-мастер' },
    { position: 5, name: 'Иван', username: 'ivan123', rating: 1450, rank: '⭐⭐ Мастер' },
    { position: 6, name: 'Анна', username: 'anna_genius', rating: 1380, rank: '⭐⭐ Мастер' },
    { position: 7, name: 'Сергей', username: 'sergey_pro', rating: 1250, rank: '⭐ Эксперт' },
    { position: 8, name: 'Ольга', username: 'olga_wise', rating: 1180, rank: '⭐ Эксперт' },
    { position: 9, name: 'Николай', username: 'kolya_fast', rating: 1050, rank: '⭐ Эксперт' },
    { position: 10, name: 'Татьяна', username: 'tanya_quiz', rating: 980, rank: '🎓 Студент' },
  ],
  truefalse: [
    { position: 1, name: 'Мария', username: 'masha_brain', record: 28 },
    { position: 2, name: 'Александр', username: 'alex_quiz', record: 25 },
    { position: 3, name: 'Елена', username: 'lena_quiz', record: 22 },
    { position: 4, name: 'Дмитрий', username: 'dima_smart', record: 19 },
    { position: 5, name: 'Иван', username: 'ivan123', record: 17 },
    { position: 6, name: 'Анна', username: 'anna_genius', record: 15 },
    { position: 7, name: 'Сергей', username: 'sergey_pro', record: 14 },
    { position: 8, name: 'Ольга', username: 'olga_wise', record: 12 },
    { position: 9, name: 'Николай', username: 'kolya_fast', record: 11 },
    { position: 10, name: 'Татьяна', username: 'tanya_quiz', record: 10 },
  ]
}

const TABS = [
  { id: 'duel', label: 'Дуэли', icon: '⚔️' },
  { id: 'truefalse', label: 'Правда/Ложь', icon: '🧠' }
]

function LeaderboardPage() {
  const { user } = useTelegram()
  const [activeTab, setActiveTab] = useState('duel')

  useEffect(() => {
    showBackButton(true)
  }, [])

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

  const data = activeTab === 'duel' ? MOCK_LEADERBOARD.duel : MOCK_LEADERBOARD.truefalse

  return (
    <div className="min-h-screen p-4 pb-8">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center pt-4 mb-6"
      >
        <div className="text-4xl mb-2">🏆</div>
        <h1 className="text-2xl font-bold">Рейтинг</h1>
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
                <p className="font-semibold truncate">
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
              <p className="font-semibold">{user?.first_name || 'Ты'}</p>
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

