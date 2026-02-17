import { useEffect, useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'
import TicketIcon from '../components/TicketIcon'
import AvatarWithFrame from '../components/AvatarWithFrame'

const SHOP_SECTIONS = [
  { key: 'hint', title: 'Подсказки', icon: '💡', description: 'Поддержка в сложных вопросах' },
  { key: 'life', title: 'Билеты', icon: '🎫', description: 'Ресурс для входа в дуэли' },
  { key: 'boost', title: 'Бусты', icon: '⚡', description: 'Ускоряют прогресс и доход' },
  { key: 'lootbox', title: 'Лутбоксы', icon: '🎁', description: 'Случайные награды и карточки' },
  { key: 'cosmetic', title: 'Косметика', icon: '✨', description: 'Оформление профиля' },
]

const RARITY_STYLES = {
  common: 'border-white/12 bg-white/5 text-white/80',
  uncommon: 'border-emerald-300/25 bg-emerald-500/10 text-emerald-100',
  rare: 'border-cyan-300/30 bg-cyan-500/10 text-cyan-100',
  epic: 'border-violet-300/30 bg-violet-500/10 text-violet-100',
  legendary: 'border-amber-300/35 bg-amber-500/10 text-amber-100',
}

function rarityLabel(rarity) {
  switch ((rarity || '').toLowerCase()) {
    case 'legendary':
      return 'Легендарный'
    case 'epic':
      return 'Эпический'
    case 'rare':
      return 'Редкий'
    case 'uncommon':
      return 'Необычный'
    default:
      return 'Обычный'
  }
}

function getFrameCosmeticId(item) {
  if (!item || item.type !== 'cosmetic') return null
  const metadata = item.metadata || {}
  const type = String(metadata.cosmetic_type || '').toLowerCase()
  const cosmeticId = String(metadata.cosmetic_id || '').trim()
  if (type !== 'frame' || !cosmeticId) return null
  return cosmeticId
}

function formatCosmeticName(value) {
  const source = String(value || '').trim()
  if (!source) return 'Рамка профиля'
  return source
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

const ShopPage = () => {
  const { webApp } = useTelegram()
  const [loading, setLoading] = useState(true)
  const [busyItemId, setBusyItemId] = useState(null)
  const [selectedCategory, setSelectedCategory] = useState(null)
  const [items, setItems] = useState([])
  const [history, setHistory] = useState([])
  const [error, setError] = useState(null)
  const [balance, setBalance] = useState({ coins: 0, gems: 0, hints: 0, tickets: 0 })
  const [quantities, setQuantities] = useState({})
  const [purchaseModalItem, setPurchaseModalItem] = useState(null)

  useEffect(() => {
    if (webApp?.BackButton) {
      const onBack = () => {
        if (selectedCategory) {
          setSelectedCategory(null)
          return
        }
        window.history.back()
      }
      webApp.BackButton.show()
      webApp.BackButton.onClick(onBack)
      return () => {
        webApp.BackButton.offClick(onBack)
        webApp.BackButton.hide()
      }
    }
  }, [webApp, selectedCategory])

  useEffect(() => {
    loadShop()
  }, [selectedCategory])

  const loadShop = async () => {
    setLoading(true)
    setError(null)
    try {
      const [itemsResponse, historyResponse] = await Promise.all([
        api.getShopItems(selectedCategory),
        api.getShopHistory(),
      ])

      const serverItems = itemsResponse?.data?.items || []
      const serverBalance = itemsResponse?.data?.balance
      setItems(serverItems)
      setHistory(historyResponse?.data?.history || [])

      if (serverBalance) {
        setBalance({
          coins: Number(serverBalance.coins || 0),
          gems: Number(serverBalance.gems || 0),
          hints: Number(serverBalance.hints || 0),
          tickets: Number(serverBalance.tickets || serverBalance.lives || 0),
        })
      }

      setQuantities((prev) => {
        const next = { ...prev }
        for (const item of serverItems) {
          const max = Number(item.max_per_purchase || 1)
          if (!next[item.id]) next[item.id] = 1
          if (next[item.id] > max) next[item.id] = Math.max(1, max)
        }
        return next
      })
    } catch (err) {
      console.error('Ошибка загрузки магазина:', err)
      setError(err.message || 'Ошибка загрузки магазина')
    } finally {
      setLoading(false)
    }
  }

  const setItemQuantity = (item, value) => {
    const maxAllowed = Math.max(1, Number(item.max_per_purchase || 1))
    const normalized = Math.max(1, Math.min(maxAllowed, value))
    setQuantities((prev) => ({ ...prev, [item.id]: normalized }))
  }

  const canBuy = (item) => {
    const quantity = Math.max(1, Number(quantities[item.id] || 1))
    const coinsNeed = Number(item.price_coins || 0) * quantity
    const gemsNeed = Number(item.price_gems || 0) * quantity
    const enoughBalance = balance.coins >= coinsNeed && balance.gems >= gemsNeed
    const remaining = item.remaining_today
    const hasLimitRoom = remaining === null || Number(remaining) > 0
    return enoughBalance && hasLimitRoom && !item.is_owned
  }

  const handleBuy = async (item) => {
    const quantity = Math.max(1, Number(quantities[item.id] || 1))
    if (busyItemId !== null) return
    setBusyItemId(item.id)
    setError(null)

    try {
      const response = await api.purchaseItem(item.id, quantity)
      if (response.success) {
        hapticFeedback('success')
        webApp?.showAlert?.(`Покупка успешна: ${item.name}`)
        setPurchaseModalItem(null)
        await loadShop()
      }
    } catch (err) {
      hapticFeedback('error')
      setError(err.message || 'Ошибка покупки')
    } finally {
      setBusyItemId(null)
    }
  }

  const historyPreview = useMemo(() => history.slice(0, 8), [history])
  const selectedSectionMeta = SHOP_SECTIONS.find((section) => section.key === selectedCategory)
  const selectedPurchaseQuantity = purchaseModalItem
    ? Math.max(1, Number(quantities[purchaseModalItem.id] || 1))
    : 1
  const selectedPurchaseFrameId = purchaseModalItem ? getFrameCosmeticId(purchaseModalItem) : null
  const selectedPurchaseTotalCoins = purchaseModalItem
    ? Number(purchaseModalItem.price_coins || 0) * selectedPurchaseQuantity
    : 0
  const selectedPurchaseTotalGems = purchaseModalItem
    ? Number(purchaseModalItem.price_gems || 0) * selectedPurchaseQuantity
    : 0
  const canConfirmPurchase = purchaseModalItem ? canBuy(purchaseModalItem) : false
  const purchaseDisabledReason = useMemo(() => {
    if (!purchaseModalItem) return ''
    if (purchaseModalItem.is_owned) return 'Этот товар уже у вас есть'
    if (purchaseModalItem.remaining_today !== null && Number(purchaseModalItem.remaining_today) <= 0) {
      return 'Дневной лимит исчерпан'
    }
    if (!canConfirmPurchase) return 'Недостаточно ресурсов для покупки'
    return ''
  }, [purchaseModalItem, canConfirmPurchase])

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/45 text-sm">Загрузка магазина...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-3" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-4 pt-4 space-y-4">
        <section className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <button
              onClick={() => (selectedCategory ? setSelectedCategory(null) : window.history.back())}
              className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <h1 className="text-white font-bold text-lg">{selectedSectionMeta ? selectedSectionMeta.title : 'Магазин'}</h1>
            <div className="w-9" />
          </div>

          <div className="grid grid-cols-2 gap-2">
            <div className="rounded-2xl border border-amber-300/30 bg-amber-500/10 px-3 py-2">
              <p className="text-[10px] text-white/55 uppercase">Монеты</p>
              <p className="text-white font-bold text-sm mt-1 flex items-center gap-1.5">
                <CoinIcon className="w-4 h-4" />
                {balance.coins}
              </p>
            </div>
            <div className="rounded-2xl border border-cyan-300/30 bg-cyan-500/10 px-3 py-2">
              <p className="text-[10px] text-white/55 uppercase">Кристаллы</p>
              <p className="text-white font-bold text-sm mt-1">💎 {balance.gems}</p>
            </div>
          </div>

          <div className="mt-2 grid grid-cols-2 gap-2">
            <div className="rounded-xl border border-white/10 bg-white/5 px-3 py-2">
              <p className="text-[10px] text-white/45 uppercase">Подсказки</p>
              <p className="text-white font-semibold text-sm mt-1">💡 {balance.hints}</p>
            </div>
            <div className="rounded-xl border border-white/10 bg-white/5 px-3 py-2">
              <p className="text-[10px] text-white/45 uppercase">Билеты</p>
              <p className="text-white font-semibold text-sm mt-1 flex items-center gap-1.5">
                <TicketIcon className="w-4 h-4" />
                {balance.tickets}
              </p>
            </div>
          </div>
        </section>

        {error && (
          <section className="rounded-2xl border border-red-400/35 bg-red-500/12 px-4 py-3 text-red-200 text-sm">
            {error}
          </section>
        )}

        {!selectedCategory ? (
          <section className="grid grid-cols-1 gap-3">
            {SHOP_SECTIONS.map((section) => (
              <motion.button
                key={section.key}
                whileTap={{ scale: 0.98 }}
                onClick={() => setSelectedCategory(section.key)}
                className="rounded-2xl border border-white/10 bg-black/20 backdrop-blur-xl p-4 text-left"
              >
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-white font-semibold text-sm mb-1">{section.icon} {section.title}</p>
                    <p className="text-white/60 text-xs">{section.description}</p>
                  </div>
                  <span className="text-white/45 text-sm">Открыть</span>
                </div>
              </motion.button>
            ))}
          </section>
        ) : (
          <section className="space-y-3">
            {items.length === 0 && (
              <div className="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-white/50">
                В этом разделе пока нет товаров
              </div>
            )}

            {items.map((item) => {
              const quantity = Math.max(1, Number(quantities[item.id] || 1))
              const maxQty = Math.max(1, Number(item.max_per_purchase || 1))
              const canBuyItem = canBuy(item)
              const disabled = busyItemId === item.id
              const rarityStyle = RARITY_STYLES[item.rarity] || RARITY_STYLES.common
              const remainingToday = item.remaining_today
              const frameCosmeticId = getFrameCosmeticId(item)

              return (
                <motion.div
                  key={item.id}
                  initial={{ opacity: 0, y: 6 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <div className="w-10 h-10 rounded-xl border border-white/10 bg-white/5 flex items-center justify-center text-lg">
                          {frameCosmeticId ? (
                            <AvatarWithFrame
                              size={34}
                              frameKey={frameCosmeticId}
                              name="?"
                              photoUrl={null}
                              showGlow={false}
                            />
                          ) : (
                            item.icon || '🛍️'
                          )}
                        </div>
                        <div>
                          <p className="text-white font-semibold text-sm">
                            {frameCosmeticId ? formatCosmeticName(item.name || frameCosmeticId) : item.name}
                          </p>
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-[10px] border ${rarityStyle}`}>
                            {rarityLabel(item.rarity)}
                          </span>
                        </div>
                      </div>
                      {item.description ? <p className="text-xs text-white/65 mt-2 leading-relaxed">{item.description}</p> : null}
                    </div>
                    {item.is_owned ? (
                      <span className="text-[10px] px-2 py-1 rounded-full border border-emerald-300/35 bg-emerald-500/12 text-emerald-200">
                        Уже куплено
                      </span>
                    ) : null}
                  </div>

                  <div className="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-white/60">
                    {Number(item.unit_quantity || 1) > 1 ? (
                      <span className="px-2 py-1 rounded-lg border border-white/12 bg-white/5">
                        В наборе: {item.unit_quantity}
                      </span>
                    ) : null}
                    {item.daily_limit !== null ? (
                      <span className="px-2 py-1 rounded-lg border border-white/12 bg-white/5">
                        Лимит/день: {item.daily_limit}
                      </span>
                    ) : null}
                    {remainingToday !== null ? (
                      <span className="px-2 py-1 rounded-lg border border-white/12 bg-white/5">
                        Осталось: {remainingToday}
                      </span>
                    ) : null}
                  </div>

                  <div className="mt-4 flex items-center justify-between gap-3">
                    <div className="inline-flex items-center rounded-xl border border-white/10 bg-white/5">
                      <button
                        onClick={() => setItemQuantity(item, quantity - 1)}
                        disabled={quantity <= 1}
                        className="w-8 h-8 text-white/80 disabled:text-white/25"
                      >
                        −
                      </button>
                      <span className="w-8 text-center text-sm text-white font-semibold">{quantity}</span>
                      <button
                        onClick={() => setItemQuantity(item, quantity + 1)}
                        disabled={quantity >= maxQty}
                        className="w-8 h-8 text-white/80 disabled:text-white/25"
                      >
                        +
                      </button>
                    </div>

                    <button
                      onClick={() => setPurchaseModalItem(item)}
                      disabled={disabled}
                      className={`px-4 py-2 rounded-xl text-sm font-semibold border ${
                        !canBuyItem || disabled
                          ? 'border-white/10 bg-white/5 text-white/35'
                          : 'border-cyan-300/35 bg-cyan-500/15 text-white active:scale-[0.98]'
                      }`}
                    >
                      {busyItemId === item.id ? (
                        'Покупка...'
                      ) : (
                        <span className="inline-flex items-center gap-1.5">
                          Купить •
                          {Number(item.price_coins || 0) > 0 ? (
                            <>
                              <CoinIcon className="w-4 h-4" />
                              <span>{Number(item.price_coins || 0) * quantity}</span>
                            </>
                          ) : null}
                          {Number(item.price_coins || 0) > 0 && Number(item.price_gems || 0) > 0 ? <span>+</span> : null}
                          {Number(item.price_gems || 0) > 0 ? <span>{Number(item.price_gems || 0) * quantity} 💎</span> : null}
                        </span>
                      )}
                    </button>
                  </div>
                </motion.div>
              )
            })}
          </section>
        )}

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
          <h2 className="text-white font-semibold text-sm mb-3">Последние покупки</h2>
          {historyPreview.length === 0 ? (
            <p className="text-white/45 text-sm">Пока нет покупок</p>
          ) : (
            <div className="space-y-2">
              {historyPreview.map((entry, index) => (
                <div key={`${entry.item_id || entry.item_name}-${entry.purchased_at}-${index}`} className="rounded-xl border border-white/10 bg-white/5 p-3">
                  <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                      <p className="text-sm text-white font-medium truncate">
                        {entry.item_icon || '🛍️'} {entry.item_name}
                        {Number(entry.quantity) > 1 ? ` x${entry.quantity}` : ''}
                      </p>
                      <p className="text-[11px] text-white/45 mt-1">{entry.purchased_at}</p>
                    </div>
                    <div className="text-xs text-white/70 whitespace-nowrap">
                      {Number(entry.price_coins || 0) > 0 ? `${entry.price_coins} 🪙` : ''}
                      {Number(entry.price_coins || 0) > 0 && Number(entry.price_gems || 0) > 0 ? ' + ' : ''}
                      {Number(entry.price_gems || 0) > 0 ? `${entry.price_gems} 💎` : ''}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      <AnimatePresence>
        {purchaseModalItem && (
          <motion.div
            className="fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-black/70 backdrop-blur-sm p-4"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={() => {
              if (busyItemId === null) setPurchaseModalItem(null)
            }}
          >
            <motion.div
              className="w-full max-w-sm rounded-3xl border border-white/15 bg-[#0a0f23]/95 p-4"
              initial={{ opacity: 0, y: 22, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: 10, scale: 0.99 }}
              transition={{ duration: 0.2 }}
              onClick={(event) => event.stopPropagation()}
            >
              <div className="flex items-start gap-3">
                <div className="w-14 h-14 rounded-2xl border border-white/10 bg-white/5 flex items-center justify-center text-2xl">
                  {selectedPurchaseFrameId ? (
                    <AvatarWithFrame
                      size={48}
                      frameKey={selectedPurchaseFrameId}
                      name="?"
                      photoUrl={null}
                      showGlow={false}
                    />
                  ) : (
                    purchaseModalItem.icon || '🛍️'
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-white font-semibold text-base leading-tight">
                    {selectedPurchaseFrameId
                      ? formatCosmeticName(purchaseModalItem.name || selectedPurchaseFrameId)
                      : purchaseModalItem.name}
                  </p>
                  <p className="text-white/65 text-xs mt-1">{purchaseModalItem.description || 'Подтвердите покупку товара'}</p>
                  <span className={`inline-flex mt-2 px-2 py-0.5 rounded-full text-[10px] border ${RARITY_STYLES[purchaseModalItem.rarity] || RARITY_STYLES.common}`}>
                    {rarityLabel(purchaseModalItem.rarity)}
                  </span>
                </div>
              </div>

              <div className="mt-4 flex items-center justify-between">
                <p className="text-white/70 text-sm">Количество</p>
                <div className="inline-flex items-center rounded-xl border border-white/10 bg-white/5">
                  <button
                    onClick={() => setItemQuantity(purchaseModalItem, selectedPurchaseQuantity - 1)}
                    disabled={selectedPurchaseQuantity <= 1 || busyItemId !== null}
                    className="w-9 h-9 text-white/80 disabled:text-white/25"
                  >
                    −
                  </button>
                  <span className="w-10 text-center text-sm text-white font-semibold">{selectedPurchaseQuantity}</span>
                  <button
                    onClick={() => setItemQuantity(purchaseModalItem, selectedPurchaseQuantity + 1)}
                    disabled={selectedPurchaseQuantity >= Math.max(1, Number(purchaseModalItem.max_per_purchase || 1)) || busyItemId !== null}
                    className="w-9 h-9 text-white/80 disabled:text-white/25"
                  >
                    +
                  </button>
                </div>
              </div>

              <div className="mt-3 rounded-2xl border border-white/10 bg-white/5 p-3">
                <p className="text-[11px] text-white/55 uppercase">Итого к оплате</p>
                <div className="mt-2 flex items-center justify-between text-sm">
                  <div className="text-white/80">Цена</div>
                  <div className="text-white font-semibold inline-flex items-center gap-2">
                    {selectedPurchaseTotalCoins > 0 ? (
                      <span className="inline-flex items-center gap-1">
                        <CoinIcon className="w-4 h-4" />
                        {selectedPurchaseTotalCoins}
                      </span>
                    ) : null}
                    {selectedPurchaseTotalCoins > 0 && selectedPurchaseTotalGems > 0 ? <span>+</span> : null}
                    {selectedPurchaseTotalGems > 0 ? <span>{selectedPurchaseTotalGems} 💎</span> : null}
                    {selectedPurchaseTotalCoins <= 0 && selectedPurchaseTotalGems <= 0 ? <span>Бесплатно</span> : null}
                  </div>
                </div>
              </div>

              {purchaseDisabledReason ? (
                <p className="mt-3 text-xs text-red-200">{purchaseDisabledReason}</p>
              ) : null}

              <div className="mt-4 grid grid-cols-2 gap-2">
                <button
                  onClick={() => setPurchaseModalItem(null)}
                  disabled={busyItemId !== null}
                  className="h-11 rounded-xl border border-white/15 bg-white/5 text-white/80 text-sm font-semibold disabled:opacity-50"
                >
                  Отмена
                </button>
                <button
                  onClick={() => handleBuy(purchaseModalItem)}
                  disabled={!canConfirmPurchase || busyItemId === purchaseModalItem.id}
                  className={`h-11 rounded-xl border text-sm font-semibold ${
                    !canConfirmPurchase || busyItemId === purchaseModalItem.id
                      ? 'border-white/15 bg-white/5 text-white/35'
                      : 'border-cyan-300/40 bg-cyan-500/15 text-white'
                  }`}
                >
                  {busyItemId === purchaseModalItem.id ? 'Покупка...' : 'Подтвердить'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default ShopPage
