import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
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

  const getRankDisplay = (rank) => {
    if (typeof rank === 'object') {
      return `${rank.emoji || ''} ${rank.name || ''}`
    }
    return rank
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-game flex items-center justify-center">
        <div className="text-center">
          <div className="relative w-20 h-20 mx-auto mb-4">
            <div className="absolute inset-0 rounded-full bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 animate-spin" style={{ padding: '3px' }}>
              <div className="w-full h-full rounded-full bg-[#1a1a2e]"></div>
            </div>
          </div>
          <p className="text-white/60 text-sm">Загрузка профиля...</p>
        </div>
      </div>
    )
  }

  if (error || !profile) {
    return (
      <div className="min-h-screen bg-gradient-game flex items-center justify-center p-6">
        <div className="text-center">
          <div className="text-6xl mb-4">😔</div>
          <p className="text-white/60 mb-6">{error || 'Профиль не найден'}</p>
          <button 
            onClick={loadProfile}
            className="px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl text-white font-medium"
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
    <div className="min-h-screen bg-gradient-game overflow-hidden">
      {/* Decorative background elements */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl"></div>
      </div>

      <div className="relative z-10 p-4 pb-8">
        {/* Header with Avatar */}
        <div className="text-center pt-4 mb-6">
          {/* Avatar with ring */}
          <div className="relative inline-block mb-4">
            <div className="relative">
              <div className="w-24 h-24 rounded-full p-[3px] bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500">
                <div className="w-full h-full rounded-full bg-gradient-to-br from-indigo-600 to-purple-700 flex items-center justify-center">
                  <span className="text-4xl font-bold text-white">
                    {user?.first_name?.[0]?.toUpperCase() || '?'}
                  </span>
                </div>
              </div>
              {/* Online indicator */}
              <div className="absolute bottom-1 right-1 w-5 h-5 bg-green-500 rounded-full border-3 border-[#1a1a2e]"></div>
            </div>
          </div>

          <h1 className="text-2xl font-bold text-white mb-1">
            {user?.first_name} {user?.last_name || ''}
          </h1>
          
          {user?.username && (
            <p className="text-white/50 text-sm">
              @{user.username}
            </p>
          )}
        </div>

        {/* Rating Card - Main */}
        <div className="relative mb-4 overflow-hidden">
          <div className="absolute inset-0 bg-gradient-to-br from-indigo-600/30 to-purple-600/30 rounded-2xl"></div>
          <div className="relative glass rounded-2xl p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-white/50 text-xs uppercase tracking-wider mb-1">Рейтинг</p>
                <div className="flex items-baseline gap-2">
                  <span className="text-4xl font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                    {profile.rating}
                  </span>
                  <span className="text-white/40 text-sm">очков</span>
                </div>
              </div>
              <div className="text-right">
                <p className="text-white/50 text-xs uppercase tracking-wider mb-1">Ранг</p>
                <p className="text-xl font-semibold text-white">{getRankDisplay(profile.rank)}</p>
              </div>
            </div>
            
            {/* Progress bar */}
            <div className="mt-4">
              <div className="flex justify-between text-xs text-white/40 mb-2">
                <span>Прогресс</span>
                <span>До следующего ранга</span>
              </div>
              <div className="h-2 bg-white/10 rounded-full overflow-hidden">
                <div
                  style={{ width: '65%' }}
                  className="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-3 gap-3 mb-4">
          <StatBox 
            icon="🏆" 
            value={profile.stats?.duel_wins || 0} 
            label="Победы" 
            color="from-green-500/20 to-emerald-500/20"
            textColor="text-green-400"
          />
          <StatBox 
            icon="📊" 
            value={profile.rating} 
            label="Рейтинг" 
            color="from-indigo-500/20 to-purple-500/20"
            textColor="text-indigo-400"
          />
          <StatBox 
            icon="🔥" 
            value={profile.win_streak} 
            label="Серия" 
            color="from-orange-500/20 to-red-500/20"
            textColor="text-orange-400"
          />
        </div>

        {/* Duel Statistics */}
        <div className="glass rounded-2xl p-4 mb-4">
          <div className="flex items-center gap-2 mb-4">
            <span className="text-lg">⚔️</span>
            <h3 className="text-white/70 text-sm font-medium">Статистика дуэлей</h3>
          </div>
          
          <div className="grid grid-cols-4 gap-2 text-center mb-4">
            <div>
              <p className="text-2xl font-bold text-green-400">{profile.stats?.duel_wins || 0}</p>
              <p className="text-[10px] text-white/40 uppercase">Побед</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-red-400">{profile.stats?.duel_losses || 0}</p>
              <p className="text-[10px] text-white/40 uppercase">Поражений</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-white/50">{profile.stats?.duel_draws || 0}</p>
              <p className="text-[10px] text-white/40 uppercase">Ничьих</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-white">{totalGames}</p>
              <p className="text-[10px] text-white/40 uppercase">Всего</p>
            </div>
          </div>

          {/* Win Rate Bar */}
          <div className="pt-3 border-t border-white/10">
            <div className="flex justify-between items-center mb-2">
              <span className="text-xs text-white/50">Процент побед</span>
              <span className="text-sm font-bold text-green-400">{winRate}%</span>
            </div>
            <div className="h-2 bg-white/10 rounded-full overflow-hidden">
              <div
                style={{ width: `${winRate}%` }}
                className="h-full bg-gradient-to-r from-green-500 to-emerald-400 rounded-full"
              />
            </div>
          </div>
        </div>

        {/* True/False Record */}
        <div className="glass rounded-2xl p-4 mb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/30 to-pink-500/30 flex items-center justify-center">
                <span className="text-2xl">🧠</span>
              </div>
              <div>
                <p className="text-white/50 text-xs uppercase tracking-wider">Правда или ложь</p>
                <p className="text-xl font-bold text-white">Рекорд: {profile.true_false_record}</p>
              </div>
            </div>
            <div className="text-3xl">🏅</div>
          </div>
        </div>

        {/* Detailed Stats Button */}
        <button
          onClick={() => navigate('/stats')}
          className="w-full glass rounded-2xl p-4 flex items-center justify-between group hover:bg-white/10 transition-all active:scale-[0.98]"
        >
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/30 to-purple-500/30 flex items-center justify-center group-hover:scale-110 transition-transform">
              <span className="text-2xl">📊</span>
            </div>
            <div className="text-left">
              <p className="font-semibold text-white">Подробная статистика</p>
              <p className="text-xs text-white/40">Аналитика по категориям</p>
            </div>
          </div>
          <div className="text-white/30 group-hover:text-white/60 group-hover:translate-x-1 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
            </svg>
          </div>
        </button>
      </div>
    </div>
  )
}

function StatBox({ icon, value, label, color, textColor }) {
  return (
    <div className="relative overflow-hidden rounded-xl p-3 text-center">
      <div className={`absolute inset-0 bg-gradient-to-br ${color}`}></div>
      <div className="absolute inset-0 glass"></div>
      <div className="relative">
        <span className="text-lg">{icon}</span>
        <p className={`text-2xl font-bold ${textColor} mt-1`}>{value}</p>
        <p className="text-[10px] text-white/40 uppercase tracking-wider">{label}</p>
      </div>
    </div>
  )
}

export default ProfilePage
