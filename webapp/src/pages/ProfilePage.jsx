import { useEffect, useState, useRef } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { motion, useMotionValue, useSpring, useTransform, AnimatePresence } from 'framer-motion'
import { useTelegram, showBackButton, hapticFeedback } from '../hooks/useTelegram'
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
  const [activeTab, setActiveTab] = useState('overview') // 'overview', 'stats', 'history'

  // Parallax effect values
  const x = useMotionValue(0)
  const y = useMotionValue(0)
  const rotateX = useTransform(y, [-100, 100], [10, -10])
  const rotateY = useTransform(x, [-100, 100], [-10, 10])
  
  const springConfig = { damping: 25, stiffness: 150 }
  const springRotateX = useSpring(rotateX, springConfig)
  const springRotateY = useSpring(rotateY, springConfig)

  useEffect(() => {
    showBackButton(true)
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    try {
      const [profileRes, showcasedRes, statsRes, collectionsRes] = await Promise.all([
        api.getProfile(),
        api.getShowcasedAchievements(),
        api.getAchievementStats(),
        api.getCollections()
      ])

      if (profileRes.success) setProfile(profileRes.data)
      else throw new Error(profileRes.error || 'Ошибка загрузки профиля')

      if (showcasedRes.success) setShowcasedAchievements(showcasedRes.data.showcased || [])
      if (statsRes.success) setAchievementStats(statsRes.data)
      if (collectionsRes.success) setCollections(collectionsRes.data.collections || [])

    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleMouseMove = (event) => {
    const rect = event.currentTarget.getBoundingClientRect()
    const centerX = rect.left + rect.width / 2
    const centerY = rect.top + rect.height / 2
    x.set(event.clientX - centerX)
    y.set(event.clientY - centerY)
  }

  const handleMouseLeave = () => {
    x.set(0)
    y.set(0)
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/40 font-mono text-sm">LOADING_PROFILE_DATA...</p>
        </div>
      </div>
    )
  }

  if (error || !profile) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center p-6">
        <div className="relative z-10 text-center">
          <div className="text-6xl mb-4">⚠️</div>
          <p className="text-white/60 mb-6">{error || 'Profile not found'}</p>
          <button 
            onClick={loadData}
            className="px-6 py-3 bg-white/10 border border-white/10 rounded-xl text-white font-medium backdrop-blur-md active:scale-95 transition-transform"
          >
            RETRY
          </button>
        </div>
      </div>
    )
  }

  const totalGames = (profile.stats?.duel_wins || 0) + (profile.stats?.duel_losses || 0) + (profile.stats?.duel_draws || 0)
  const winRate = totalGames > 0 ? Math.round((profile.stats?.duel_wins / totalGames) * 100) : 0
  const rankName = typeof profile.rank === 'object' ? profile.rank.name : profile.rank

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col pb-24">
      <div className="aurora-blob aurora-blob-1 opacity-50" />
      <div className="aurora-blob aurora-blob-3 opacity-50" />
      <div className="noise-overlay" />

      {/* Hero Section with Parallax */}
      <div className="relative z-10 p-6 pb-0">
        <motion.div
          style={{ rotateX: springRotateX, rotateY: springRotateY, perspective: 1000 }}
          onMouseMove={handleMouseMove}
          onMouseLeave={handleMouseLeave}
          className="relative w-full aspect-[4/3] rounded-[32px] overflow-hidden shadow-2xl mb-6 group cursor-grab active:cursor-grabbing"
        >
          {/* Card Background */}
          <div className="absolute inset-0 bg-gradient-to-br from-[#1a1a2e] to-[#0f0f1a] z-0" />
          <div className="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 z-0 mix-blend-overlay" />
          
          {/* Animated Glow */}
          <div className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-game-primary/20 via-transparent to-purple-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10" />

          {/* Content */}
          <div className="relative z-20 h-full flex flex-col items-center justify-center p-6 text-center transform translate-z-10">
            <motion.div 
              initial={{ scale: 0.5, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              transition={{ type: "spring", stiffness: 200, delay: 0.1 }}
              className="relative mb-4"
            >
              <div className="absolute inset-0 bg-game-primary/30 blur-3xl rounded-full" />
              <AvatarWithFrame
                photoUrl={user?.photo_url}
                name={user?.first_name}
                frameKey={profile?.equipped_frame}
                size={100}
                animated={false}
                showGlow={true}
              />
              <div className="absolute -bottom-2 -right-2 bg-black/50 backdrop-blur-md border border-white/10 rounded-full px-3 py-1 flex items-center gap-1">
                <div className="w-2 h-2 rounded-full bg-game-success animate-pulse" />
                <span className="text-[10px] font-bold text-white uppercase tracking-wider">В сети</span>
              </div>
            </motion.div>

            <motion.div
              initial={{ y: 20, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.2 }}
            >
              <h1 className="text-3xl font-black text-white mb-1 tracking-tight drop-shadow-lg">
                {user?.first_name}
                <span className="text-game-primary">.</span>
              </h1>
              {user?.username && (
                <p className="text-white/40 text-sm font-mono tracking-wider">@{user.username}</p>
              )}
            </motion.div>

            {/* Rank Badge */}
            <motion.div 
              initial={{ y: 20, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.3 }}
              className="mt-4 bg-white/5 border border-white/10 backdrop-blur-md rounded-xl px-4 py-2 flex items-center gap-2"
            >
              <span className="text-lg">🏆</span>
              <div className="text-left">
                <p className="text-[10px] text-white/40 uppercase font-bold tracking-widest leading-none">Ранг</p>
                <p className="text-sm font-bold text-white leading-none mt-0.5">{rankName}</p>
              </div>
              <div className="w-px h-6 bg-white/10 mx-2" />
              <div className="text-right">
                <p className="text-[10px] text-white/40 uppercase font-bold tracking-widest leading-none">Рейтинг</p>
                <p className="text-sm font-bold text-gradient-primary leading-none mt-0.5">{profile.rating}</p>
              </div>
            </motion.div>
          </div>
        </motion.div>

        {/* Tabs */}
        <div className="flex p-1 bg-black/20 backdrop-blur-xl rounded-2xl mb-6 border border-white/5">
          {['overview', 'stats', 'achievements'].map((tab) => (
            <button
              key={tab}
              onClick={() => {
                setActiveTab(tab)
                hapticFeedback('light')
              }}
              className="relative flex-1 py-3 text-sm font-medium transition-colors z-10"
            >
              {activeTab === tab && (
                <motion.div
                  layoutId="activeTabProfile"
                  className="absolute inset-0 bg-white/10 rounded-xl border border-white/10 shadow-sm"
                  transition={{ type: "spring", stiffness: 500, damping: 30 }}
                />
              )}
              <span className={activeTab === tab ? 'text-white' : 'text-white/40'}>
                {tab === 'overview' ? 'Обзор' : tab === 'stats' ? 'Статистика' : 'Достижения'}
              </span>
            </button>
          ))}
        </div>

        <AnimatePresence mode="wait">
          {activeTab === 'overview' && (
            <motion.div
              key="overview"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
              className="space-y-4"
            >
              {/* Quick Actions Grid */}
              <div className="grid grid-cols-2 gap-3">
                <Link to="/inventory" className="group">
                  <div className="bg-gradient-to-br from-emerald-900/40 to-emerald-900/10 border border-emerald-500/20 rounded-3xl p-5 relative overflow-hidden transition-transform active:scale-95">
                    <div className="absolute top-0 right-0 p-4 opacity-20 group-hover:opacity-40 transition-opacity">
                      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" className="text-emerald-400"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/></svg>
                    </div>
                    <div className="relative z-10">
                      <div className="w-10 h-10 rounded-2xl bg-emerald-500/20 flex items-center justify-center mb-3 text-emerald-400 text-xl">🎒</div>
                      <h3 className="font-bold text-white text-lg">Инвентарь</h3>
                      <p className="text-emerald-400/60 text-xs mt-1 font-medium">Скины и рамки</p>
                    </div>
                  </div>
                </Link>

                <Link to="/lootbox" className="group">
                  <div className="bg-gradient-to-br from-amber-900/40 to-amber-900/10 border border-amber-500/20 rounded-3xl p-5 relative overflow-hidden transition-transform active:scale-95">
                    <div className="absolute top-0 right-0 p-4 opacity-20 group-hover:opacity-40 transition-opacity">
                      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" className="text-amber-400"><path d="m21 8-2 2-1.5-3.7A2 2 0 0 0 15.646 5H8.4a2 2 0 0 0-1.903 1.257L5 10 3 8"/><path d="M7 12a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2Z"/><path d="M5 10a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2Z"/></svg>
                    </div>
                    <div className="relative z-10">
                      <div className="w-10 h-10 rounded-2xl bg-amber-500/20 flex items-center justify-center mb-3 text-amber-400 text-xl">🎁</div>
                      <h3 className="font-bold text-white text-lg">Лутбоксы</h3>
                      <p className="text-amber-400/60 text-xs mt-1 font-medium">Испытай удачу</p>
                    </div>
                  </div>
                </Link>
              </div>

              {/* Collections Preview */}
              <div className="bento-card p-5">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-bold text-white flex items-center gap-2">
                    <span className="text-xl">📚</span> Коллекции
                  </h3>
                  <Link to="/collections" className="text-xs font-bold text-game-primary bg-game-primary/10 px-3 py-1 rounded-full">
                    ВСЕ
                  </Link>
                </div>
                {collections.length > 0 ? (
                  <div className="grid grid-cols-3 gap-3">
                    {collections.slice(0, 3).map((col) => (
                      <Link 
                        key={col.id} 
                        to={`/collections/${col.id}`}
                        className="bg-white/5 rounded-2xl p-3 flex flex-col items-center text-center hover:bg-white/10 transition-colors"
                      >
                        <div className="text-3xl mb-2">{col.icon}</div>
                        <p className="text-xs font-bold text-white line-clamp-1 w-full">{col.title}</p>
                        <div className="w-full h-1 bg-white/10 rounded-full mt-3 overflow-hidden">
                          <div className="h-full bg-game-primary" style={{ width: `${col.progress_percent}%` }} />
                        </div>
                      </Link>
                    ))}
                  </div>
                ) : (
                  <p className="text-white/30 text-xs text-center py-4">Нет активных коллекций</p>
                )}
              </div>
            </motion.div>
          )}

          {activeTab === 'stats' && (
            <motion.div
              key="stats"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
              className="space-y-4"
            >
              {/* Main Stats */}
              <div className="grid grid-cols-2 gap-3">
                <div className="glass rounded-3xl p-5 flex flex-col justify-between h-32">
                  <div className="text-white/40 text-xs font-bold uppercase tracking-wider">% Побед</div>
                  <div className="text-right">
                    <span className="text-4xl font-black text-white">{winRate}</span>
                    <span className="text-lg text-white/40">%</span>
                  </div>
                  <div className="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
                    <div className="h-full bg-game-success rounded-full" style={{ width: `${winRate}%` }} />
                  </div>
                </div>
                <div className="glass rounded-3xl p-5 flex flex-col justify-between h-32">
                  <div className="text-white/40 text-xs font-bold uppercase tracking-wider">Всего игр</div>
                  <div className="text-right">
                    <span className="text-4xl font-black text-white">{totalGames}</span>
                  </div>
                  <div className="flex justify-end gap-1">
                    <span className="text-xs text-game-success font-bold">{profile.stats?.duel_wins}W</span>
                    <span className="text-xs text-white/20">/</span>
                    <span className="text-xs text-game-danger font-bold">{profile.stats?.duel_losses}L</span>
                  </div>
                </div>
              </div>

              {/* T/F Stats */}
              <div className="bento-card p-5 flex items-center justify-between">
                <div>
                  <div className="text-white/40 text-xs font-bold uppercase tracking-wider mb-1">Рекорд Правда/Ложь</div>
                  <div className="text-2xl font-bold text-white">{profile.true_false_record}</div>
                </div>
                <div className="text-4xl">🧠</div>
              </div>

              <button 
                onClick={() => navigate('/stats')}
                className="w-full py-4 bg-white/5 border border-white/10 rounded-2xl text-white font-bold text-sm hover:bg-white/10 transition-colors"
              >
                ПОДРОБНАЯ СТАТИСТИКА
              </button>
            </motion.div>
          )}

          {activeTab === 'achievements' && (
            <motion.div
              key="achievements"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
              className="space-y-4"
            >
              {achievementStats && (
                <div className="glass rounded-3xl p-6 text-center mb-4">
                  <div className="relative w-32 h-32 mx-auto mb-4">
                    <svg className="w-full h-full -rotate-90">
                      <circle cx="64" cy="64" r="56" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="12" />
                      <circle 
                        cx="64" cy="64" r="56" 
                        fill="none" 
                        stroke="#6366f1" 
                        strokeWidth="12" 
                        strokeDasharray={351} 
                        strokeDashoffset={351 - (351 * (achievementStats.completion_percent / 100))} 
                        strokeLinecap="round"
                      />
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                      <span className="text-3xl font-black text-white">{achievementStats.completion_percent}%</span>
                    </div>
                  </div>
                  <p className="text-white/60 text-sm">
                    Открыто <span className="text-white font-bold">{achievementStats.completed}</span> из <span className="text-white font-bold">{achievementStats.total}</span>
                  </p>
                </div>
              )}

              <div className="space-y-3">
                {showcasedAchievements.map((achievement) => (
                  <div key={achievement.id} className="bento-card p-4 flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-game-primary/20 to-purple-500/20 flex items-center justify-center text-2xl border border-white/10">
                      {achievement.icon}
                    </div>
                    <div className="flex-1">
                      <h4 className="text-white font-bold text-sm">{achievement.title}</h4>
                      <p className="text-white/40 text-xs">{achievement.description}</p>
                    </div>
                  </div>
                ))}
                
                <Link 
                  to="/achievements"
                  className="block w-full py-4 text-center text-game-primary font-bold text-sm bg-game-primary/10 rounded-2xl mt-4"
                >
                  ВСЕ ДОСТИЖЕНИЯ
                </Link>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  )
}

export default ProfilePage
