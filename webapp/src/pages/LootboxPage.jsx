import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../api/client'
import { useTelegram } from '../hooks/useTelegram'
import CoinIcon from '../components/CoinIcon'
import RewardNotifications from '../components/RewardNotifications'
import { addNotificationItems } from '../utils/notificationInbox'

const LootboxPage = () => {
  const { webApp } = useTelegram()
  const [inventory, setInventory] = useState(null)
  const [selectedType, setSelectedType] = useState(null)
  const [opening, setOpening] = useState(false)
  const [rewards, setRewards] = useState(null)
  const [rewardNotifications, setRewardNotifications] = useState([])
  const [loading, setLoading] = useState(true)

  // Показываем кнопку Назад
  useEffect(() => {
    if (webApp?.BackButton) {
      webApp.BackButton.show()
      webApp.BackButton.onClick(() => window.history.back())
      return () => webApp.BackButton.hide()
    }
  }, [webApp])

  const lootboxTypes = {
    bronze: {
      name: 'Бронзовый лутбокс',
      icon: '📦',
      color: 'from-gray-600 to-gray-700',
      description: '2-3 случайные награды',
    },
    silver: {
      name: 'Серебряный лутбокс',
      icon: '📦',
      color: 'from-gray-400 to-gray-500',
      description: '3-4 награды, шанс на редкие',
    },
    gold: {
      name: 'Золотой лутбокс',
      icon: '🎁',
      color: 'from-yellow-500 to-orange-500',
      description: '4-5 наград, гарантированная редкая',
    },
    legendary: {
      name: 'Легендарный лутбокс',
      icon: '💎',
      color: 'from-purple-500 to-pink-500',
      description: '5-6 наград, шанс на легендарные',
    },
  }

  const rarityColors = {
    common: 'bg-gray-500',
    uncommon: 'bg-green-500',
    rare: 'bg-blue-500',
    epic: 'bg-purple-500',
    legendary: 'bg-yellow-500',
  }

  useEffect(() => {
    loadInventory()
  }, [])

  const loadInventory = async () => {
    setLoading(true)
    try {
      const response = await api.getInventory()
      setInventory(response.data)
    } catch (error) {
      console.error('Ошибка загрузки инвентаря:', error)
      webApp?.showAlert?.('Ошибка загрузки данных')
    } finally {
      setLoading(false)
    }
  }

  const handleOpenLootbox = async () => {
    if (!selectedType || opening) return

    setOpening(true)
    setRewards(null)

    try {
      const response = await api.openLootbox(selectedType)
      queueRewardNotifications(response.data)
      
      // Показываем анимацию открытия
      setTimeout(() => {
        setRewards(response.data.rewards)
        setOpening(false)
        loadInventory()
      }, 2000)

    } catch (error) {
      console.error('Ошибка открытия лутбокса:', error)
      webApp?.showAlert?.(`Ошибка: ${error.message}`)
      setOpening(false)
    }
  }

  const getRewardIcon = (type) => {
    const icons = {
      coins: '🪙',
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
    return icons[type] || '🎁'
  }

  const getRewardName = (type, amount) => {
    const names = {
      coins: `${amount} монет`,
      exp: `${amount} опыта`,
      gems: `${amount} кристаллов`,
      hint: `${amount} подсказок`,
      life: `${amount} билетов`,
      boost_12h: 'Буст 12ч',
      boost_24h: 'Буст 24ч',
      boost_7d: 'Буст 7 дней',
      cosmetic_epic: 'Эпическая косметика',
      cosmetic_legendary: 'Легендарная косметика',
    }
    return names[type] || 'Награда'
  }

  const availableLootboxes = inventory?.items?.filter(item => item.type === 'lootbox') || []

  const dismissRewardNotification = (id) => {
    setRewardNotifications((prev) => prev.filter((item) => item.id !== id))
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
      const isDuplicate = Boolean(drop?.is_duplicate)
      const coins = isDuplicate
        ? Number(drop?.duplicate_compensation?.coins || 0)
        : Number(drop?.new_card_bonus?.coins || 0)
      queue.push({
        id: `lb_card_${Date.now()}_${index}_${item.id || item.key || 'x'}`,
        type: 'card',
        icon: isDuplicate ? '♻️' : '🃏',
        title: isDuplicate ? `Дубликат: ${item.name}` : `Карточка: ${item.name}`,
        subtitle: isDuplicate
          ? `Обмен на +${coins} монет`
          : `Редкость: ${item.rarity_label || drop.rarity_label || 'Обычная'}${coins > 0 ? ` · +${coins} монет` : ''}`,
        rarity: item.rarity || 'common',
      })
    })

    if (queue.length === 0) return

    setRewardNotifications((prev) => [...prev, ...queue].slice(-6))
    addNotificationItems(queue)
    queue.forEach((entry) => {
      setTimeout(() => dismissRewardNotification(entry.id), 5500)
    })
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-dark-950 to-dark-900 flex items-center justify-center">
        <div className="inline-block animate-spin rounded-full h-12 w-12 border-4 border-game-primary border-t-transparent" />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-dark-950 to-dark-900 pb-24">
      <RewardNotifications items={rewardNotifications} onDismiss={dismissRewardNotification} />
      {/* Header */}
      <div className="glass-effect border-b border-white/10 p-4">
        <div className="flex items-center justify-between mb-2">
          <button
            onClick={() => window.history.back()}
            className="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"
          >
            <span className="text-xl">←</span>
          </button>
          <h1 className="text-2xl font-bold text-white">
            🎁 Лутбоксы
          </h1>
          <div className="w-10" />
        </div>
        <p className="text-white/60 text-center text-sm">
          Открывай лутбоксы и получай награды!
        </p>
      </div>

      {/* Available Lootboxes */}
      <div className="p-4">
        {availableLootboxes.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-7xl mb-4">📦</div>
            <p className="text-white/40 text-lg">У вас нет лутбоксов</p>
            <p className="text-white/30 text-sm mt-2">
              Получите их из колеса фортуны или купите в магазине
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            <h2 className="text-white font-bold text-lg">Ваши лутбоксы:</h2>
            {availableLootboxes.map((box) => {
              const typeInfo = lootboxTypes[box.key]
              return (
                <motion.button
                  key={box.id}
                  onClick={() => setSelectedType(box.key)}
                  className="w-full"
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                >
                  <div className={`
                    relative overflow-hidden rounded-2xl p-6
                    bg-gradient-to-br ${typeInfo?.color || 'from-gray-600 to-gray-700'}
                    border-2 border-white/30
                  `}>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-4">
                        <div className="text-6xl">{typeInfo?.icon}</div>
                        <div className="text-left">
                          <h3 className="text-white font-bold text-xl">
                            {typeInfo?.name}
                          </h3>
                          <p className="text-white/80 text-sm">
                            {typeInfo?.description}
                          </p>
                        </div>
                      </div>
                      <div className="text-right">
                        <div className="text-4xl font-bold text-white">
                          ×{box.quantity}
                        </div>
                      </div>
                    </div>
                  </div>
                </motion.button>
              )
            })}
          </div>
        )}
      </div>

      {/* Open Button */}
      {selectedType && (
        <div className="fixed bottom-24 left-0 right-0 p-4 glass-effect border-t border-white/10">
          <motion.button
            onClick={handleOpenLootbox}
            disabled={opening}
            className={`
              w-full py-5 rounded-2xl font-bold text-lg transition-all
              ${opening
                ? 'bg-white/20 text-white/50 cursor-not-allowed'
                : 'bg-gradient-to-r from-game-primary to-purple-600 text-white shadow-lg hover:shadow-xl'
              }
            `}
            whileHover={!opening ? { scale: 1.02 } : {}}
            whileTap={!opening ? { scale: 0.98 } : {}}
          >
            {opening ? 'Открытие...' : `🎁 Открыть ${lootboxTypes[selectedType]?.name}`}
          </motion.button>
        </div>
      )}

      {/* Opening Animation */}
      <AnimatePresence>
        {opening && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
          >
            <motion.div
              className="text-9xl"
              animate={{
                scale: [1, 1.2, 1],
                rotate: [0, 10, -10, 0],
              }}
              transition={{
                duration: 0.5,
                repeat: Infinity,
              }}
            >
              {lootboxTypes[selectedType]?.icon}
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Rewards Modal */}
      <AnimatePresence>
        {rewards && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80"
            onClick={() => {
              setRewards(null)
              setSelectedType(null)
            }}
          >
            <motion.div
              initial={{ scale: 0.5, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.5, opacity: 0 }}
              className="relative w-full max-w-md p-6 rounded-3xl bg-gradient-to-br from-purple-600 to-pink-600 border-4 border-white overflow-hidden"
              onClick={(e) => e.stopPropagation()}
            >
              {/* Sparkles Animation */}
              <div className="absolute inset-0 pointer-events-none">
                {[...Array(20)].map((_, i) => (
                  <motion.div
                    key={i}
                    className="absolute text-2xl"
                    initial={{
                      x: '50%',
                      y: '50%',
                      scale: 0,
                    }}
                    animate={{
                      x: `${Math.random() * 100}%`,
                      y: `${Math.random() * 100}%`,
                      scale: [0, 1, 0],
                    }}
                    transition={{
                      duration: 1.5,
                      delay: i * 0.1,
                    }}
                  >
                    ✨
                  </motion.div>
                ))}
              </div>

              <div className="relative">
                <h2 className="text-3xl font-bold text-white text-center mb-6">
                  🎉 Ваши награды!
                </h2>

                <div className="space-y-3 mb-6">
                  {rewards.map((reward, index) => (
                    <motion.div
                      key={index}
                      initial={{ x: -50, opacity: 0 }}
                      animate={{ x: 0, opacity: 1 }}
                      transition={{ delay: index * 0.1 }}
                      className={`
                        flex items-center gap-4 p-4 rounded-xl
                        ${rarityColors[reward.rarity] || 'bg-white/20'}
                        border-2 border-white/50
                      `}
                    >
                      <div className="text-4xl">
                        {reward.type === 'coins' ? <CoinIcon size={40} /> : getRewardIcon(reward.type)}
                      </div>
                      <div className="flex-1">
                        <div className="font-bold text-white">
                          {getRewardName(reward.type, reward.amount)}
                        </div>
                        <div className="text-xs text-white/80 uppercase">
                          {reward.rarity}
                        </div>
                      </div>
                    </motion.div>
                  ))}
                </div>

                <button
                  onClick={() => {
                    setRewards(null)
                    setSelectedType(null)
                  }}
                  className="w-full py-4 bg-white text-purple-600 rounded-xl font-bold text-lg hover:bg-white/90 transition-colors"
                >
                  Забрать награды
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default LootboxPage
