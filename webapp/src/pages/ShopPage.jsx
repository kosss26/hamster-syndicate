import { useEffect, useMemo, useState } from 'react'
import { motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'

const CATEGORY_TABS = [
  { key: 'all', label: 'Все', icon: '🛍️' },
  { key: 'hint', label: 'Подсказки', icon: '💡' },
  { key: 'life', label: 'Билеты', icon: '🎫' },
  { key: 'boost', label: 'Бусты', icon: '⚡' },
  { key: 'lootbox', label: 'Лутбоксы', icon: '🎁' },
  { key: 'cosmetic', label: 'Косметика', icon: '✨' },
]

const RARITY_STYLES = {
  common: 'border-white/12 bg-white/5 text-white/80',
  uncommon: 'border-emerald-300/25 bg-emerald-500/10 text-emerald-100',
  rare: 'border-cyan-300/30 bg-cyan-500/10 text-cyan-100',
  epic: 'border-violet-300/30 bg-violet-500/10 text-violet-100',
  legendary: 'border-amber-300/35 bg-amber-500/10 text-amber-100',
}

const RECOMMEND_TAG_STYLES = {
  critical: 'border-red-300/40 bg-red-500/15 text-red-100',
  hot: 'border-orange-300/40 bg-orange-500/15 text-orange-100',
  recommended: 'border-cyan-300/40 bg-cyan-500/15 text-cyan-100',
  daily: 'border-emerald-300/40 bg-emerald-500/15 text-emerald-100',
  style: 'border-fuchsia-300/40 bg-fuchsia-500/15 text-fuchsia-100',
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

function recommendationTagLabel(tag) {
  switch (tag) {
    case 'critical':
      return 'Срочно'
    case 'hot':
      return 'Горячее'
    case 'recommended':
      return 'Советуем'
    case 'daily':
      return 'Сегодня'
    case 'style':
      return 'Стиль'
    default:
      return null
  }
}

function priceLabel(item, quantity) {
  const q = Math.max(1, Number(quantity || 1))
  const coins = Number(item.price_coins || 0) * q
  const gems = Number(item.price_gems || 0) * q

  if (coins > 0 && gems > 0) {
    return `${coins} 🪙 + ${gems} 💎`
  }
  if (coins > 0) {
    return `${coins} 🪙`
  }
  return `${gems} 💎`
}

const ShopPage = () => {
  const { webApp } = useTelegram()
  const [loading, setLoading] = useState(true)
  const [busyItemId, setBusyItemId] = useState(null)
  const [activeCategory, setActiveCategory] = useState('all')
  const [items, setItems] = useState([])
  const [history, setHistory] = useState([])
  const [error, setError] = useState(null)
  const [balance, setBalance] = useState({ coins: 0, gems: 0, hints: 0, tickets: 0 })
  const [quantities, setQuantities] = useState({})

  useEffect(() => {
    if (webApp?.BackButton) {
      const onBack = () => window.history.back()
      webApp.BackButton.show()
      webApp.BackButton.onClick(onBack)
      return () => {
        webApp.BackButton.offClick(onBack)
        webApp.BackButton.hide()
      }
    }
  }, [webApp])

  useEffect(() => {
    loadShop()
  }, [activeCategory])

  const loadShop = async () => {
    setLoading(true)
    setError(null)
    try {
      const [itemsResponse, historyResponse] = await Promise.all([
        api.getShopItems(activeCategory === 'all' ? null : activeCategory),
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
          if (!next[item.id]) {
            next[item.id] = 1
          } else if (next[item.id] > max) {
            next[item.id] = Math.max(1, max)
          }
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
        const granted = response?.data?.granted
        const grantedLabel = granted?.amount ? ` (+${granted.amount})` : ''
        webApp?.showAlert?.(`Покупка успешна: ${item.name}${grantedLabel}`)
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
  const featuredItems = useMemo(
    () => items.filter((item) => item.is_featured).sort((a, b) => (b.recommendation_score || 0) - (a.recommendation_score || 0)).slice(0, 3),
    [items]
  )

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
              onClick={() => window.history.back()}
              className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <h1 className="text-white font-bold text-lg">Магазин</h1>
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
              <p className="text-white font-semibold text-sm mt-1">🎫 {balance.tickets}</p>
            </div>
          </div>
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-3">
          <div className="flex gap-2 overflow-x-auto scrollbar-hide pb-1">
            {CATEGORY_TABS.map((tab) => (
              <button
                key={tab.key}
                onClick={() => setActiveCategory(tab.key)}
                className={`shrink-0 px-3 py-2 rounded-xl border text-xs font-semibold ${
                  activeCategory === tab.key
                    ? 'border-cyan-300/35 bg-cyan-500/15 text-white'
                    : 'border-white/10 bg-white/5 text-white/70'
                }`}
              >
                {tab.icon} {tab.label}
              </button>
            ))}
          </div>
        </section>

        {featuredItems.length > 0 && (
          <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
            <h2 className="text-white font-semibold text-sm mb-3">Персональные предложения</h2>
            <div className="space-y-2">
              {featuredItems.map((item) => {
                const quantity = Math.max(1, Number(quantities[item.id] || 1))
                const disabled = !canBuy(item) || busyItemId === item.id
                const tagLabel = recommendationTagLabel(item.recommendation_tag)
                const tagStyle = RECOMMEND_TAG_STYLES[item.recommendation_tag] || RECOMMEND_TAG_STYLES.recommended

                return (
                  <div key={`featured-${item.id}`} className="rounded-2xl border border-white/10 bg-white/5 p-3">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <p className="text-sm text-white font-semibold truncate">{item.icon || '🛍️'} {item.name}</p>
                        {item.recommendation_reason ? (
                          <p className="text-xs text-white/55 mt-1">{item.recommendation_reason}</p>
                        ) : null}
                      </div>
                      {tagLabel ? (
                        <span className={`text-[10px] px-2 py-1 rounded-full border ${tagStyle}`}>{tagLabel}</span>
                      ) : null}
                    </div>
                    <div className="mt-2 flex justify-end">
                      <button
                        onClick={() => handleBuy(item)}
                        disabled={disabled}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                          disabled
                            ? 'border-white/10 bg-white/5 text-white/35'
                            : 'border-cyan-300/35 bg-cyan-500/15 text-white'
                        }`}
                      >
                        {busyItemId === item.id ? 'Покупка...' : `Купить • ${priceLabel(item, quantity)}`}
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          </section>
        )}

        {error && (
          <section className="rounded-2xl border border-red-400/35 bg-red-500/12 px-4 py-3 text-red-200 text-sm">
            {error}
          </section>
        )}

        <section className="space-y-3">
          {items.length === 0 && (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-white/50">
              В этой категории пока нет товаров
            </div>
          )}

          {items.map((item) => {
            const quantity = Math.max(1, Number(quantities[item.id] || 1))
            const maxQty = Math.max(1, Number(item.max_per_purchase || 1))
            const disabled = !canBuy(item) || busyItemId === item.id
            const rarityStyle = RARITY_STYLES[item.rarity] || RARITY_STYLES.common
            const remainingToday = item.remaining_today

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
                        {item.icon || '🛍️'}
                      </div>
                      <div>
                        <p className="text-white font-semibold text-sm">{item.name}</p>
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-[10px] border ${rarityStyle}`}>
                          {rarityLabel(item.rarity)}
                        </span>
                        {item.recommendation_tag && recommendationTagLabel(item.recommendation_tag) ? (
                          <span className={`inline-flex ml-1 px-2 py-0.5 rounded-full text-[10px] border ${RECOMMEND_TAG_STYLES[item.recommendation_tag] || RECOMMEND_TAG_STYLES.recommended}`}>
                            {recommendationTagLabel(item.recommendation_tag)}
                          </span>
                        ) : null}
                      </div>
                    </div>
                    {item.description ? (
                      <p className="text-xs text-white/65 mt-2 leading-relaxed">{item.description}</p>
                    ) : null}
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
                    onClick={() => handleBuy(item)}
                    disabled={disabled}
                    className={`px-4 py-2 rounded-xl text-sm font-semibold border ${
                      disabled
                        ? 'border-white/10 bg-white/5 text-white/35'
                        : 'border-cyan-300/35 bg-cyan-500/15 text-white active:scale-[0.98]'
                    }`}
                  >
                    {busyItemId === item.id ? 'Покупка...' : `Купить • ${priceLabel(item, quantity)}`}
                  </button>
                </div>
              </motion.div>
            )
          })}
        </section>

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
    </div>
  )
}

export default ShopPage
