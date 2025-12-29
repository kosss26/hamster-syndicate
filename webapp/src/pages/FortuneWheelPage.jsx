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

  // Показываем кнопку Назад
  useEffect(() => {
    if (webApp?.BackButton) {
      webApp.BackButton.show()
      webApp.BackButton.onClick(() => window.history.back())
      return () => webApp.BackButton.hide()
    }
  }, [webApp])

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

  // Цвета для секторов
  const sectorColors = [
    '#ff6b6b', // красный
    '#4ecdc4', // бирюзовый
    '#45b7d1', // голубой
    '#f7dc6f', // желтый
    '#bb8fce', // фиолетовый
    '#52be80', // зеленый
    '#eb984e', // оранжевый
    '#f1948a', // розовый
  ]

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
        <div className="flex items-center justify-between mb-2">
          <button
            onClick={() => window.history.back()}
            className="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"
          >
            <span className="text-xl">←</span>
          </button>
          <h1 className="text-2xl font-bold text-white">
            🎰 Колесо фортуны
          </h1>
          <div className="w-10" />
        </div>
        <p className="text-white/60 text-center text-sm">
          Крути колесо и выигрывай призы!
        </p>
      </div>

      {/* Wheel Container */}
      <div className="relative py-8">
        {/* Custom Pointer */}
        <div className="absolute top-8 left-1/2 -translate-x-1/2 z-10">
          <img 
            src="/api/images/wheel/pointer.png" 
            alt="pointer"
            className="w-16 h-16 drop-shadow-2xl animate-bounce"
            style={{ animationDuration: '2s', animationIterationCount: 'infinite' }}
            onError={(e) => {
              // Fallback на CSS треугольник если картинка не загрузилась
              e.target.style.display = 'none'
              const fallback = document.createElement('div')
              fallback.className = 'w-0 h-0 border-l-[20px] border-r-[20px] border-t-[30px] border-l-transparent border-r-transparent border-t-red-500 drop-shadow-2xl'
              e.target.parentNode.appendChild(fallback)
            }}
          />
        </div>

        {/* Wheel */}
        <div className="flex justify-center items-center px-4 mt-8">
          <div className="relative w-full max-w-sm aspect-square">
            <motion.svg
              ref={wheelRef}
              viewBox="0 0 200 200"
              className="w-full h-full drop-shadow-2xl"
              animate={{ rotate: rotation }}
              transition={{
                duration: 4,
                ease: [0.25, 0.1, 0.25, 1],
              }}
            >
              {/* Draw sectors with clear borders */}
              {config.map((sector, index) => {
                const angle = 360 / config.length
                const startAngle = (index * angle - 90) * (Math.PI / 180)
                const endAngle = ((index + 1) * angle - 90) * (Math.PI / 180)
                
                const x1 = 100 + 90 * Math.cos(startAngle)
                const y1 = 100 + 90 * Math.sin(startAngle)
                const x2 = 100 + 90 * Math.cos(endAngle)
                const y2 = 100 + 90 * Math.sin(endAngle)
                
                const largeArc = angle > 180 ? 1 : 0
                
                const pathData = [
                  `M 100 100`,
                  `L ${x1} ${y1}`,
                  `A 90 90 0 ${largeArc} 1 ${x2} ${y2}`,
                  `Z`
                ].join(' ')

                // Text position
                const textAngle = (index * angle + angle / 2 - 90) * (Math.PI / 180)
                const textX = 100 + 60 * Math.cos(textAngle)
                const textY = 100 + 60 * Math.sin(textAngle)
                
                return (
                  <g key={index}>
                    {/* Sector */}
                    <path
                      d={pathData}
                      fill={sectorColors[index % sectorColors.length]}
                      stroke="#ffffff"
                      strokeWidth="2"
                    />
                    
                    {/* Icon/Amount text */}
                    <text
                      x={textX}
                      y={textY}
                      textAnchor="middle"
                      dominantBaseline="middle"
                      fontSize="14"
                      fontWeight="bold"
                      fill="#ffffff"
                      style={{ textShadow: '0 2px 4px rgba(0,0,0,0.5)' }}
                    >
                      {sector.icon}
                    </text>
                    <text
                      x={textX}
                      y={textY + 12}
                      textAnchor="middle"
                      dominantBaseline="middle"
                      fontSize="10"
                      fontWeight="bold"
                      fill="#ffffff"
                      style={{ textShadow: '0 2px 4px rgba(0,0,0,0.5)' }}
                    >
                      {sector.amount}
                    </text>
                  </g>
                )
              })}
              
              {/* Center circle */}
              <circle
                cx="100"
                cy="100"
                r="25"
                fill="url(#centerGradient)"
                stroke="#ffffff"
                strokeWidth="3"
              />
              
              {/* Center text */}
              <text
                x="100"
                y="105"
                textAnchor="middle"
                dominantBaseline="middle"
                fontSize="24"
              >
                🎰
              </text>
              
              {/* Gradient definition */}
              <defs>
                <linearGradient id="centerGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stopColor="#8b5cf6" />
                  <stop offset="100%" stopColor="#ec4899" />
                </linearGradient>
              </defs>
            </motion.svg>

            {/* Outer ring for 3D effect */}
            <div className="absolute inset-0 rounded-full border-8 border-white/20 pointer-events-none" />
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
