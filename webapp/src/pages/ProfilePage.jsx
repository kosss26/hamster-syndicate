import { useEffect, useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
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
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      // Загружаем профиль - это обязательные данные
      const profileRes = await api.getProfile()
      if (profileRes.success) {
        setProfile(profileRes.data)
      } else {
        setError(profileRes.error || 'Ошибка загрузки профиля')
        setLoading(false)
        return
      }

      // Остальные данные загружаем параллельно, но не критичны
      try {
        const [showcasedRes, statsRes, collectionsRes] = await Promise.allSettled([
          api.getShowcasedAchievements(),
          api.getAchievementStats(),
          api.getCollections()
        ])

        if (showcasedRes.status === 'fulfilled' && showcasedRes.value.success) {
          setShowcasedAchievements(showcasedRes.value.data.showcased || [])
        }
        if (statsRes.status === 'fulfilled' && statsRes.value.success) {
          setAchievementStats(statsRes.value.data)
        }
        if (collectionsRes.status === 'fulfilled' && collectionsRes.value.success) {
          setCollections(collectionsRes.value.data.collections || [])
        }
      } catch (err) {
        // Игнорируем ошибки дополнительных данных
        console.warn('Ошибка загрузки дополнительных данных:', err)
      }

    } catch (err) {
      console.error('Ошибка загрузки профиля:', err)
      setError(err.message || 'Ошибка загрузки данных')
    } finally {
      setLoading(false)
    }
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

  // Показываем страницу даже при ошибке, если есть хотя бы частичные данные
  if (!profile && !loading) {
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
  const level = Number(profile.level || 1)
  const experience = Number(profile.experience || 0)
  const progressData = profile.experience_progress && typeof profile.experience_progress === 'object'
    ? profile.experience_progress
    : null
  const expIntoLevel = Number(progressData?.exp_into_level || 0)
  const levelStart = Number(progressData?.current_level_start || 0)
  const nextLevelExp = Number(progressData?.next_level_experience || 0)
  const levelSpan = Math.max(1, nextLevelExp - levelStart)
  const experienceProgress = Math.max(0, Math.min(100, Math.round((expIntoLevel / levelSpan) * 100)))
  const avatarRingSize = 140
  const avatarStroke = 7
  const avatarRadius = (avatarRingSize - avatarStroke) / 2
  const avatarCircumference = 2 * Math.PI * avatarRadius
  const avatarProgressOffset = avatarCircumference * (1 - experienceProgress / 100)

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col pb-24">
      <div className="aurora-blob aurora-blob-1 opacity-50" />
      <div className="aurora-blob aurora-blob-3 opacity-50" />
      <div className="noise-overlay" />

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.2 }}
        className="relative z-10 p-6 space-y-4"
      >
        <section className="relative rounded-[32px] overflow-hidden shadow-2xl">
          <div className="absolute inset-0 bg-gradient-to-br from-[#161625] to-[#0c101d]" />
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(56,189,248,0.2),transparent_45%),radial-gradient(circle_at_85%_90%,rgba(16,185,129,0.2),transparent_40%)]" />

          <div className="relative p-6 text-center">
            <div className="relative mb-4 pt-6">
              <div className="absolute left-1/2 -translate-x-1/2 top-0 z-30 px-3 py-1 rounded-full border border-cyan-300/35 bg-black/55 backdrop-blur-md">
                <span className="text-xs font-black text-cyan-200 tracking-wide">LVL {level}</span>
              </div>

              <div className="relative mx-auto" style={{ width: avatarRingSize, height: avatarRingSize }}>
                <svg className="absolute inset-0 -rotate-90" width={avatarRingSize} height={avatarRingSize}>
                  <circle
                    cx={avatarRingSize / 2}
                    cy={avatarRingSize / 2}
                    r={avatarRadius}
                    fill="none"
                    stroke="rgba(255,255,255,0.18)"
                    strokeWidth={avatarStroke}
                  />
                  <circle
                    cx={avatarRingSize / 2}
                    cy={avatarRingSize / 2}
                    r={avatarRadius}
                    fill="none"
                    stroke="url(#profileXpRing)"
                    strokeWidth={avatarStroke}
                    strokeLinecap="round"
                    strokeDasharray={avatarCircumference}
                    strokeDashoffset={avatarProgressOffset}
                    style={{ transition: 'stroke-dashoffset 0.35s ease' }}
                  />
                  <defs>
                    <linearGradient id="profileXpRing" x1="0%" y1="0%" x2="100%" y2="100%">
                      <stop offset="0%" stopColor="#67e8f9" />
                      <stop offset="50%" stopColor="#818cf8" />
                      <stop offset="100%" stopColor="#34d399" />
                    </linearGradient>
                  </defs>
                </svg>

                <div className="absolute inset-[14px] rounded-full">
                  <AvatarWithFrame
                    photoUrl={user?.photo_url}
                    name={user?.first_name}
                    frameKey={profile?.equipped_frame}
                    size={100}
                    animated={false}
                    showGlow={false}
                  />
                </div>
              </div>
            </div>

            <h1 className="text-3xl font-black text-white tracking-tight">
              {user?.first_name}
              <span className="text-cyan-300">.</span>
            </h1>
            {user?.username && <p className="text-white/45 text-sm mt-1">@{user.username}</p>}

            <div className="mt-4 grid grid-cols-2 gap-2 text-left">
              <div className="rounded-xl border border-white/10 bg-black/25 px-3 py-2">
                <p className="text-[10px] text-white/45 uppercase tracking-wider">Ранг</p>
                <p className="text-sm font-bold text-white mt-0.5">{rankName}</p>
              </div>
              <div className="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-right">
                <p className="text-[10px] text-white/45 uppercase tracking-wider">Рейтинг</p>
                <p className="text-sm font-bold text-cyan-200 mt-0.5">{profile.rating}</p>
              </div>
            </div>

            
          </div>
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
          <h3 className="text-white text-sm font-bold mb-3">Быстрые действия</h3>
          <div className="grid grid-cols-3 gap-2">
            <Link to="/inventory" className="rounded-2xl border border-emerald-400/25 bg-emerald-500/10 p-3 text-center active:scale-95 transition-transform">
              <div className="text-xl mb-1">🎒</div>
              <p className="text-xs font-semibold text-white">Инвентарь</p>
            </Link>
            <Link to="/wheel" className="rounded-2xl border border-cyan-400/25 bg-cyan-500/10 p-3 text-center active:scale-95 transition-transform">
              <div className="text-xl mb-1">🎡</div>
              <p className="text-xs font-semibold text-white">Колесо</p>
            </Link>
            <Link to="/lootbox" className="rounded-2xl border border-amber-400/25 bg-amber-500/10 p-3 text-center active:scale-95 transition-transform">
              <div className="text-xl mb-1">🎁</div>
              <p className="text-xs font-semibold text-white">Лутбоксы</p>
            </Link>
          </div>
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-white text-sm font-bold">Статистика</h3>
            <button
              onClick={() => navigate('/stats')}
              className="text-[11px] text-cyan-200 border border-cyan-300/30 bg-cyan-500/10 rounded-full px-3 py-1 active:scale-95 transition-transform"
            >
              Детально
            </button>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
              <p className="text-[10px] text-white/45 uppercase tracking-wider">Победы</p>
              <p className="text-xl font-black text-white mt-1">{profile.stats?.duel_wins || 0}</p>
            </div>
            <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
              <p className="text-[10px] text-white/45 uppercase tracking-wider">Поражения</p>
              <p className="text-xl font-black text-white mt-1">{profile.stats?.duel_losses || 0}</p>
            </div>
            <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
              <p className="text-[10px] text-white/45 uppercase tracking-wider">Winrate</p>
              <p className="text-xl font-black text-emerald-300 mt-1">{winRate}%</p>
            </div>
            <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
              <p className="text-[10px] text-white/45 uppercase tracking-wider">Рекорд П/Л</p>
              <p className="text-xl font-black text-cyan-200 mt-1">{profile.true_false_record || 0}</p>
            </div>
          </div>
          <p className="text-xs text-white/45 mt-2">Всего игр: {totalGames}</p>
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-white text-sm font-bold">Витрина достижений</h3>
            <Link to="/achievements" className="text-[11px] text-cyan-200 border border-cyan-300/30 bg-cyan-500/10 rounded-full px-3 py-1">
              Управлять
            </Link>
          </div>

          {achievementStats && (
            <p className="text-xs text-white/50 mb-3">
              Открыто {achievementStats.completed}/{achievementStats.total} ({achievementStats.completion_percent}%)
            </p>
          )}

          {showcasedAchievements.length > 0 ? (
            <div className="grid grid-cols-1 gap-2">
              {showcasedAchievements.slice(0, 4).map((achievement) => (
                <div key={achievement.id} className="rounded-2xl border border-white/10 bg-white/5 p-3 flex items-center gap-3">
                  <div className="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-xl">
                    {achievement.icon}
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-white truncate">{achievement.title}</p>
                    <p className="text-xs text-white/50 truncate">{achievement.description}</p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-xs text-white/45">Пока нет выбранных достижений для витрины.</p>
          )}
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-white text-sm font-bold">Коллекции</h3>
            <Link to="/collections" className="text-[11px] text-cyan-200 border border-cyan-300/30 bg-cyan-500/10 rounded-full px-3 py-1">
              Все
            </Link>
          </div>
          {collections.length > 0 ? (
            <div className="grid grid-cols-2 gap-2">
              {collections.slice(0, 4).map((col) => (
                <Link
                  key={col.id}
                  to={`/collections/${col.id}`}
                  className="rounded-2xl border border-white/10 bg-white/5 p-3"
                >
                  <div className="text-xl mb-1">{col.icon}</div>
                  <p className="text-xs font-semibold text-white truncate">{col.title}</p>
                  <div className="w-full h-1 bg-white/10 rounded-full mt-2 overflow-hidden">
                    <div className="h-full bg-cyan-300" style={{ width: `${col.progress_percent}%` }} />
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <p className="text-xs text-white/45">Нет активных коллекций.</p>
          )}
        </section>
      </motion.div>
    </div>
  )
}

export default ProfilePage
