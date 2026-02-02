import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'

const FortuneWheelPage = () => {
  const navigate = useNavigate()

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

      {/* Main Content - Заглушка */}
      <div className="relative z-10 flex-1 flex flex-col items-center justify-center px-6">
        <motion.div
          initial={{ scale: 0.8, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{ duration: 0.5 }}
          className="text-center max-w-md"
        >
          {/* Иконка */}
          <motion.div
            animate={{ 
              rotate: [0, 10, -10, 10, -10, 0],
              scale: [1, 1.1, 1]
            }}
            transition={{ 
              duration: 2,
              repeat: Infinity,
              repeatDelay: 3
            }}
            className="text-9xl mb-6"
          >
            🎡
          </motion.div>

          {/* Заголовок */}
          <h2 className="text-3xl font-black text-white uppercase italic mb-4 text-shadow-glow">
            В разработке
          </h2>

          {/* Описание */}
          <p className="text-white/70 text-lg mb-8 leading-relaxed">
            Колесо фортуны скоро появится!<br />
            Следите за обновлениями.
          </p>

          {/* Декоративные элементы */}
          <div className="flex justify-center gap-4 mb-8">
            {['🎁', '💰', '💎', '⭐'].map((emoji, i) => (
              <motion.div
                key={i}
                animate={{ 
                  y: [0, -10, 0],
                  opacity: [0.5, 1, 0.5]
                }}
                transition={{ 
                  duration: 1.5,
                  repeat: Infinity,
                  delay: i * 0.2
                }}
                className="text-4xl"
              >
                {emoji}
              </motion.div>
            ))}
          </div>

          {/* Кнопка назад */}
          <motion.button
            whileTap={{ scale: 0.95 }}
            onClick={() => navigate(-1)}
            className="px-8 py-4 bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl text-white font-bold text-lg backdrop-blur-md transition-colors"
          >
            Вернуться назад
          </motion.button>
        </motion.div>
      </div>
    </div>
  )
}

export default FortuneWheelPage
