import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import api from '../api/client'
import { hapticFeedback, showBackButton } from '../hooks/useTelegram'

const FortuneWheelPage = () => {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [spinning, setSpinning] = useState(false)
  const [status, setStatus] = useState(null)
  const [config, setConfig] = useState([])
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    showBackButton(true)
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [statusRes, configRes] = await Promise.all([
        api.getWheelStatus(),
        api.getWheelConfig(),
      ])
      if (statusRes?.success) {
        setStatus(statusRes.data)
      }
      if (configRes?.success) {
        setConfig(configRes.data?.sectors || [])
      }
    } catch (err) {
      setError(err.message || 'Не удалось загрузить колесо')
    } finally {
      setLoading(false)
    }
  }

  const spin = async (isPremium = false) => {
    if (spinning) return
    setSpinning(true)
    setError(null)
    setResult(null)
    try {
      hapticFeedback('medium')
      const response = await api.spinWheel(isPremium)
      if (response?.success) {
        setResult(response.data?.reward || null)
        hapticFeedback('success')
        const refreshed = await api.getWheelStatus()
        if (refreshed?.success) {
          setStatus(refreshed.data)
        }
      }
    } catch (err) {
      hapticFeedback('error')
      setError(err.message || 'Не удалось прокрутить колесо')
    } finally {
      setSpinning(false)
    }
  }

  const cooldownText = useMemo(() => {
    if (!status) return '...'
    if (status.can_spin_free) return 'Бесплатный спин доступен'
    return `Следующий бесплатный спин через ${status.hours_left || 0}ч ${status.minutes_left || 0}м`
  }, [status])

  const rewardTitle = (reward) => {
    if (!reward) return ''
    const type = reward.type
    if (type === 'coins') return `+${reward.amount} монет`
    if (type === 'gems') return `+${reward.amount} кристаллов`
    if (type === 'exp') return `+${reward.amount} XP`
    if (type === 'hint') return `+${reward.amount} подсказка`
    if (type === 'life') return `+${reward.amount} жизнь`
    if (type === 'lootbox') return `+${reward.amount} лутбокс`
    return `+${reward.amount}`
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col">
      <div className="aurora-blob aurora-blob-1 opacity-60" />
      <div className="aurora-blob aurora-blob-2 opacity-60" />
      <div className="noise-overlay" />

      {/* Header */}
      <div className="relative z-10 px-6 pt-[calc(1.5rem+env(safe-area-inset-top))] pb-2 flex items-center justify-between">
        <button 
          onClick={() => navigate(-1)}
          className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-white/20 transition-colors backdrop-blur-md"
        >
          ←
        </button>
        <h1 className="text-2xl font-black italic uppercase text-white tracking-wider text-shadow-glow">
          Колесо Фортуны
        </h1>
        <div className="w-10" />
      </div>

      <div className="relative z-10 flex-1 overflow-y-auto px-6 pb-24">
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-3xl border border-amber-300/30 bg-gradient-to-br from-amber-500/20 to-orange-500/10 p-5 mb-4"
        >
          <p className="text-white/70 text-xs uppercase tracking-wide mb-1">Ежедневная награда</p>
          <h2 className="text-white text-2xl font-black mb-2">Крути и забирай призы</h2>
          <p className="text-white/70 text-sm">{cooldownText}</p>
        </motion.div>

        <div className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-5 mb-4">
          <div className="flex items-center justify-between mb-3">
            <div>
              <p className="text-white/50 text-xs uppercase tracking-wide">Серия спинов</p>
              <p className="text-white font-bold text-xl">{status?.wheel_streak ?? 0} дней</p>
            </div>
            <div className="text-right">
              <p className="text-white/50 text-xs uppercase tracking-wide">Всего</p>
              <p className="text-white font-bold text-xl">{status?.total_spins ?? 0}</p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <button
              onClick={() => spin(false)}
              disabled={spinning || loading || !status?.can_spin_free}
              className="rounded-2xl bg-emerald-400 text-slate-900 font-black py-3 disabled:opacity-50"
            >
              {spinning ? 'Крутим...' : 'Бесплатный спин'}
            </button>
            <button
              onClick={() => spin(true)}
              disabled={spinning || loading}
              className="rounded-2xl border border-cyan-300/30 bg-cyan-500/10 text-white font-bold py-3 disabled:opacity-50"
            >
              Премиум за 40 💎
            </button>
          </div>
        </div>

        <div className="rounded-3xl border border-white/10 bg-white/5 p-4 mb-4">
          <p className="text-white/55 text-xs uppercase tracking-wide mb-3">Сектора колеса</p>
          <div className="grid grid-cols-3 gap-2">
            {config.map((sector, index) => (
              <div key={`${sector.type}-${sector.amount}-${index}`} className="rounded-xl border border-white/10 bg-white/5 px-2 py-2 text-center">
                <div className="text-lg mb-1">{sector.icon || '🎁'}</div>
                <div className="text-white text-xs font-semibold leading-tight">{rewardTitle(sector)}</div>
              </div>
            ))}
          </div>
        </div>

        {error && (
          <div className="rounded-2xl border border-red-400/30 bg-red-500/10 p-3 text-red-200 text-sm mb-4">
            {error}
          </div>
        )}

        <AnimatePresence>
          {result && (
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              className="rounded-3xl border border-emerald-300/35 bg-emerald-500/10 p-4"
            >
              <p className="text-emerald-100 text-xs uppercase tracking-wide mb-1">Награда получена</p>
              <div className="flex items-center gap-3">
                <div className="text-3xl">{result.icon || '🎁'}</div>
                <div>
                  <p className="text-white font-black text-lg">{rewardTitle(result)}</p>
                  <p className="text-white/60 text-xs">Уже начислено на ваш аккаунт</p>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  )
}

export default FortuneWheelPage
