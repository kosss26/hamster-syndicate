import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton } from '../hooks/useTelegram'
import api from '../api/client'

function ReferralPage() {
  const { user, webApp } = useTelegram()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    showBackButton(true)
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

  // Кнопка "Пригласить друга" - открывает окно выбора контакта
  const inviteFriend = () => {
    if (!stats?.referral_link) return
    
    const text = `🎮 Присоединяйся к Битве знаний! Получи 50 монет в подарок!\n${stats.referral_link}`
    
    // Используем правильный метод для открытия окна выбора контактов
    // tg://msg_url открывает окно выбора чата
    const shareUrl = `tg://msg_url?url=${encodeURIComponent(stats.referral_link)}&text=${encodeURIComponent('🎮 Присоединяйся к Битве знаний! Получи 50 монет в подарок!')}`
    
    // Безопасно открываем окно шаринга
    if (webApp?.openTelegramLink) {
      webApp.openTelegramLink(shareUrl)
    } else if (webApp?.openLink) {
      webApp.openLink(shareUrl)
    } else {
      // Fallback для браузера
      window.location.href = shareUrl
    }
  }

  // Копировать ссылку в буфер обмена
  const copyLink = async () => {
    if (!stats?.referral_link) return

    try {
      await navigator.clipboard.writeText(stats.referral_link)
      setCopied(true)
      
      // Безопасно показываем уведомление
      if (webApp?.showPopup) {
        webApp.showPopup({
          title: '✅ Скопировано!',
          message: 'Ссылка скопирована в буфер обмена',
          buttons: [{ type: 'close' }]
        })
      } else if (webApp?.showAlert) {
        webApp.showAlert('✅ Ссылка скопирована!')
      }
      
      setTimeout(() => setCopied(false), 2000)
    } catch (err) {
      console.error('Ошибка копирования:', err)
      if (webApp?.showAlert) {
        webApp.showAlert('Не удалось скопировать ссылку')
      }
    }
  }

  // Копировать код
  const copyCode = async () => {
    if (!stats?.referral_code) return

    try {
      await navigator.clipboard.writeText(stats.referral_code)
      
      // Безопасно показываем уведомление
      if (webApp?.showPopup) {
        webApp.showPopup({
          title: '✅ Скопировано!',
          message: `Код ${stats.referral_code} скопирован`,
          buttons: [{ type: 'close' }]
        })
      } else if (webApp?.showAlert) {
        webApp.showAlert(`✅ Код ${stats.referral_code} скопирован!`)
      }
    } catch (err) {
      console.error('Ошибка копирования:', err)
      if (webApp?.showAlert) {
        webApp.showAlert('Не удалось скопировать код')
      }
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/40">Загрузка...</p>
        </div>
      </div>
    )
  }

  if (error || !stats) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-6">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            className="text-7xl mb-4"
          >
            😔
          </motion.div>
          <p className="text-white/50 mb-6">{error || 'Ошибка загрузки'}</p>
          <button 
            onClick={loadStats}
            className="px-6 py-3 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl text-white font-medium shadow-glow"
          >
            Попробовать снова
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden pb-8">
      {/* Aurora Background */}
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="aurora-blob aurora-blob-3" />
      <div className="noise-overlay" />

      <div className="relative z-10 p-4">
        {/* Header */}
        <motion.div 
          initial={{ opacity: 0, y: -30 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center pt-4 mb-6"
        >
          <h1 className="text-3xl font-bold text-white mb-2">
            🎁 Приглашай друзей
          </h1>
          <p className="text-white/60">
            Получай награды за каждого приглашенного друга
          </p>
        </motion.div>

        {/* Реферальный код */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bento-card p-6 mb-4"
        >
          <div className="bento-glow bg-gradient-to-br from-game-primary/30 via-purple-500/20 to-transparent blur-2xl" />
          
          <div className="relative">
            <p className="text-white/40 text-sm mb-2">Твой реферальный код</p>
            <div className="flex items-center gap-3">
              <code className="flex-1 text-2xl font-bold text-gradient-primary select-all">
                {stats.referral_code}
              </code>
              <button 
                onClick={copyCode}
                className="px-4 py-3 bg-white/10 hover:bg-white/20 rounded-xl text-white transition-colors"
              >
                📋
              </button>
            </div>
          </div>
        </motion.div>

        {/* Кнопки действий */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="grid grid-cols-2 gap-3 mb-4"
        >
          <button 
            onClick={inviteFriend}
            className="flex flex-col items-center justify-center p-4 bg-gradient-to-br from-game-primary to-purple-600 rounded-2xl text-white font-bold shadow-glow hover:scale-105 transition-transform"
          >
            <span className="text-3xl mb-2">📤</span>
            <span className="text-sm">Пригласить</span>
            <span className="text-xs opacity-70">друга</span>
          </button>

          <button 
            onClick={copyLink}
            className={`flex flex-col items-center justify-center p-4 rounded-2xl text-white font-bold transition-all ${
              copied 
                ? 'bg-gradient-to-br from-game-success to-emerald-600' 
                : 'bg-gradient-to-br from-purple-600 to-pink-600 hover:scale-105'
            }`}
          >
            <span className="text-3xl mb-2">{copied ? '✅' : '🔗'}</span>
            <span className="text-sm">{copied ? 'Скопировано!' : 'Копировать'}</span>
            <span className="text-xs opacity-70">ссылку</span>
          </button>
        </motion.div>

        {/* Статистика */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="grid grid-cols-2 gap-3 mb-4"
        >
          <StatCard 
            icon="👥" 
            value={stats.total_referrals} 
            label="Всего друзей"
            gradient="from-blue-500/20 to-cyan-500/10"
          />
          <StatCard 
            icon="✅" 
            value={stats.active_referrals} 
            label="Активных"
            gradient="from-game-success/20 to-emerald-500/10"
          />
          <StatCard 
            icon="💰" 
            value={stats.total_coins_earned} 
            label="Заработано монет"
            gradient="from-game-warning/20 to-orange-500/10"
          />
          <StatCard 
            icon="⭐" 
            value={stats.total_exp_earned} 
            label="Получено опыта"
            gradient="from-purple-500/20 to-pink-500/10"
          />
        </motion.div>

        {/* Награды */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="bento-card p-6 mb-4"
        >
          <div className="bento-glow bg-gradient-to-br from-game-success/20 via-emerald-500/10 to-transparent blur-2xl" />
          
          <div className="relative">
            <h3 className="text-white font-bold mb-4 flex items-center gap-2">
              <span>🎯</span>
              <span>Что получишь</span>
            </h3>
            <div className="space-y-3">
              <RewardItem emoji="💰" text="100 монет за каждого активного друга" />
              <RewardItem emoji="⭐" text="50 опыта за приглашение" />
              <RewardItem emoji="🎁" text="Бонусы за количество друзей" />
              <RewardItem emoji="⏱" text="Друг должен сыграть 3 игры" />
            </div>
          </div>
        </motion.div>

        {/* Следующая награда */}
        {stats.next_milestone && (
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="bento-card p-6 mb-4"
          >
            <div className="bento-glow bg-gradient-to-br from-game-primary/30 via-purple-500/20 to-pink-500/10 blur-2xl" />
            
            <div className="relative">
              <h3 className="text-white font-bold mb-3">🏆 Следующая награда</h3>
              <p className="text-xl text-gradient-primary font-bold mb-2">
                {stats.next_milestone.title}
              </p>
              <p className="text-white/60 text-sm mb-4">
                {stats.next_milestone.referrals_needed} друзей
              </p>
              
              {/* Progress bar */}
              <div className="mb-3">
                <div className="flex justify-between text-xs text-white/40 mb-2">
                  <span>{stats.next_milestone.progress} / {stats.next_milestone.referrals_needed}</span>
                  <span>{Math.round((stats.next_milestone.progress / stats.next_milestone.referrals_needed) * 100)}%</span>
                </div>
                <div className="h-2 bg-white/5 rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${(stats.next_milestone.progress / stats.next_milestone.referrals_needed) * 100}%` }}
                    transition={{ delay: 0.7, duration: 1 }}
                    className="h-full bg-gradient-to-r from-game-primary via-purple-500 to-pink-500 rounded-full shadow-glow"
                  />
                </div>
              </div>

              <div className="flex gap-4 text-sm">
                <div className="flex items-center gap-2">
                  <span>💰</span>
                  <span className="text-white/80">{stats.next_milestone.reward_coins}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span>⭐</span>
                  <span className="text-white/80">{stats.next_milestone.reward_experience}</span>
                </div>
              </div>
            </div>
          </motion.div>
        )}

        {/* Список рефералов */}
        {stats.referrals && stats.referrals.length > 0 && (
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
            className="bento-card p-6"
          >
            <div className="bento-glow bg-gradient-to-br from-blue-500/20 via-cyan-500/10 to-transparent blur-2xl" />
            
            <div className="relative">
              <h3 className="text-white font-bold mb-4 flex items-center gap-2">
                <span>👥</span>
                <span>Твои рефералы</span>
              </h3>
              <div className="space-y-2">
                {stats.referrals.map((ref, index) => (
                  <ReferralItem key={index} referral={ref} />
                ))}
              </div>
              
              {stats.referrals.length > 0 && (
                <div className="mt-4 pt-4 border-t border-white/10 text-xs text-white/40">
                  <p>✅ — активный (получена награда)</p>
                  <p>⏳ — ожидает 3 игр</p>
                </div>
              )}
            </div>
          </motion.div>
        )}
      </div>
    </div>
  )
}

// Компоненты
function StatCard({ icon, value, label, gradient }) {
  return (
    <motion.div 
      className="relative overflow-hidden rounded-2xl p-4 text-center"
      whileHover={{ scale: 1.05 }}
      transition={{ type: "spring", stiffness: 400 }}
    >
      <div className={`absolute inset-0 bg-gradient-to-br ${gradient}`} />
      <div className="absolute inset-0 glass" />
      
      <div className="relative">
        <span className="text-3xl block mb-1">{icon}</span>
        <p className="text-2xl font-bold text-white mb-1">{value}</p>
        <p className="text-2xs text-white/40 uppercase tracking-wider leading-tight">{label}</p>
      </div>
    </motion.div>
  )
}

function RewardItem({ emoji, text }) {
  return (
    <div className="flex items-start gap-3">
      <span className="text-xl flex-shrink-0">{emoji}</span>
      <p className="text-white/70 text-sm leading-relaxed">{text}</p>
    </div>
  )
}

function ReferralItem({ referral }) {
  const statusEmoji = referral.status === 'active' ? '✅' : '⏳'
  const statusColor = referral.status === 'active' ? 'text-game-success' : 'text-game-warning'
  
  return (
    <div className="flex items-center justify-between p-3 bg-white/5 rounded-xl">
      <div className="flex items-center gap-3">
        <span className={`text-xl ${statusColor}`}>{statusEmoji}</span>
        <div>
          <p className="text-white text-sm font-medium">
            {referral.user.name}
            {referral.user.username && (
              <span className="text-white/40 text-xs ml-1">@{referral.user.username}</span>
            )}
          </p>
          <p className="text-white/40 text-xs">
            {referral.games_played} игр • {referral.created_at}
          </p>
        </div>
      </div>
    </div>
  )
}

export default ReferralPage

