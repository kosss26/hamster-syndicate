import { useEffect } from 'react'
import { motion } from 'framer-motion'
import { useTelegram } from '../hooks/useTelegram'

const ShopPage = () => {
  const { webApp } = useTelegram()

  // Показываем кнопку Назад
  useEffect(() => {
    if (webApp?.BackButton) {
      webApp.BackButton.show()
      webApp.BackButton.onClick(() => window.history.back())
      return () => webApp.BackButton.hide()
    }
  }, [webApp])

  return (
    <div className="min-h-screen bg-gradient-to-b from-dark-950 to-dark-900 flex items-center justify-center p-6">
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        className="text-center max-w-md"
      >
        <div className="mb-6">
          <motion.div
            className="text-8xl mb-4"
            animate={{ rotate: [0, -10, 10, -10, 0] }}
            transition={{ duration: 2, repeat: Infinity, repeatDelay: 1 }}
          >
            🏪
          </motion.div>
          <h1 className="text-3xl font-bold text-white mb-3">
            Магазин
          </h1>
          <div className="inline-block px-4 py-2 bg-yellow-500/20 border border-yellow-500/50 rounded-xl mb-4">
            <span className="text-yellow-400 font-bold">🚧 В разработке</span>
          </div>
        </div>
        
        <p className="text-white/60 text-lg mb-6">
          Мы работаем над созданием удивительного магазина с крутыми товарами!
        </p>
        
        <div className="space-y-3 mb-8 text-left bg-white/5 rounded-2xl p-4 border border-white/10">
          <div className="flex items-start gap-3">
            <span className="text-2xl">💡</span>
            <div>
              <p className="text-white font-semibold text-sm">Подсказки</p>
              <p className="text-white/40 text-xs">Помощь в сложных вопросах</p>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <span className="text-2xl">⚡</span>
            <div>
              <p className="text-white font-semibold text-sm">Бусты опыта</p>
              <p className="text-white/40 text-xs">Ускорь свой прогресс</p>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <span className="text-2xl">🎁</span>
            <div>
              <p className="text-white font-semibold text-sm">Лутбоксы</p>
              <p className="text-white/40 text-xs">Случайные награды</p>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <span className="text-2xl">🖼️</span>
            <div>
              <p className="text-white font-semibold text-sm">Рамки профиля</p>
              <p className="text-white/40 text-xs">Кастомизируй аватар</p>
            </div>
          </div>
        </div>
        
        <button
          onClick={() => window.history.back()}
          className="w-full py-4 bg-gradient-to-r from-game-primary to-purple-600 rounded-2xl text-white font-bold text-lg hover:shadow-xl transition-all"
        >
          Вернуться назад
        </button>
        
        <p className="text-white/30 text-sm mt-4">
          Скоро здесь появится много интересного! 🎉
        </p>
      </motion.div>
    </div>
  )
}

export default ShopPage
