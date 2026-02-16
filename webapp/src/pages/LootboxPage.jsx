import { useEffect, useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { api } from '../api/client'
import { useTelegram } from '../hooks/useTelegram'
import CoinIcon from '../components/CoinIcon'
import RewardNotifications from '../components/RewardNotifications'
import { addNotificationItems } from '../utils/notificationInbox'

const BOX_VISUALS = {
  bronze: {
    icon: '📦',
    title: 'Бронзовый',
    gradient: 'from-[#5a4b41] to-[#7a5a43]',
  },
  silver: {
    icon: '🧰',
    title: 'Серебряный',
    gradient: 'from-[#4b5667] to-[#7a879d]',
  },
  gold: {
    icon: '🎁',
    title: 'Золотой',
    gradient: 'from-[#9c6a1f] to-[#d7973b]',
  },
  legendary: {
    icon: '👑',
    title: 'Легендарный',
    gradient: 'from-[#5d3ca6] to-[#b04cd9]',
  },
}

const RARITY_LABEL = {
  common: 'Обычная',
  uncommon: 'Необычная',
  rare: 'Редкая',
  epic: 'Эпическая',
  legendary: 'Легендарная',
}

const RARITY_BADGE = {
  common: 'border-white/15 bg-white/5 text-white/70',
  uncommon: 'border-emerald-300/35 bg-emerald-500/10 text-emerald-200',
  rare: 'border-cyan-300/35 bg-cyan-500/10 text-cyan-200',
  epic: 'border-violet-300/35 bg-violet-500/10 text-violet-200',
  legendary: 'border-amber-300/40 bg-amber-500/10 text-amber-200',
}

function rewardName(type, amount) {
  const value = Number(amount || 0)
  const map = {
    coins: `${value} монет`,
    exp: `${value} опыта`,
    gems: `${value} кристаллов`,
    hint: `${value} подсказок`,
    life: `${value} билетов`,
    boost_12h: 'Буст 12ч',
    boost_24h: 'Буст 24ч',
    boost_7d: 'Буст 7 дней',
    cosmetic_epic: 'Эпическая косметика',
    cosmetic_legendary: 'Легендарная косметика',
  }
  return map[type] || `${value}`
}

function rewardIcon(type) {
  if (type === 'coins') return <CoinIcon size={28} />
  const map = {
    exp: '⭐',
    gems: '💎',
    hint: '💡',
    life: '🎫',
    boost_12h: '⚡',
    boost_24h: '⚡',
    boost_7d: '💫',
    cosmetic_epic: '✨',
    cosmetic_legendary: '👑',
  }
  return <span>{map[type] || '🎁'}</span>
}

const LootboxPage = () => {
  const { webApp } = useTelegram()
  const [loading, setLoading] = useState(true)
  const [opening, setOpening] = useState(false)
  const [selectedType, setSelectedType] = useState(null)
  const [error, setError] = useState(null)
  const [config, setConfig] = useState([])
  const [rewardPool, setRewardPool] = useState({})
  const [resources, setResources] = useState(null)
  const [result, setResult] = useState(null)
  const [rewardNotifications, setRewardNotifications] = useState([])

  useEffect(() => {
    if (webApp?.BackButton) {
      const onBack = () => window.history.back()
      webApp.BackButton.show()
      webApp.BackButton.onClick(onBack)
      return () => {
        webApp.BackButton.offClick(onBack)
        webApp.BackButton.hide()
      }
    }
  }, [webApp])

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [cfgResponse, invResponse] = await Promise.all([
        api.getLootboxConfig(),
        api.getInventory(),
      ])

      setConfig(cfgResponse?.data?.boxes || [])
      setRewardPool(cfgResponse?.data?.reward_pool || {})
      setResources(invResponse?.data?.resources || null)

      if (!selectedType && (cfgResponse?.data?.boxes || []).length > 0) {
        setSelectedType(cfgResponse.data.boxes[0].type)
      }
    } catch (err) {
      console.error('Ошибка загрузки лутбоксов:', err)
      setError(err.message || 'Ошибка загрузки лутбоксов')
    } finally {
      setLoading(false)
    }
  }

  const selectedBox = useMemo(
    () => config.find((box) => box.type === selectedType) || null,
    [config, selectedType]
  )

  const openSelectedLootbox = async () => {
    if (!selectedBox || opening) return
    if (Number(selectedBox.inventory_count || 0) <= 0) {
      setError('У вас нет этого лутбокса')
      return
    }

    setOpening(true)
    setResult(null)
    setError(null)

    try {
      const response = await api.openLootbox(selectedBox.type)
      queueRewardNotifications(response?.data)
      setTimeout(async () => {
        setResult(response.data)
        setOpening(false)
        await loadData()
      }, 1200)
    } catch (err) {
      console.error('Ошибка открытия:', err)
      setError(err.message || 'Не удалось открыть лутбокс')
      setOpening(false)
    }
  }

  const queueRewardNotifications = (payload) => {
    if (!payload) return
    const queue = []
    const achievements = Array.isArray(payload.achievement_unlocks) ? payload.achievement_unlocks : []
    const drops = Array.isArray(payload.collection_drops) ? payload.collection_drops : []

    achievements.forEach((unlock, index) => {
      const achievement = unlock?.achievement
      if (!achievement?.title) return
      queue.push({
        id: `lb_ach_${Date.now()}_${index}_${achievement.id || achievement.key || 'x'}`,
        type: 'achievement',
        icon: achievement.icon || '🏆',
        title: achievement.title,
        subtitle: achievement.description || '',
        rarity: achievement.rarity || 'common',
      })
    })

    drops.forEach((drop, index) => {
      const item = drop?.item
      if (!item?.name) return
      const duplicate = Boolean(drop?.is_duplicate)
      queue.push({
        id: `lb_card_${Date.now()}_${index}_${item.id || item.key || 'x'}`,
        type: 'card',
        icon: duplicate ? '♻️' : '🃏',
        title: duplicate ? `Дубликат: ${item.name}` : `Карточка: ${item.name}`,
        subtitle: duplicate
          ? `Компенсация +${Number(drop?.duplicate_compensation?.coins || 0)} монет`
          : `Новая карточка · ${item.rarity_label || 'Обычная'}`,
        rarity: item.rarity || 'common',
      })
    })

    if (queue.length === 0) return
    setRewardNotifications((prev) => [...prev, ...queue].slice(-6))
    addNotificationItems(queue)
    queue.forEach((entry) => setTimeout(() => dismissRewardNotification(entry.id), 5500))
  }

  const dismissRewardNotification = (id) => {
    setRewardNotifications((prev) => prev.filter((item) => item.id !== id))
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="spinner" />
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <RewardNotifications items={rewardNotifications} onDismiss={dismissRewardNotification} />
      <div className="aurora-blob aurora-blob-1 opacity-60" />
      <div className="aurora-blob aurora-blob-2 opacity-60" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-4 pt-4 space-y-4">
        <section className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <button onClick={() => window.history.back()} className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center">←</button>
            <h1 className="text-white font-bold text-lg">Лутбоксы</h1>
            <div className="w-9" />
          </div>
          <p className="text-white/65 text-sm">Гаранты редкости, защита от дублей и карточные дропы.</p>

          {resources && (
            <div className="grid grid-cols-4 gap-2 mt-3 text-center">
              <div className="rounded-xl border border-white/10 bg-white/5 p-2 text-xs text-white/75"><CoinIcon size={16} /> {resources.coins}</div>
              <div className="rounded-xl border border-white/10 bg-white/5 p-2 text-xs text-white/75">💎 {resources.gems}</div>
              <div className="rounded-xl border border-white/10 bg-white/5 p-2 text-xs text-white/75">💡 {resources.hints}</div>
              <div className="rounded-xl border border-white/10 bg-white/5 p-2 text-xs text-white/75">🎫 {resources.tickets ?? resources.lives}</div>
            </div>
          )}
        </section>

        {error && <div className="rounded-2xl border border-red-400/35 bg-red-500/12 p-3 text-red-100 text-sm">{error}</div>}

        <section className="grid grid-cols-2 gap-2">
          {config.map((box) => {
            const visual = BOX_VISUALS[box.type] || BOX_VISUALS.bronze
            const selected = box.type === selectedType
            return (
              <button
                key={box.type}
                onClick={() => setSelectedType(box.type)}
                className={`rounded-2xl border p-3 text-left bg-gradient-to-br ${visual.gradient} ${selected ? 'border-white/60' : 'border-white/20'}`}
              >
                <div className="flex items-center justify-between">
                  <span className="text-2xl">{visual.icon}</span>
                  <span className="text-white/90 font-bold">x{box.inventory_count}</span>
                </div>
                <p className="text-white font-semibold text-sm mt-2">{visual.title}</p>
                <p className="text-[11px] text-white/70 mt-1">Эпик через: {box.pity?.epic_remaining}</p>
                <p className="text-[11px] text-white/70">Легенда через: {box.pity?.legendary_remaining}</p>
              </button>
            )
          })}
        </section>

        {selectedBox && (
          <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
            <h2 className="text-white font-semibold mb-2">Шансы и гарант: {BOX_VISUALS[selectedBox.type]?.title || selectedBox.label}</h2>
            <div className="grid grid-cols-2 gap-2 text-xs">
              {Object.entries(selectedBox.rarity_weights || {}).map(([rarity, weight]) => (
                <div key={rarity} className={`rounded-lg border px-2 py-1 ${RARITY_BADGE[rarity] || RARITY_BADGE.common}`}>
                  {RARITY_LABEL[rarity] || rarity}: {weight}%
                </div>
              ))}
            </div>
            <p className="text-white/60 text-xs mt-3">Карточных дропов за открытие: {selectedBox.card_drops}</p>
            <p className="text-white/60 text-xs">Наград в открытии: {selectedBox.rewards_count?.[0]}-{selectedBox.rewards_count?.[1]}</p>

            <button
              onClick={openSelectedLootbox}
              disabled={opening || Number(selectedBox.inventory_count || 0) <= 0}
              className={`w-full mt-4 py-3 rounded-2xl font-bold ${
                opening || Number(selectedBox.inventory_count || 0) <= 0
                  ? 'bg-white/10 text-white/40'
                  : 'bg-white text-slate-900'
              }`}
            >
              {opening ? 'Открытие...' : 'Открыть лутбокс'}
            </button>
          </section>
        )}

        {selectedBox && (
          <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
            <h3 className="text-white font-semibold text-sm mb-2">Пул наград</h3>
            <div className="space-y-2">
              {Object.entries(rewardPool || {}).map(([rarity, rewards]) => (
                <div key={rarity}>
                  <p className={`inline-flex px-2 py-0.5 rounded-full border text-[10px] mb-1 ${RARITY_BADGE[rarity] || RARITY_BADGE.common}`}>
                    {RARITY_LABEL[rarity] || rarity}
                  </p>
                  <div className="flex flex-wrap gap-1">
                    {(Array.isArray(rewards) ? rewards : []).slice(0, 6).map((r, idx) => (
                      <span key={`${rarity}-${idx}`} className="text-[11px] text-white/70 border border-white/10 rounded-md px-2 py-1">
                        {rewardName(r.type, `${r.amount?.[0] || ''}-${r.amount?.[1] || ''}`)}
                      </span>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}
      </div>

      <AnimatePresence>
        {opening && selectedBox && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center">
            <motion.div
              animate={{ rotate: [0, -8, 8, -6, 6, 0], scale: [1, 1.06, 1] }}
              transition={{ duration: 0.9, repeat: Infinity }}
              className="text-8xl"
            >
              {BOX_VISUALS[selectedBox.type]?.icon || '🎁'}
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {result && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 bg-black/80 z-50 p-4 flex items-center justify-center">
            <motion.div initial={{ scale: 0.92, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} exit={{ scale: 0.95, opacity: 0 }} className="w-full max-w-md rounded-3xl border border-white/15 bg-[#0d1020] p-4">
              <h2 className="text-white font-bold text-lg mb-3">Награды получены</h2>
              <div className="space-y-2 max-h-[40vh] overflow-y-auto pr-1">
                {(result.rewards || []).map((reward, index) => (
                  <div key={`reward-${index}`} className="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center gap-3">
                    <div className="text-2xl">{rewardIcon(reward.type)}</div>
                    <div className="min-w-0">
                      <p className="text-white text-sm font-semibold">{rewardName(reward.type, reward.amount)}</p>
                      <p className="text-[11px] text-white/55">{RARITY_LABEL[reward.rarity] || reward.rarity}</p>
                    </div>
                  </div>
                ))}
              </div>

              {(result.collection_drops || []).length > 0 && (
                <div className="mt-3 rounded-xl border border-cyan-300/20 bg-cyan-500/10 p-3">
                  <p className="text-cyan-100 text-sm font-semibold mb-1">Карточные дропы</p>
                  {(result.collection_drops || []).map((drop, idx) => (
                    <p key={`drop-${idx}`} className="text-xs text-cyan-50/90">
                      {drop?.is_duplicate ? '♻️' : '🃏'} {drop?.item?.name || 'Карточка'}
                    </p>
                  ))}
                </div>
              )}

              {result.duplicate_protection_bonus && (
                <div className="mt-3 rounded-xl border border-amber-300/25 bg-amber-500/10 p-3 text-amber-100 text-xs">
                  Компенсация за дубли: +{result.duplicate_protection_bonus.amount} монет
                </div>
              )}

              <button
                onClick={() => setResult(null)}
                className="w-full mt-4 py-3 rounded-xl bg-white text-slate-900 font-semibold"
              >
                Забрать
              </button>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default LootboxPage

