import { useEffect, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useTelegram } from '../hooks/useTelegram'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'

function ReferralPage() {
  const { user, webApp } = useTelegram()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [copiedLink, setCopiedLink] = useState(false)
  const [copiedCode, setCopiedCode] = useState(false)

  useEffect(() => {
    loadStats()
  }, [])

  const loadStats = async () => {
    try {
      setLoading(true)
      setError(null)
      const response = await api.getReferralStats()
      if (response.success) {
        setStats(response.data)
      } else {
        setError(response.error || 'Ошибка API')
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const inviteFriend = () => {
    if (!stats?.referral_link) return
    
    const text = '🎮 Присоединяйся к Битве знаний! Получи 50 монет в подарок!'
    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(stats.referral_link)}&text=${encodeURIComponent(text)}`
    
    if (webApp?.openLink) {
      webApp.openLink(shareUrl)
    } else {
      window.open(shareUrl, '_blank')
    }
  }

  const copyToClipboard = async (text, type) => {
    if (!text) return

    try {
      await navigator.clipboard.writeText(text)
      
      if (type === 'link') {
        setCopiedLink(true)
        setTimeout(() => setCopiedLink(false), 2000)
      } else {
        setCopiedCode(true)
        setTimeout(() => setCopiedCode(false), 2000)
      }
      
      // Haptic feedback
      if (webApp?.HapticFeedback) {
        webApp.HapticFeedback.notificationOccurred('success')
      }

    } catch (err) {
      console.error('Ошибка копирования:', err)
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        <div className="relative z-10 flex flex-col items-center">
          <div className="w-12 h-12 border-4 border-game-primary border-t-transparent rounded-full animate-spin mb-4" />
          <p className="text-white/40 font-medium">Загрузка данных...</p>
        </div>
      </div>
    )
  }

  if (error || !stats) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-6">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center max-w-sm">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            className="text-6xl mb-6"
          >
            🔌
          </motion.div>
          <h2 className="text-xl font-bold text-white mb-2">Что-то пошло не так</h2>
          <p className="text-white/50 mb-8">{error || 'Не удалось загрузить данные'}</p>
          <button 
            onClick={loadStats}
            className="w-full py-4 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl text-white font-bold shadow-lg shadow-game-primary/20 active:scale-95 transition-transform"
          >
            Попробовать снова
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden pb-safe">
      {/* Background Effects */}
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-4 pt-4 pb-24 space-y-6">
        
        {/* Header Section */}
        <div className="text-center pt-6 pb-2">
          <motion.div
            initial={{ scale: 0.5, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            className="w-20 h-20 bg-gradient-to-br from-game-primary to-purple-600 rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg shadow-game-primary/30"
          >
            <span className="text-4xl">🎁</span>
          </motion.div>
          <motion.h1 
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ delay: 0.1 }}
            className="text-3xl font-bold text-white mb-2"
          >
            Реферальная программа
          </motion.h1>
          <motion.p 
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ delay: 0.2 }}
            className="text-white/60 text-sm max-w-xs mx-auto"
          >
            Приглашай друзей и получай бонусы за их активность
          </motion.p>
        </div>

        {/* Invite Card */}
        <motion.div 
          initial={{ y: 20, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          transition={{ delay: 0.3 }}
          className="glass-card p-1 rounded-2xl overflow-hidden"
        >
          <div className="bg-black/40 backdrop-blur-xl p-5 rounded-xl">
            <div className="flex justify-between items-center mb-2">
              <span className="text-white/40 text-xs font-bold uppercase tracking-wider">Твой код приглашения</span>
              <span className="text-game-primary text-xs font-bold">Нажми, чтобы скопировать</span>
            </div>
            
            <button 
              onClick={() => copyToClipboard(stats.referral_code, 'code')}
              className="w-full flex items-center justify-between bg-white/5 hover:bg-white/10 active:bg-white/15 p-4 rounded-xl transition-colors group mb-4"
            >
              <code className="text-2xl font-mono font-bold text-white tracking-widest">
                {stats.referral_code}
              </code>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center transition-colors ${copiedCode ? 'bg-green-500/20 text-green-400' : 'bg-white/10 text-white/40 group-hover:text-white'}`}>
                {copiedCode ? (
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                ) : (
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                )}
              </div>
            </button>

            <div className="grid grid-cols-2 gap-3">
              <button 
                onClick={inviteFriend}
                className="py-3.5 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl text-white font-bold shadow-lg shadow-game-primary/20 active:scale-95 transition-all flex items-center justify-center gap-2"
              >
                <span>🚀</span>
                <span>Пригласить</span>
              </button>
              <button 
                onClick={() => copyToClipboard(stats.referral_link, 'link')}
                className={`py-3.5 rounded-xl font-bold border active:scale-95 transition-all flex items-center justify-center gap-2 ${
                  copiedLink 
                    ? 'bg-green-500/10 border-green-500/30 text-green-400' 
                    : 'bg-white/5 border-white/10 text-white hover:bg-white/10'
                }`}
              >
                {copiedLink ? (
                  <>
                    <span>✅</span>
                    <span>Скопировано</span>
                  </>
                ) : (
                  <>
                    <span>🔗</span>
                    <span>Ссылка</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </motion.div>

        {/* Stats Grid */}
        <div className="grid grid-cols-2 gap-3">
          <StatCard 
            icon="👥" 
            value={stats.total_referrals} 
            label="Всего приглашено" 
            delay={0.4} 
            color="text-blue-400"
            bg="bg-blue-500/10"
          />
          <StatCard 
            icon={<CoinIcon className="w-5 h-5" />} 
            value={stats.total_coins_earned} 
            label="Заработано монет" 
            delay={0.5} 
            color="text-yellow-400"
            bg="bg-yellow-500/10"
          />
        </div>

        {/* Rewards Info */}
        <motion.div 
          initial={{ y: 20, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          transition={{ delay: 0.6 }}
          className="bg-white/5 border border-white/10 rounded-2xl p-5"
        >
          <h3 className="text-white font-bold mb-4 flex items-center gap-2">
            <span className="text-xl">🏆</span>
            <span>Награды за друзей</span>
          </h3>
          <div className="space-y-4">
            <RewardRow 
              icon={<CoinIcon className="w-5 h-5" />} 
              title="100 монет" 
              desc="За каждого активного друга" 
            />
            <RewardRow 
              icon={<span className="text-lg">⭐</span>} 
              title="50 опыта" 
              desc="Повышай уровень быстрее" 
            />
            <div className="bg-white/5 rounded-xl p-3 text-xs text-white/40 flex gap-2 items-start">
              <span className="text-lg leading-none">ℹ️</span>
              <span className="leading-snug">
                Друг считается активным после того, как сыграет 3 дуэли. Награда начисляется автоматически.
              </span>
            </div>
          </div>
        </motion.div>

        {/* Milestone Progress */}
        {stats.next_milestone && (
          <motion.div 
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ delay: 0.7 }}
            className="glass-card p-5 rounded-2xl relative overflow-hidden"
          >
            <div className="absolute top-0 right-0 p-3 opacity-10">
              <span className="text-6xl">🎯</span>
            </div>
            
            <div className="relative z-10">
              <div className="flex justify-between items-start mb-2">
                <div>
                  <h3 className="text-white font-bold text-lg">{stats.next_milestone.title}</h3>
                  <p className="text-white/60 text-xs">Следующая цель</p>
                </div>
                <div className="bg-white/10 backdrop-blur-md px-3 py-1 rounded-lg">
                  <span className="text-game-primary font-bold text-sm">
                    {stats.next_milestone.progress}/{stats.next_milestone.referrals_needed}
                  </span>
                </div>
              </div>

              {/* Progress Bar */}
              <div className="h-3 bg-black/40 rounded-full overflow-hidden mb-4 border border-white/5">
                <motion.div
                  initial={{ width: 0 }}
                  animate={{ width: `${(stats.next_milestone.progress / stats.next_milestone.referrals_needed) * 100}%` }}
                  transition={{ duration: 1.5, ease: "easeOut" }}
                  className="h-full bg-gradient-to-r from-game-primary via-purple-500 to-pink-500 relative"
                >
                  <div className="absolute inset-0 bg-white/20 animate-pulse-slow" />
                </motion.div>
              </div>

              <div className="flex gap-4">
                <div className="flex items-center gap-1.5 bg-black/20 px-3 py-1.5 rounded-lg border border-white/5">
                  <CoinIcon className="w-4 h-4" />
                  <span className="text-white font-bold text-sm">+{stats.next_milestone.reward_coins}</span>
                </div>
                <div className="flex items-center gap-1.5 bg-black/20 px-3 py-1.5 rounded-lg border border-white/5">
                  <span className="text-sm">⭐</span>
                  <span className="text-white font-bold text-sm">+{stats.next_milestone.reward_experience}</span>
                </div>
              </div>
            </div>
          </motion.div>
        )}

        {/* Referrals List */}
        {stats.referrals && stats.referrals.length > 0 && (
          <motion.div
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ delay: 0.8 }}
          >
            <h3 className="text-white/60 text-sm font-bold uppercase tracking-wider mb-3 px-1">
              Твои приглашения ({stats.referrals.length})
            </h3>
            <div className="space-y-2">
              {stats.referrals.map((referral, i) => (
                <ReferralItem key={i} referral={referral} index={i} />
              ))}
            </div>
          </motion.div>
        )}
      </div>
    </div>
  )
}

function StatCard({ icon, value, label, delay, color, bg }) {
  return (
    <motion.div 
      initial={{ y: 20, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ delay }}
      className={`p-4 rounded-2xl border border-white/5 backdrop-blur-sm ${bg}`}
    >
      <div className={`text-2xl mb-2 ${color}`}>{icon}</div>
      <div className="text-2xl font-bold text-white leading-none mb-1">{value}</div>
      <div className="text-white/40 text-xs font-medium">{label}</div>
    </motion.div>
  )
}

function RewardRow({ icon, title, desc }) {
  return (
    <div className="flex items-center gap-4">
      <div className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0 border border-white/10">
        {icon}
      </div>
      <div>
        <div className="text-white font-bold text-sm">{title}</div>
        <div className="text-white/40 text-xs">{desc}</div>
      </div>
    </div>
  )
}

function ReferralItem({ referral, index }) {
  const isActive = referral.status === 'active'
  
  return (
    <motion.div 
      initial={{ x: -20, opacity: 0 }}
      animate={{ x: 0, opacity: 1 }}
      transition={{ delay: 0.8 + (index * 0.05) }}
      className="bg-white/5 border border-white/5 p-3 rounded-xl flex items-center justify-between"
    >
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center text-lg font-bold text-white/20">
          {referral.user.name.charAt(0)}
        </div>
        <div>
          <div className="text-white font-medium text-sm">
            {referral.user.name}
          </div>
          <div className="text-white/40 text-xs flex items-center gap-2">
            <span>{referral.games_played} игр</span>
            <span className="w-1 h-1 rounded-full bg-white/20" />
            <span>{referral.created_at}</span>
          </div>
        </div>
      </div>
      
      {isActive ? (
        <div className="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center text-green-400">
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          </svg>
        </div>
      ) : (
        <div className="w-8 h-8 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-400/50">
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
      )}
    </motion.div>
  )
}

export default ReferralPage
