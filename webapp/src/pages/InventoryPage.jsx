import { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import { api } from '../api/client'
import { useTelegram } from '../hooks/useTelegram'
import CoinIcon from '../components/CoinIcon'
import TicketIcon from '../components/TicketIcon'
import AvatarWithFrame from '../components/AvatarWithFrame'

const formatCosmeticTitle = (cosmetic) => {
  if (cosmetic?.name) return cosmetic.name
  const raw = String(cosmetic?.cosmetic_id || cosmetic?.key || '').trim()
  if (!raw) return 'Косметика'
  return raw
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

const InventoryPage = () => {
  const { webApp } = useTelegram()
  const [inventory, setInventory] = useState(null)
  const [activeTab, setActiveTab] = useState('items')
  const [loading, setLoading] = useState(true)

  // Показываем кнопку Назад
  useEffect(() => {
    if (webApp?.BackButton) {
      webApp.BackButton.show()
      webApp.BackButton.onClick(() => window.history.back())
      return () => webApp.BackButton.hide()
    }
  }, [webApp])

  const rarityColors = {
    common: 'border-gray-500/50 bg-gray-600/10',
    uncommon: 'border-green-500/50 bg-green-600/10',
    rare: 'border-blue-500/50 bg-blue-600/10',
    epic: 'border-purple-500/50 bg-purple-600/10',
    legendary: 'border-yellow-500/50 bg-yellow-600/10',
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
      webApp?.showAlert?.('Ошибка загрузки инвентаря')
    } finally {
      setLoading(false)
    }
  }

  const handleEquipCosmetic = async (cosmeticId) => {
    try {
      await api.equipCosmetic(cosmeticId)
      webApp?.showPopup?.({
        title: '✅ Экипировано!',
        message: 'Косметика успешно экипирована',
        buttons: [{ type: 'close' }]
      })
      await loadInventory()
    } catch (error) {
      console.error('Ошибка экипировки:', error)
      webApp?.showAlert?.(`Ошибка: ${error.message}`)
    }
  }

  const handleUnequipCosmetic = async (cosmeticType) => {
    try {
      await api.unequipCosmetic(cosmeticType)
      webApp?.showPopup?.({
        title: '✅ Снято!',
        message: 'Косметика снята',
        buttons: [{ type: 'close' }]
      })
      await loadInventory()
    } catch (error) {
      console.error('Ошибка снятия косметики:', error)
      webApp?.showAlert?.(`Ошибка: ${error.message}`)
    }
  }

  const getItemIcon = (type, key) => {
    const icons = {
      lootbox: { bronze: '📦', silver: '📦', gold: '🎁', legendary: '💎' },
      hint: '💡',
      life: '🎫',
      boost: '⚡',
    }
    return icons[type]?.[key] || icons[type] || '📦'
  }

  const getItemName = (type, key) => {
    const names = {
      lootbox: {
        bronze: 'Бронзовый лутбокс',
        silver: 'Серебряный лутбокс',
        gold: 'Золотой лутбокс',
        legendary: 'Легендарный лутбокс',
      },
    }
    return names[type]?.[key] || key
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
      <div className="sticky top-0 z-10 glass-effect border-b border-white/10 p-4">
        <div className="flex items-center gap-3 mb-4">
          <button
            onClick={() => window.history.back()}
            className="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"
          >
            <span className="text-xl">←</span>
          </button>
          <h1 className="text-2xl font-bold text-white">🎒 Инвентарь</h1>
        </div>
        
        {/* Resources Display */}
        {inventory?.resources && (
          <div className="grid grid-cols-4 gap-2 mb-4">
            <div className="bg-game-warning/20 rounded-xl p-3 text-center border border-game-warning/30">
              <div className="flex justify-center mb-1">
                <CoinIcon size={32} />
              </div>
              <div className="text-white font-bold">{inventory.resources.coins}</div>
            </div>
            <div className="bg-blue-500/20 rounded-xl p-3 text-center border border-blue-500/30">
              <div className="text-2xl mb-1">💎</div>
              <div className="text-white font-bold">{inventory.resources.gems}</div>
            </div>
            <div className="bg-purple-500/20 rounded-xl p-3 text-center border border-purple-500/30">
              <div className="text-2xl mb-1">💡</div>
              <div className="text-white font-bold">{inventory.resources.hints}</div>
            </div>
            <div className="bg-red-500/20 rounded-xl p-3 text-center border border-red-500/30">
              <div className="flex justify-center mb-1">
                <TicketIcon size={32} />
              </div>
              <div className="text-white font-bold">{inventory.resources.tickets ?? inventory.resources.lives}</div>
            </div>
          </div>
        )}

        {/* Tabs */}
        <div className="flex gap-2">
          <button
            onClick={() => setActiveTab('items')}
            className={`
              flex-1 py-3 rounded-xl font-medium transition-all
              ${activeTab === 'items'
                ? 'bg-game-primary text-white'
                : 'bg-white/5 text-white/60'
              }
            `}
          >
            📦 Предметы
          </button>
          <button
            onClick={() => setActiveTab('cosmetics')}
            className={`
              flex-1 py-3 rounded-xl font-medium transition-all
              ${activeTab === 'cosmetics'
                ? 'bg-game-primary text-white'
                : 'bg-white/5 text-white/60'
              }
            `}
          >
            ✨ Косметика
          </button>
        </div>
      </div>

      {/* Content */}
      <div className="p-4">
        {activeTab === 'items' ? (
          // Items Tab
          inventory?.items && inventory.items.length > 0 ? (
            <div className="space-y-3">
              {inventory.items.map((item) => (
                <motion.div
                  key={item.id}
                  className="bg-white/5 rounded-xl p-4 border border-white/10"
                  whileHover={{ scale: 1.02 }}
                >
                  <div className="flex items-center gap-4">
                    <div className="text-5xl">{getItemIcon(item.type, item.key)}</div>
                    <div className="flex-1">
                      <h3 className="text-white font-bold">
                        {getItemName(item.type, item.key)}
                      </h3>
                      <p className="text-white/60 text-sm">
                        {item.type === 'lootbox' ? 'Откройте на странице лутбоксов' : 'Предмет'}
                      </p>
                    </div>
                    <div className="text-right">
                      <div className="text-2xl font-bold text-white">×{item.quantity}</div>
                      {item.expires_at && !item.is_expired && (
                        <div className="text-xs text-yellow-500">⏰ Истекает</div>
                      )}
                      {item.is_expired && (
                        <div className="text-xs text-red-500">❌ Истекло</div>
                      )}
                    </div>
                  </div>
                </motion.div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <div className="text-6xl mb-4">📦</div>
              <p className="text-white/40">Инвентарь пуст</p>
              <p className="text-white/30 text-sm mt-2">
                Покупайте товары в магазине
              </p>
            </div>
          )
        ) : (
          // Cosmetics Tab
          inventory?.cosmetics && inventory.cosmetics.length > 0 ? (
            <div className="grid grid-cols-2 gap-4">
              {inventory.cosmetics.map((cosmetic) => (
                <motion.div
                  key={cosmetic.id}
                  className={`
                    rounded-xl p-4 border-2 transition-all
                    ${rarityColors[cosmetic.rarity] || rarityColors.common}
                    ${cosmetic.is_equipped ? 'ring-2 ring-game-primary' : ''}
                  `}
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  {/* Frame preview */}
                  {cosmetic.type === 'frame' ? (
                    <div className="flex justify-center mb-3">
                      <AvatarWithFrame
                        photoUrl={null}
                        name="?"
                        frameKey={cosmetic.cosmetic_id || cosmetic.key}
                        size={80}
                        animated={cosmetic.metadata?.animated || false}
                        showGlow={false}
                      />
                    </div>
                  ) : (
                    <div className="text-center mb-3">
                      <div className="text-5xl mb-2">😎</div>
                    </div>
                  )}
                  
                  <div className="text-center mb-3">
                    <h3 className="text-white font-bold text-sm mb-1">
                      {formatCosmeticTitle(cosmetic)}
                    </h3>
                    <div className="text-xs font-bold text-white/60 uppercase">
                      {cosmetic.rarity}
                    </div>
                  </div>
                  
                  {cosmetic.is_equipped ? (
                    <>
                      <div className="text-xs text-center text-green-400 font-bold mb-2">
                        ✓ Экипировано
                      </div>
                      <button
                        onClick={() => handleUnequipCosmetic(cosmetic.type)}
                        className="w-full py-2 bg-white/10 hover:bg-white/20 rounded-lg text-white text-sm font-medium transition-colors"
                      >
                        Снять
                      </button>
                    </>
                  ) : (
                    <button
                      onClick={() => handleEquipCosmetic(cosmetic.id)}
                      className="w-full py-2 bg-game-primary hover:bg-game-primary-dark rounded-lg text-white text-sm font-medium transition-colors"
                    >
                      Экипировать
                    </button>
                  )}
                </motion.div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <div className="text-6xl mb-4">✨</div>
              <p className="text-white/40">Нет косметики</p>
              <p className="text-white/30 text-sm mt-2">
                Открывайте лутбоксы или покупайте в магазине
              </p>
            </div>
          )
        )}
      </div>
    </div>
  )
}

export default InventoryPage
