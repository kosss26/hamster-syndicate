import { motion } from 'framer-motion'

// Градиенты для разных рамок
const FRAME_STYLES = {
  default: {
    gradient: 'from-gray-500 to-gray-600',
    glow: 'from-gray-500/20 to-gray-600/20',
  },
  winner: {
    gradient: 'from-yellow-400 to-amber-600',
    glow: 'from-yellow-400/30 to-amber-600/30',
  },
  streak: {
    gradient: 'from-orange-500 to-red-600',
    glow: 'from-orange-500/30 to-red-600/30',
  },
  legend: {
    gradient: 'from-purple-500 to-purple-700',
    glow: 'from-purple-500/30 to-purple-700/30',
  },
  rainbow: {
    gradient: 'from-pink-500 via-purple-500 to-blue-500',
    glow: 'from-pink-500/30 via-purple-500/30 to-blue-500/30',
  },
  diamond: {
    gradient: 'from-cyan-400 via-blue-500 to-purple-600',
    glow: 'from-cyan-400/30 via-blue-500/30 to-purple-600/30',
  },
  royal: {
    gradient: 'from-yellow-300 via-yellow-500 to-yellow-700',
    glow: 'from-yellow-300/40 via-yellow-500/40 to-yellow-700/40',
  },
  lightning: {
    gradient: 'from-yellow-300 via-purple-500 to-blue-600',
    glow: 'from-yellow-300/30 via-purple-500/30 to-blue-600/30',
  },
}

/**
 * Компонент аватара с рамкой
 * 
 * @param {string} photoUrl - URL фото пользователя
 * @param {string} name - Имя пользователя (для fallback)
 * @param {string} frameKey - Ключ рамки (default, winner, royal и т.д.)
 * @param {number} size - Размер в пикселях (по умолчанию 112)
 * @param {boolean} animated - Анимировать ли рамку (вращение)
 * @param {boolean} showGlow - Показывать ли свечение вокруг рамки
 */
function AvatarWithFrame({ 
  photoUrl, 
  name = '?', 
  frameKey = 'default',
  size = 112,
  animated = false,
  showGlow = true,
  className = ''
}) {
  const frameStyle = FRAME_STYLES[frameKey] || FRAME_STYLES.default
  const initial = name[0]?.toUpperCase() || '?'
  
  // Размеры рамки
  const frameWidth = Math.max(3, size * 0.06) // ~6% от размера
  const innerSize = size - frameWidth * 2

  return (
    <div className={`relative ${className}`} style={{ width: size, height: size }}>
      {/* Outer glow (опционально) */}
      {showGlow && (
        <div 
          className={`absolute -inset-2 rounded-full bg-gradient-to-r ${frameStyle.glow} blur-lg opacity-50`}
        />
      )}
      
      {/* Frame container */}
      <motion.div 
        className="relative w-full h-full rounded-full"
        animate={animated ? { rotate: 360 } : {}}
        transition={animated ? {
          duration: 3,
          repeat: Infinity,
          ease: "linear"
        } : {}}
      >
        {/* Frame gradient border */}
        <div 
          className={`absolute inset-0 rounded-full bg-gradient-to-br ${frameStyle.gradient}`}
          style={{ padding: frameWidth }}
        >
          {/* Inner background (for avatar) */}
          <div className="w-full h-full rounded-full bg-gradient-to-br from-game-primary to-purple-700 overflow-hidden flex items-center justify-center">
            {photoUrl ? (
              <img 
                src={photoUrl} 
                alt={name}
                className="w-full h-full object-cover"
                onError={(e) => {
                  // Fallback если изображение не загрузилось
                  e.target.style.display = 'none'
                  e.target.nextElementSibling.style.display = 'flex'
                }}
              />
            ) : null}
            
            {/* Fallback: первая буква имени */}
            <span 
              className="absolute inset-0 flex items-center justify-center font-bold text-white"
              style={{ 
                fontSize: size * 0.4,
                display: photoUrl ? 'none' : 'flex'
              }}
            >
              {initial}
            </span>
          </div>
        </div>
      </motion.div>
    </div>
  )
}

export default AvatarWithFrame

