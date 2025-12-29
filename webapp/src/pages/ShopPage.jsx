import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../api/client'
import { useTelegram } from '../hooks/useTelegram'

const ShopPage = () => {
  const { webApp } = useTelegram()
  const [items, setItems] = useState([])
  const [profile, setProfile] = useState(null)
  const [selectedCategory, setSelectedCategory] = useState('all')
  const [selectedItem, setSelectedItem] = useState(null)
  const [quantity, setQuantity] = useState(1)
  const [loading, setLoading] = useState(true)
  const [purchasing, setPurchasing] = useState(false)

  // Показываем кнопку Назад
  useEffect(() => {
    if (webApp?.BackButton) {
      webApp.BackButton.show()
      webApp.BackButton.onClick(() => window.history.back())
      return () => webApp.BackButton.hide()
    }
  }, [webApp])

  const categories = [
    { id: 'all', name: 'Всё', icon: '🏪' },
    { id: 'hint', name: 'Подсказки', icon: '💡' },
    { id: 'boost', name: 'Бусты', icon: '⚡' },
    { id: 'lootbox', name: 'Лутбоксы', icon: '🎁' },
    { id: 'cosmetic', name: 'Косметика', icon: '✨' },
  ]

  const rarityColors = {
    common: 'from-gray-600 to-gray-700',
    uncommon: 'from-green-600 to-green-700',
    rare: 'from-blue-600 to-blue-700',
    epic: 'from-purple-600 to-purple-700',
    legendary: 'from-yellow-600 to-orange-600',
  }

  useEffect(() => {
    loadData()
  }, [selectedCategory])

  const loadData = async () => {
    setLoading(true)
    try {
      const [itemsData, profileData] = await Promise.all([
        api.getShopItems(selectedCategory === 'all' ? null : selectedCategory),
        api.getProfile(),
      ])
      setItems(itemsData.data.items)
      setProfile(profileData.data)
    } catch (error) {
      console.error('Ошибка загрузки:', error)
      webApp?.showAlert?.('Ошибка загрузки данных')
    } finally {
      setLoading(false)
    }
  }

  const handlePurchase = async () => {
    if (!selectedItem || purchasing) return

    const totalCoins = selectedItem.price_coins * quantity
    const totalGems = selectedItem.price_gems * quantity

    if (totalCoins > profile.coins || totalGems > profile.gems) {
      webApp?.showAlert?.('Недостаточно средств!')
      return
    }

    setPurchasing(true)
    try {
      await api.purchaseItem(selectedItem.id, quantity)
      webApp?.showPopup?.({
        title: '✅ Успешно!',
        message: `Куплено: ${selectedItem.name} x${quantity}`,
        buttons: [{ type: 'close' }]
      })
      setSelectedItem(null)
      setQuantity(1)
      await loadData()
    } catch (error) {
      console.error('Ошибка покупки:', error)
      webApp?.showAlert?.(`Ошибка: ${error.message}`)
    } finally {
      setPurchasing(false)
    }
  }

  const filteredItems = selectedCategory === 'all' 
    ? items 
    : items.filter(item => item.type === selectedCategory)

  return (
    <div className="min-h-screen bg-gradient-to-b from-dark-950 to-dark-900 pb-24">
      {/* Header */}
      <div className="sticky top-0 z-10 glass-effect border-b border-white/10 p-4">
        <div className="flex items-center gap-3 mb-4">
          <button
            onClick={() => window.history.back()}
            className="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"
          >
            <span className="text-xl">←</span>
          </button>
          <h1 className="text-2xl font-bold text-white">🏪 Магазин</h1>
        </div>
        
        {/* Currency Display */}
        {profile && (
          <div className="flex gap-3 mb-4">
            <div className="flex items-center gap-2 px-4 py-2 bg-game-warning/20 rounded-xl border border-game-warning/30">
              <span className="text-2xl">🪙</span>
              <span className="font-bold text-white">{profile.coins}</span>
            </div>
            <div className="flex items-center gap-2 px-4 py-2 bg-blue-500/20 rounded-xl border border-blue-500/30">
              <span className="text-2xl">💎</span>
              <span className="font-bold text-white">{profile.gems}</span>
            </div>
          </div>
        )}

        {/* Category Tabs */}
        <div className="flex gap-2 overflow-x-auto pb-2">
          {categories.map((cat) => (
            <button
              key={cat.id}
              onClick={() => setSelectedCategory(cat.id)}
              className={`
                flex items-center gap-2 px-4 py-2 rounded-xl font-medium whitespace-nowrap transition-all
                ${selectedCategory === cat.id
                  ? 'bg-game-primary text-white scale-105'
                  : 'bg-white/5 text-white/60 hover:bg-white/10'
                }
              `}
            >
              <span>{cat.icon}</span>
              <span>{cat.name}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Items Grid */}
      <div className="p-4">
        {loading ? (
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-4 border-game-primary border-t-transparent" />
          </div>
        ) : filteredItems.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-white/40 text-lg">Товары не найдены</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-4">
            {filteredItems.map((item) => (
              <motion.button
                key={item.id}
                onClick={() => setSelectedItem(item)}
                className="text-left"
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
              >
                <div className={`
                  relative overflow-hidden rounded-2xl p-4 h-full
                  bg-gradient-to-br ${rarityColors[item.rarity] || rarityColors.common}
                  border border-white/20
                `}>
                  {/* Icon */}
                  <div className="text-5xl mb-2">{item.icon}</div>
                  
                  {/* Name */}
                  <h3 className="text-white font-bold text-sm mb-1 line-clamp-2">
                    {item.name}
                  </h3>
                  
                  {/* Description */}
                  <p className="text-white/70 text-xs mb-3 line-clamp-2">
                    {item.description}
                  </p>
                  
                  {/* Price */}
                  <div className="flex items-center gap-2 bg-black/30 rounded-lg px-2 py-1">
                    {item.price_coins > 0 && (
                      <span className="text-white font-bold text-sm">
                        🪙 {item.price_coins}
                      </span>
                    )}
                    {item.price_gems > 0 && (
                      <span className="text-white font-bold text-sm">
                        💎 {item.price_gems}
                      </span>
                    )}
                  </div>
                </div>
              </motion.button>
            ))}
          </div>
        )}
      </div>

      {/* Purchase Modal */}
      <AnimatePresence>
        {selectedItem && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
            onClick={() => setSelectedItem(null)}
          >
            <motion.div
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.9, opacity: 0 }}
              className={`
                relative w-full max-w-md p-6 rounded-3xl
                bg-gradient-to-br ${rarityColors[selectedItem.rarity]}
                border border-white/30
              `}
              onClick={(e) => e.stopPropagation()}
            >
              <button
                onClick={() => setSelectedItem(null)}
                className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center bg-black/30 rounded-full text-white"
              >
                ✕
              </button>

              <div className="text-center mb-6">
                <div className="text-7xl mb-4">{selectedItem.icon}</div>
                <h2 className="text-2xl font-bold text-white mb-2">
                  {selectedItem.name}
                </h2>
                <p className="text-white/80">
                  {selectedItem.description}
                </p>
              </div>

              {/* Quantity Selector */}
              <div className="mb-6">
                <label className="block text-white/80 mb-2 text-sm">Количество:</label>
                <div className="flex items-center gap-4 justify-center">
                  <button
                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                    className="w-10 h-10 rounded-full bg-black/30 text-white font-bold text-xl"
                  >
                    −
                  </button>
                  <span className="text-3xl font-bold text-white w-12 text-center">
                    {quantity}
                  </span>
                  <button
                    onClick={() => setQuantity(Math.min(99, quantity + 1))}
                    className="w-10 h-10 rounded-full bg-black/30 text-white font-bold text-xl"
                  >
                    +
                  </button>
                </div>
              </div>

              {/* Total Price */}
              <div className="mb-6 p-4 bg-black/30 rounded-xl">
                <div className="text-white/70 text-sm mb-1">Итого:</div>
                <div className="flex items-center gap-4 justify-center text-2xl font-bold text-white">
                  {selectedItem.price_coins > 0 && (
                    <span>🪙 {selectedItem.price_coins * quantity}</span>
                  )}
                  {selectedItem.price_gems > 0 && (
                    <span>💎 {selectedItem.price_gems * quantity}</span>
                  )}
                </div>
              </div>

              {/* Purchase Button */}
              <button
                onClick={handlePurchase}
                disabled={purchasing}
                className={`
                  w-full py-4 rounded-xl font-bold text-lg text-white transition-all
                  ${purchasing
                    ? 'bg-white/20 cursor-not-allowed'
                    : 'bg-white/30 hover:bg-white/40 active:scale-95'
                  }
                `}
              >
                {purchasing ? 'Покупка...' : 'Купить'}
              </button>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default ShopPage

