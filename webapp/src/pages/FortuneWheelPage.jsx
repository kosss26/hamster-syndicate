import { useState, useEffect, useRef } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../api/client'
import { useTelegram } from '../hooks/useTelegram'

const FortuneWheelPage = () => {
  const { webApp } = useTelegram()
  const [wheelData, setWheelData] = useState(null)
  const [config, setConfig] = useState([])
  const [spinning, setSpinning] = useState(false)
  const [reward, setReward] = useState(null)
  const [rotation, setRotation] = useState(0)
  const [loading, setLoading] = useState(true)
  const wheelRef = useRef(null)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    try {
      const [statusData, configData] = await Promise.all([
        api.getWheelStatus(),
        api.getWheelConfig(),
      ])
      setWheelData(statusData.data)
      setConfig(configData.data.sectors)
    } catch (error) {
      console.error('Ошибка загрузки:', error)
      webApp?.showAlert?.('Ошибка загрузки данных')
    } finally {
      setLoading(false)
    }
  }

  const handleSpin = async (usePremium = false) => {
    if (spinning) return

    if (!usePremium && !wheelData?.can_spin_free) {
      webApp?.showAlert?.(`Следующее вращение через ${wheelData?.hours_left || 0} часов`)
      return
    }

    setSpinning(true)
    setReward(null)

    try {
      const response = await api.spinWheel(usePremium)
      const result = response.data

      // Находим индекс сектора с наградой
      const rewardIndex = config.findIndex(
        s => s.type === result.reward.type && s.amount === result.reward.amount
      )
      
      // Вычисляем угол поворота
      const sectorAngle = 360 / config.length
      const targetAngle = rewardIndex * sectorAngle
      const spins = 5 // Количество полных оборотов
      const finalRotation = rotation + (360 * spins) + (360 - targetAngle)

      // Анимируем вращение
      setRotation(finalRotation)

      // Показываем награду через 4 секунды
      setTimeout(() => {
        setReward(result.reward)
        setSpinning(false)
        loadData()
      }, 4000)

    } catch (error) {
      console.error('Ошибка вращения:', error)
      webApp?.showAlert?.(`Ошибка: ${error.message}`)
      setSpinning(false)
    }
  }

  const formatTime = (hours) => {
    if (hours < 1) return 'Менее часа'
    if (hours === 1) return '1 час'
    if (hours < 5) return `${hours} часа`
    return `${hours} часов`
  }

  const getRewardText = (type, amount) => {
    const texts = {
      coins: `${amount} монет`,
      exp: `${amount} опыта`,
      hint: `${amount} подсказок`,
      life: `${amount} жизней`,
      gems: `${amount} кристаллов`,
      lootbox: 'Лутбокс',
    }
    return texts[type] || 'Награда'
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
      {/* Header */}
      <div className="glass-effect border-b border-white/10 p-4">
        <h1 className="text-2xl font-bold text-white text-center mb-2">
          🎰 Колесо фортуны
        </h1>
        <p className="text-white/60 text-center text-sm">
          Крути колесо и выигрывай призы!
        </p>
      </div>

      {/* Wheel Container */}
      <div className="relative py-8">
        {/* Arrow Pointer */}
        <div className="absolute top-0 left-1/2 -translate-x-1/2 z-10">
          <div className="text-5xl drop-shadow-lg">👇</div>
        </div>

        {/* Wheel */}
        <div className="flex justify-center items-center px-4">
          <div className="relative w-full max-w-sm aspect-square">
            <motion.div
              ref={wheelRef}
              className="absolute inset-0 rounded-full overflow-hidden border-8 border-game-primary shadow-2xl"
              style={{
                background: 'conic-gradient(from 0deg, #ff6b6b 0deg, #4ecdc4 45deg, #45b7d1 90deg, #f7dc6f 135deg, #bb8fce 180deg, #52be80 225deg, #eb984e 270deg, #f1948a 315deg)',
              }}
              animate={{ rotate: rotation }}
              transition={{
                duration: 4,
                ease: [0.25, 0.1, 0.25, 1],
              }}
            >
              {/* Sectors */}
              {config.map((sector, index) => {
                const angle = (360 / config.length) * index
                return (
                  <div
                    key={index}
                    className="absolute top-1/2 left-1/2 origin-left"
                    style={{
                      transform: `rotate(${angle}deg)`,
                      width: '50%',
                    }}
                  >
                    <div className="absolute left-1/4 top-1/2 -translate-y-1/2 text-2xl">
                      {sector.icon}
                    </div>
                  </div>
                )
              })}

              {/* Center Circle */}
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="w-24 h-24 rounded-full bg-gradient-to-br from-game-primary to-purple-600 border-4 border-white shadow-lg flex items-center justify-center">
                  <span className="text-4xl font-bold text-white">🎰</span>
                </div>
              </div>
            </motion.div>
          </div>
        </div>
      </div>

      {/* Spin Buttons */}
      <div className="px-4 space-y-3">
        {wheelData?.can_spin_free ? (
          <motion.button
            onClick={() => handleSpin(false)}
            disabled={spinning}
            className={`
              w-full py-5 rounded-2xl font-bold text-lg transition-all
              ${spinning
                ? 'bg-white/20 text-white/50 cursor-not-allowed'
                : 'bg-gradient-to-r from-game-primary to-purple-600 text-white shadow-lg hover:shadow-xl active:scale-95'
              }
            `}
            whileHover={!spinning ? { scale: 1.02 } : {}}
            whileTap={!spinning ? { scale: 0.98 } : {}}
          >
            {spinning ? 'Вращается...' : '🎁 Бесплатное вращение'}
          </motion.button>
        ) : (
          <div className="bg-white/5 rounded-2xl p-5 border border-white/10 text-center">
            <div className="text-3xl mb-2">⏰</div>
            <div className="text-white/60">Следующее вращение через:</div>
            <div className="text-2xl font-bold text-white mt-1">
              {formatTime(wheelData?.hours_left)}
            </div>
          </div>
        )}

        <motion.button
          onClick={() => handleSpin(true)}
          disabled={spinning}
          className={`
            w-full py-4 rounded-2xl font-medium text-base transition-all border-2
            ${spinning
              ? 'bg-white/10 border-white/20 text-white/50 cursor-not-allowed'
              : 'bg-transparent border-purple-500 text-white hover:bg-purple-500/20'
            }
          `}
          whileHover={!spinning ? { scale: 1.02 } : {}}
          whileTap={!spinning ? { scale: 0.98 } : {}}
        >
          💎 Крутить за 50 кристаллов
        </motion.button>
      </div>

      {/* Stats */}
      <div className="px-4 mt-6">
        <div className="bg-white/5 rounded-2xl p-4 border border-white/10">
          <h3 className="text-white font-bold mb-3">📊 Статистика</h3>
          <div className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-white/60">Всего вращений:</span>
              <span className="text-white font-bold">{wheelData?.total_spins || 0}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-white/60">Streak (дни подряд):</span>
              <span className="text-white font-bold">
                {wheelData?.wheel_streak || 0} 🔥
              </span>
            </div>
            {wheelData?.wheel_streak >= 7 && (
              <div className="mt-2 p-2 bg-green-500/20 rounded-lg border border-green-500/30 text-center">
                <span className="text-green-400 font-bold text-xs">
                  ✨ Бонус +10% к наградам!
                </span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Recent History */}
      {wheelData?.history && wheelData.history.length > 0 && (
        <div className="px-4 mt-4">
          <h3 className="text-white font-bold mb-3">📜 Последние вращения</h3>
          <div className="space-y-2">
            {wheelData.history.slice(0, 5).map((spin, index) => (
              <div
                key={index}
                className="bg-white/5 rounded-xl p-3 border border-white/10 flex items-center justify-between"
              >
                <div className="flex items-center gap-3">
                  <span className="text-2xl">
                    {config.find(s => s.type === spin.reward_type)?.icon || '🎁'}
                  </span>
                  <span className="text-white text-sm">
                    {getRewardText(spin.reward_type, spin.reward_amount)}
                  </span>
                </div>
                <span className="text-white/40 text-xs">
                  {spin.created_at}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Reward Modal */}
      <AnimatePresence>
        {reward && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80"
            onClick={() => setReward(null)}
          >
            <motion.div
              initial={{ scale: 0.5, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.5, opacity: 0 }}
              className="relative w-full max-w-md p-8 rounded-3xl bg-gradient-to-br from-yellow-500 to-orange-600 border-4 border-white"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="text-center">
                <motion.div
                  className="text-8xl mb-4"
                  initial={{ scale: 0 }}
                  animate={{ scale: [0, 1.2, 1] }}
                  transition={{ duration: 0.5, times: [0, 0.6, 1] }}
                >
                  {config.find(s => s.type === reward.type)?.icon || '🎁'}
                </motion.div>
                
                <h2 className="text-3xl font-bold text-white mb-2">
                  Поздравляем!
                </h2>
                
                <p className="text-2xl font-bold text-white mb-6">
                  {getRewardText(reward.type, reward.amount)}
                </p>
                
                {reward.streak_bonus && (
                  <div className="mb-4 p-3 bg-white/20 rounded-xl">
                    <span className="text-white font-bold text-sm">
                      ✨ Применён streak бонус +10%!
                    </span>
                  </div>
                )}

                <button
                  onClick={() => setReward(null)}
                  className="w-full py-4 bg-white text-orange-600 rounded-xl font-bold text-lg hover:bg-white/90 transition-colors"
                >
                  Забрать награду
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default FortuneWheelPage

