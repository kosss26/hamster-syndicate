import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'

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
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    try {
      const [duelRes, tfRes] = await Promise.all([
        api.getLeaderboard('duel'),
        api.getLeaderboard('truefalse')
      ])

      setLeaderboard({
        duel: duelRes.success ? duelRes.data.players || [] : [],
        truefalse: tfRes.success ? tfRes.data.players || [] : []
      })
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleTabChange = (tabId) => {
    if (activeTab === tabId) return
    setActiveTab(tabId)
    hapticFeedback('light')
  }

  const data = activeTab === 'duel' ? leaderboard.duel : leaderboard.truefalse
  
  // Find current user in the list (by username as ID might not be exposed or reliable for matching from WebApp user)
  // Or better by comparing telegram_id if available, but here we use what we have.
  // Ideally backend should return `is_me` flag. 
  // Let's assume username match or we can't identify me in the list easily without ID.
  // Actually api.php returns `username`.
  const currentUserIndex = data.findIndex(p => p.username === user?.username)
  const currentUserData = currentUserIndex !== -1 ? data[currentUserIndex] : null
  const currentUserPosition = currentUserIndex !== -1 ? currentUserIndex + 1 : null

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="spinner" />
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col">
      <div className="aurora-blob aurora-blob-1 opacity-50" />
      <div className="aurora-blob aurora-blob-2 opacity-50" />
      <div className="noise-overlay" />

      {/* Header */}
      <div className="relative z-10 px-6 pt-[calc(1.5rem+env(safe-area-inset-top))] pb-4 text-center">
        <h1 className="text-3xl font-black italic uppercase text-white tracking-wider text-shadow-glow">
          Зал Славы
        </h1>
        <p className="text-white/60 text-sm mt-1">Лучшие из лучших</p>
      </div>

      {/* Tabs */}
      <div className="relative z-10 px-6 mb-6 shrink-0">
        <div className="bg-white/10 p-1 rounded-2xl flex relative backdrop-blur-md border border-white/5">
          <div 
            className="absolute inset-y-1 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl shadow-lg transition-all duration-300 ease-out"
            style={{
                left: activeTab === 'duel' ? '4px' : '50%',
                width: 'calc(50% - 4px)'
            }}
          />
          <button
            onClick={() => handleTabChange('duel')}
            className={`relative z-10 flex-1 py-3 text-sm font-bold transition-colors ${activeTab === 'duel' ? 'text-white' : 'text-white/60 hover:text-white'}`}
          >
            ⚔️ Дуэли
          </button>
          <button
            onClick={() => handleTabChange('truefalse')}
            className={`relative z-10 flex-1 py-3 text-sm font-bold transition-colors ${activeTab === 'truefalse' ? 'text-white' : 'text-white/60 hover:text-white'}`}
          >
            🧠 Правда или ложь
          </button>
        </div>
      </div>

      {/* Scrollable Content */}
      <div className="flex-1 overflow-y-auto px-6 pb-32 relative z-10 custom-scrollbar">
        {data.length === 0 ? (
            <div className="text-center py-12 opacity-50">
                <div className="text-6xl mb-4">🏜️</div>
                <p>Список пуст</p>
            </div>
        ) : (
            <>
                {/* Podium */}
                <div className="flex items-end justify-center gap-2 mb-8 min-h-[200px]">
                    {/* 2nd Place */}
                    <PodiumItem 
                        player={data[1]} 
                        place={2} 
                        activeTab={activeTab} 
                        delay={0.2}
                    />
                    {/* 1st Place */}
                    <PodiumItem 
                        player={data[0]} 
                        place={1} 
                        activeTab={activeTab} 
                        delay={0}
                    />
                    {/* 3rd Place */}
                    <PodiumItem 
                        player={data[2]} 
                        place={3} 
                        activeTab={activeTab} 
                        delay={0.4}
                    />
                </div>

                {/* List 4+ */}
                <div className="space-y-3">
                    {data.slice(3).map((player, idx) => (
                        <RankItem 
                            key={idx} 
                            player={player} 
                            place={idx + 4} 
                            activeTab={activeTab}
                            isMe={player.username === user?.username}
                            isAdmin={isAdmin}
                        />
                    ))}
                </div>
            </>
        )}
      </div>

      {/* Sticky User Rank (if not in top 3 visible) */}
      {currentUserData && currentUserPosition > 3 && (
         <div className="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] left-4 right-4 z-30">
             <div className="relative overflow-hidden rounded-2xl bg-[#0f172a]/90 backdrop-blur-xl border-t border-game-primary/50 shadow-[0_-4px_20px_rgba(0,0,0,0.3)] p-4 flex items-center gap-4">
                 <div className="absolute inset-0 bg-game-primary/10 animate-pulse-slow pointer-events-none" />
                 
                 <div className="w-10 h-10 flex items-center justify-center font-black text-white/40 bg-white/5 rounded-xl text-sm border border-white/10 shrink-0">
                     #{currentUserPosition}
                 </div>
                 
                 <div className="relative shrink-0">
                    <AvatarWithFrame user={user} size={40} />
                 </div>
                 
                 <div className="flex-1 min-w-0 relative">
                     <p className="font-bold text-white truncate text-sm">Вы</p>
                     <p className="text-xs text-white/40 truncate">
                        {activeTab === 'duel' ? 'Рейтинг' : 'Серия'}
                     </p>
                 </div>
                 
                 <div className="text-right relative">
                     <p className="font-black text-game-primary text-lg">
                        {activeTab === 'duel' ? currentUserData.rating : currentUserData.record}
                     </p>
                 </div>
             </div>
         </div>
      )}
    </div>
  )
}

// Sub-components

const PodiumItem = ({ player, place, activeTab, delay }) => {
    if (!player) return <div className="w-[80px] md:w-[100px]" /> // Spacer

    const isFirst = place === 1
    const size = isFirst ? 80 : 64
    const color = isFirst ? '#fbbf24' : place === 2 ? '#94a3b8' : '#b45309'
    const glowColor = isFirst ? 'rgba(251, 191, 36, 0.5)' : place === 2 ? 'rgba(148, 163, 184, 0.5)' : 'rgba(180, 83, 9, 0.5)'
    
    return (
        <motion.div
            initial={{ opacity: 0, y: 50 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay, type: "spring" }}
            className={`flex flex-col items-center ${isFirst ? '-mb-4 z-10' : ''}`}
        >
            <div className="relative">
                {isFirst && (
                    <div className="absolute -top-8 left-1/2 -translate-x-1/2 text-3xl animate-bounce">
                        👑
                    </div>
                )}
                <div 
                    className={`rounded-full p-1 shadow-2xl relative`}
                    style={{ 
                        background: `linear-gradient(135deg, ${color}, transparent)`,
                        boxShadow: `0 0 20px ${glowColor}`
                    }}
                >
                    <div className="rounded-full overflow-hidden bg-black/50 backdrop-blur-sm" style={{ width: size, height: size }}>
                        {player.photo_url ? (
                            <img src={player.photo_url} alt={player.name} className="w-full h-full object-cover" />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-white font-bold text-xl">
                                {player.name?.[0]}
                            </div>
                        )}
                    </div>
                    <div className="absolute -bottom-2 left-1/2 -translate-x-1/2 w-6 h-6 rounded-full bg-black border border-white/20 flex items-center justify-center text-xs font-bold text-white shadow-lg">
                        {place}
                    </div>
                </div>
            </div>
            
            <div className={`text-center mt-4 ${isFirst ? 'mb-4' : ''}`}>
                <p className="font-bold text-white text-xs truncate max-w-[80px] md:max-w-[100px]">
                    {player.name}
                </p>
                <p className={`font-black text-sm ${isFirst ? 'text-yellow-400' : 'text-white/60'}`}>
                    {activeTab === 'duel' ? player.rating : player.record}
                </p>
            </div>
        </motion.div>
    )
}

const RankItem = ({ player, place, activeTab, isMe, isAdmin }) => {
    return (
        <motion.div
            initial={{ opacity: 0, x: -20 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            className={`
                relative flex items-center gap-4 p-4 rounded-2xl border transition-all
                ${isMe 
                    ? 'bg-game-primary/10 border-game-primary/50' 
                    : 'bg-white/5 border-white/5 hover:bg-white/10'
                }
            `}
        >
            <div className="w-8 h-8 flex items-center justify-center font-bold text-white/40 font-mono">
                #{place}
            </div>
            
            <div className="w-10 h-10 rounded-full overflow-hidden bg-white/10 shrink-0">
                {player.photo_url ? (
                    <img src={player.photo_url} alt={player.name} className="w-full h-full object-cover" />
                ) : (
                    <div className="w-full h-full flex items-center justify-center text-white text-sm font-bold">
                        {player.name?.[0]}
                    </div>
                )}
            </div>
            
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                    <p className="font-bold text-white text-sm truncate">{player.name}</p>
                    {isMe && <span className="text-[10px] bg-game-primary px-1.5 py-0.5 rounded text-white">Вы</span>}
                </div>
                {isAdmin && player.username && (
                    <p className="text-[10px] text-white/30 truncate">@{player.username}</p>
                )}
            </div>
            
            <div className="text-right">
                <p className={`font-black ${isMe ? 'text-game-primary' : 'text-white'}`}>
                    {activeTab === 'duel' ? player.rating : player.record}
                </p>
                <p className="text-[10px] text-white/30">
                    {activeTab === 'duel' ? 'MMR' : 'Score'}
                </p>
            </div>
        </motion.div>
    )
}

export default LeaderboardPage
