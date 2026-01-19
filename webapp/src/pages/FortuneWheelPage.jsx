import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../api/client'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import CoinIcon from '../components/CoinIcon'

const FortuneWheelPage = () => {
  const { user } = useTelegram()
  const navigate = useNavigate()
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
    } finally {
      setLoading(false)
    }
  }

  const handleSpin = async (usePremium = false) => {
    if (spinning) return

    if (!usePremium && !wheelData?.can_spin_free) {
        hapticFeedback('error')
        return
    }

    setSpinning(true)
    setReward(null)
    hapticFeedback('medium')

    try {
      const response = await api.spinWheel(usePremium)
      const result = response.data

      // Находим индекс сектора с наградой
      const matchingSectors = config
        .map((s, idx) => ({ sector: s, index: idx }))
        .filter(item => item.sector.type === result.reward.type && item.sector.amount === result.reward.amount)
      
      const rewardIndex = matchingSectors.length > 0 
        ? matchingSectors[Math.floor(Math.random() * matchingSectors.length)].index
        : 0
      
      const sectorAngle = 360 / config.length
      // Добавляем случайное вращение для реалистичности
      const spins = 5
      // Целевой угол должен указывать на 12 часов (270 градусов в SVG координатах или -90)
      // Но наша стрелка сверху (top). 
      // Если сектор 0 начинается в -90deg, то его центр в -90 + angle/2.
      // Чтобы сектор i оказался наверху, колесо нужно повернуть так, чтобы центр сектора i совпал с -90.
      
      // Текущий угол поворота колеса rotation.
      // Сектор i находится в диапазоне [i*angle, (i+1)*angle] (если считать от 0)
      // В SVG мы рисуем от -90.
      // Центр сектора i: -90 + i*angle + angle/2.
      // Мы хотим, чтобы этот угол после вращения оказался в позиции стрелки (-90).
      // newRotation = currentRotation + delta
      // (center + delta) % 360 = -90
      
      // Проще: targetRotation = - (i * angle + angle/2) - 90?
      // Давайте просто добавим оборотов и вычтем угол позиции сектора.
      
      const sectorCenterAngle = rewardIndex * sectorAngle + (sectorAngle / 2)
      const randomOffset = (Math.random() - 0.5) * (sectorAngle * 0.4) 
      
      // 360 * spins - полный оборот
      // - sectorCenterAngle - поворачиваем назад на угол сектора, чтобы он стал в 0 (справа)
      // - 90 - поворачиваем еще назад, чтобы 0 стал наверху?
      // В текущей реализации SVG сектор 0 рисуется от -90 (12 часов).
      // Значит, если rewardIndex=0, нам нужно повернуть на 0 (или 360).
      // Если rewardIndex=1, он рисуется правее, значит колесо нужно повернуть ПРОТИВ часовой (-), чтобы он стал наверх.
      
      const finalRotation = rotation + (360 * spins) - (rewardIndex * sectorAngle) + randomOffset
      
      setRotation(finalRotation)

      setTimeout(() => {
        setReward(result.reward)
        setSpinning(false)
        hapticFeedback('success')
        setWheelData(prev => ({
          ...prev,
          can_spin_free: false,
          hours_left: result.hours_left || 3,
          minutes_left: 0,
          total_spins: (prev?.total_spins || 0) + 1,
          wheel_streak: result.streak || prev?.wheel_streak
        }))
      }, 4000)

    } catch (error) {
      console.error('Ошибка вращения:', error)
      setSpinning(false)
      hapticFeedback('error')
    }
  }

  const formatTime = (hours, minutes = 0) => {
    if (hours === 0 && minutes < 1) return 'Менее минуты'
    return `${hours}ч ${minutes}м`
  }

  const getRewardText = (type, amount) => {
    const texts = {
      coins: 'Монеты',
      exp: 'Опыт',
      hint: 'Подсказки',
      life: 'Жизни',
      gems: 'Кристаллы',
      lootbox: 'Лутбокс',
    }
    return texts[type] || 'Награда'
  }

  // Обновленная палитра в стиле Cyberpunk/Neon
  const sectorColors = [
    '#6366f1', // Indigo
    '#ec4899', // Pink
    '#8b5cf6', // Violet
    '#3b82f6', // Blue
    '#10b981', // Emerald
    '#f59e0b', // Amber
    '#ef4444', // Red
    '#06b6d4', // Cyan
  ]

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="spinner" />
      </div>
    )
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

      {/* Main Content */}
      <div className="relative z-10 flex-1 flex flex-col items-center justify-center py-4">
        
        {/* Wheel Container */}
        <div className="relative w-[340px] h-[340px] sm:w-[380px] sm:h-[380px]">
           {/* Ambient Glow */}
           <div className="absolute inset-8 rounded-full bg-game-primary/30 blur-[60px] animate-pulse-slow" />
           
           {/* Pointer - сохраняем стиль, позиционируем сверху */}
           <div className="absolute -top-5 left-1/2 -translate-x-1/2 z-30 pointer-events-none drop-shadow-[0_4px_8px_rgba(0,0,0,0.5)]">
              <img 
                src="/api/images/wheel/pointer.png" 
                alt="pointer"
                className="w-16 h-16 object-contain"
                onError={(e) => {
                  e.target.style.display = 'none'
                  e.target.parentNode.innerHTML = '<div class="w-0 h-0 border-l-[15px] border-r-[15px] border-t-[30px] border-l-transparent border-r-transparent border-t-red-500 filter drop-shadow-lg"></div>'
                }}
              />
           </div>

           {/* Wheel SVG */}
           <div className="relative w-full h-full p-2">
               <motion.div 
                 className="w-full h-full rounded-full shadow-2xl relative z-10"
                 animate={{ rotate: rotation }}
                 transition={{
                    duration: 4,
                    ease: [0.2, 0.8, 0.2, 1] // Custom cubic bezier for realistic spin
                 }}
               >
                 <svg viewBox="0 0 200 200" className="w-full h-full rotate-0">
                    <defs>
                        <linearGradient id="centerGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stopColor="#4f46e5" />
                            <stop offset="100%" stopColor="#c026d3" />
                        </linearGradient>
                        <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="0" stdDeviation="2" floodOpacity="0.5"/>
                        </filter>
                    </defs>
                    
                    {/* Outer Ring Background */}
                    <circle cx="100" cy="100" r="98" fill="#1e1b4b" stroke="#312e81" strokeWidth="4" />

                    {config.map((sector, index) => {
                        const angle = 360 / config.length
                        // Рисуем сектора так, чтобы 0-й был сверху (-90deg)
                        // Ноль градусов в SVG - это 3 часа. -90 - это 12 часов.
                        const startAngle = (index * angle - 90) * (Math.PI / 180)
                        const endAngle = ((index + 1) * angle - 90) * (Math.PI / 180)
                        
                        const x1 = 100 + 94 * Math.cos(startAngle)
                        const y1 = 100 + 94 * Math.sin(startAngle)
                        const x2 = 100 + 94 * Math.cos(endAngle)
                        const y2 = 100 + 94 * Math.sin(endAngle)
                        
                        const largeArc = angle > 180 ? 1 : 0
                        const pathData = `M 100 100 L ${x1} ${y1} A 94 94 0 ${largeArc} 1 ${x2} ${y2} Z`
                        
                        // Text position
                        const textAngle = (index * angle + angle / 2 - 90) * (Math.PI / 180)
                        const textX = 100 + 65 * Math.cos(textAngle)
                        const textY = 100 + 65 * Math.sin(textAngle)
                        
                        return (
                            <g key={index}>
                                <path
                                    d={pathData}
                                    fill={sectorColors[index % sectorColors.length]}
                                    stroke="rgba(255,255,255,0.2)"
                                    strokeWidth="1"
                                />
                                {/* Sector Inner Glow/Shadow for depth */}
                                <path
                                    d={pathData}
                                    fill="url(#centerGrad)"
                                    fillOpacity="0"
                                    stroke="none"
                                />
                                
                                {/* Icon/Text */}
                                <g transform={`translate(${textX}, ${textY}) rotate(${index * angle + angle/2})`}>
                                     {/* Поворачиваем контент так, чтобы он был читаем от центра */}
                                     {sector.custom_icon_url ? (
                                          <image
                                            href={sector.custom_icon_url}
                                            x="-10" y="-18" width="20" height="20"
                                            style={{ filter: 'drop-shadow(0 1px 2px rgba(0,0,0,0.5))' }}
                                          />
                                     ) : (
                                          <text
                                            x="0" y="-12"
                                            textAnchor="middle" dominantBaseline="middle"
                                            fontSize="14" fontWeight="bold" fill="white"
                                            style={{ filter: 'drop-shadow(0 1px 2px rgba(0,0,0,0.5))' }}
                                          >
                                            {sector.icon}
                                          </text>
                                     )}
                                     <text
                                        x="0" y="8"
                                        textAnchor="middle" dominantBaseline="middle"
                                        fontSize="11" fontWeight="800" fill="white"
                                        style={{ filter: 'drop-shadow(0 1px 2px rgba(0,0,0,0.5))' }}
                                     >
                                        {sector.amount}
                                     </text>
                                </g>
                            </g>
                        )
                    })}
                    
                    {/* Center Decoration (STATIC) - Removed from here */}
                 </svg>
                 
                 {/* Shiny Overlay on Wheel */}
                 <div className="absolute inset-0 rounded-full bg-gradient-to-tr from-white/10 to-transparent pointer-events-none" />
               </motion.div>

               {/* Static Center Hub */}
               <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20 w-14 h-14 rounded-full bg-gradient-to-br from-[#4f46e5] to-[#c026d3] shadow-[0_0_15px_rgba(79,70,229,0.5)] border-2 border-white/20 flex items-center justify-center pointer-events-none">
                   <div className="w-8 h-8 rounded-full bg-white/10 backdrop-blur-sm shadow-inner" />
               </div>

               {/* Outer Decor Ring (Fixed) */}
               <div className="absolute -inset-1 rounded-full border-[6px] border-[#312e81] pointer-events-none shadow-[0_0_20px_rgba(0,0,0,0.5)] z-0" />
               <div className="absolute -inset-1 rounded-full border border-white/20 pointer-events-none z-20" />
               
               {/* Bulbs on the ring */}
               {[...Array(12)].map((_, i) => (
                   <div 
                     key={i}
                     className={`absolute w-3 h-3 rounded-full ${spinning ? 'animate-pulse' : ''}`}
                     style={{
                         top: '50%', left: '50%',
                         backgroundColor: i % 2 === 0 ? '#fbbf24' : '#f472b6',
                         transform: `translate(-50%, -50%) rotate(${i * 30}deg) translateY(-178px)`,
                         boxShadow: `0 0 10px ${i % 2 === 0 ? '#fbbf24' : '#f472b6'}`
                     }}
                   />
               ))}
           </div>
        </div>
      </div>

      {/* Controls Area */}
      <div className="relative z-10 px-6 space-y-4 mb-8">
           {/* Free Spin */}
           <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => handleSpin(false)}
              disabled={spinning || (!wheelData?.can_spin_free)}
              className={`
                 w-full relative overflow-hidden rounded-2xl p-4 flex items-center justify-between
                 ${wheelData?.can_spin_free 
                    ? 'bg-gradient-to-r from-game-primary to-purple-600 shadow-glow' 
                    : 'bg-white/5 border border-white/10 opacity-80'
                 }
              `}
           >
               <div className="flex flex-col items-start">
                  <span className={`font-bold text-lg ${wheelData?.can_spin_free ? 'text-white' : 'text-white/50'}`}>
                      {wheelData?.can_spin_free ? 'Бесплатное вращение' : 'Следующий спин'}
                  </span>
                  {!wheelData?.can_spin_free && (
                      <span className="text-white font-mono text-sm mt-1">
                          через {formatTime(wheelData?.hours_left, wheelData?.minutes_left)}
                      </span>
                  )}
               </div>
               <div className="text-3xl">🎁</div>
           </motion.button>
           
           {/* Premium Spin */}
           <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => handleSpin(true)}
              disabled={spinning}
              className="w-full bg-white/5 hover:bg-white/10 border border-white/10 rounded-2xl p-4 flex items-center justify-between transition-colors"
           >
              <div className="flex flex-col items-start">
                  <span className="font-bold text-white">Экстра вращение</span>
                  <span className="text-game-accent text-sm">Гарантированный приз</span>
              </div>
              <div className="flex items-center gap-2 bg-white/10 px-3 py-1 rounded-full">
                  <span className="text-white font-bold">50</span>
                  <span>💎</span>
              </div>
           </motion.button>
      </div>
      
      {/* Stats Grid */}
      <div className="relative z-10 px-6 grid grid-cols-2 gap-3 mb-6">
          <div className="bento-card p-3 flex flex-col items-center justify-center bg-white/5">
              <span className="text-white/40 text-xs uppercase font-bold tracking-wider mb-1">Всего спинов</span>
              <span className="text-2xl font-black text-white">{wheelData?.total_spins || 0}</span>
          </div>
          <div className="bento-card p-3 flex flex-col items-center justify-center bg-white/5">
              <span className="text-white/40 text-xs uppercase font-bold tracking-wider mb-1">Стрик дней</span>
              <div className="flex items-center gap-1">
                  <span className="text-2xl font-black text-white">{wheelData?.wheel_streak || 0}</span>
                  <span className="text-orange-500 text-lg">🔥</span>
              </div>
          </div>
      </div>

      {/* Reward Modal */}
      <AnimatePresence>
        {reward && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/80 backdrop-blur-md"
            onClick={() => setReward(null)}
          >
            <motion.div
              initial={{ scale: 0.5, opacity: 0, y: 50 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.8, opacity: 0 }}
              onClick={e => e.stopPropagation()}
              className="relative w-full max-w-sm bg-gradient-to-br from-[#1e1b4b] to-[#0f172a] border border-white/10 rounded-[32px] p-8 text-center shadow-2xl overflow-hidden"
            >
              <div className="absolute inset-0 bg-game-primary/10 blur-xl" />
              <div className="relative z-10">
                  <motion.div 
                     initial={{ scale: 0, rotate: -180 }}
                     animate={{ scale: 1, rotate: 0 }}
                     transition={{ type: "spring", stiffness: 200, delay: 0.2 }}
                     className="w-32 h-32 mx-auto mb-6 flex items-center justify-center"
                  >
                     {reward.type === 'coins' ? (
                       <div className="text-[100px] leading-none drop-shadow-glow">💰</div>
                     ) : (
                       <div className="text-[100px] leading-none drop-shadow-glow">
                         {config.find(s => s.type === reward.type)?.icon || '🎁'}
                       </div>
                     )}
                  </motion.div>
                  
                  <h2 className="text-3xl font-black text-white uppercase italic mb-2">Победа!</h2>
                  <div className="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-orange-500 mb-6">
                      +{reward.amount} {getRewardText(reward.type, reward.amount).split(' ')[0]}
                  </div>
                  
                  {reward.streak_bonus && (
                     <div className="mb-6 py-2 px-4 bg-orange-500/20 border border-orange-500/40 rounded-xl inline-block">
                        <span className="text-orange-400 font-bold text-sm">🔥 Бонус серии +10%</span>
                     </div>
                  )}
                  
                  <button
                    onClick={() => setReward(null)}
                    className="w-full py-4 bg-white rounded-xl text-black font-bold text-lg hover:bg-white/90 transition-transform active:scale-95"
                  >
                    Забрать
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
