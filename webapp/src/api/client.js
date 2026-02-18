// Базовый URL API
// В продакшене используем относительный путь /api, который nginx проксирует на PHP
const API_BASE_URL = import.meta.env.VITE_API_URL || '/api'
const WS_BASE_URL = import.meta.env.VITE_WS_URL || ''
const INIT_DATA_CACHE_KEY = 'quizbot_tg_init_data'
const RESPONSE_CACHE_PREFIX = 'quizbot_api_response_v1:'

const responseMemoryCache = new Map()
const inflightCacheRequests = new Map()

// Получаем initData из Telegram для авторизации
function getInitData() {
  const initData = window.Telegram?.WebApp?.initData || ''
  if (initData) {
    try {
      window.sessionStorage?.setItem(INIT_DATA_CACHE_KEY, initData)
    } catch (_) {
      // noop
    }
    return initData
  }

  let cachedInitData = ''
  try {
    cachedInitData = window.sessionStorage?.getItem(INIT_DATA_CACHE_KEY) || ''
  } catch (_) {
    // noop
  }

  if (cachedInitData) {
    return cachedInitData
  }

  if (!initData) {
    console.warn('Telegram WebApp initData is missing; protected endpoints may return 401')
  }
  
  return initData
}

// Базовый метод для запросов
async function request(endpoint, options = {}) {
  const url = `${API_BASE_URL}${endpoint}`
  
  const headers = {
    'Content-Type': 'application/json',
    'X-Telegram-Init-Data': getInitData(),
    ...options.headers,
  }

  try {
    const response = await fetch(url, {
      ...options,
      headers,
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Ошибка сервера' }))
      const requestId = error.request_id ? ` (request_id: ${error.request_id})` : ''
      throw new Error((error.error || error.message || `HTTP ${response.status}`) + requestId)
    }

    return response.json()
  } catch (error) {
    console.error('API Error:', error)
    throw error
  }
}

function getResponseCacheStorageKey(cacheKey) {
  return `${RESPONSE_CACHE_PREFIX}${cacheKey}`
}

function readResponseCacheEntry(cacheKey) {
  if (!cacheKey) return null

  const memoryEntry = responseMemoryCache.get(cacheKey)
  if (memoryEntry && Number.isFinite(memoryEntry.ts)) {
    return memoryEntry
  }

  if (typeof window === 'undefined') return null

  try {
    const raw = window.sessionStorage?.getItem(getResponseCacheStorageKey(cacheKey))
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed || !Number.isFinite(parsed.ts)) return null
    responseMemoryCache.set(cacheKey, parsed)
    return parsed
  } catch (_) {
    return null
  }
}

function writeResponseCacheEntry(cacheKey, payload) {
  if (!cacheKey) return payload

  const entry = { ts: Date.now(), payload }
  responseMemoryCache.set(cacheKey, entry)

  if (typeof window !== 'undefined') {
    try {
      window.sessionStorage?.setItem(getResponseCacheStorageKey(cacheKey), JSON.stringify(entry))
    } catch (_) {
      // ignore quota / serialization errors
    }
  }

  return payload
}

function readCachedResponse(cacheKey, maxAgeMs = Number.POSITIVE_INFINITY) {
  const entry = readResponseCacheEntry(cacheKey)
  if (!entry) return null

  if (Number.isFinite(maxAgeMs) && maxAgeMs >= 0) {
    const ageMs = Date.now() - entry.ts
    if (ageMs > maxAgeMs) return null
  }

  return entry.payload
}

function peekCachedResponse(cacheKey) {
  return readCachedResponse(cacheKey, Number.POSITIVE_INFINITY)
}

function invalidateResponseCache(cacheKeyOrPrefix, { prefix = false } = {}) {
  if (!cacheKeyOrPrefix) return

  for (const key of responseMemoryCache.keys()) {
    if ((prefix && key.startsWith(cacheKeyOrPrefix)) || (!prefix && key === cacheKeyOrPrefix)) {
      responseMemoryCache.delete(key)
      inflightCacheRequests.delete(key)
    }
  }

  if (typeof window !== 'undefined') {
    try {
      const keysToDelete = []
      const storage = window.sessionStorage
      for (let i = 0; i < (storage?.length || 0); i += 1) {
        const storageKey = storage.key(i)
        if (!storageKey || !storageKey.startsWith(RESPONSE_CACHE_PREFIX)) continue
        const rawKey = storageKey.slice(RESPONSE_CACHE_PREFIX.length)
        if ((prefix && rawKey.startsWith(cacheKeyOrPrefix)) || (!prefix && rawKey === cacheKeyOrPrefix)) {
          keysToDelete.push(storageKey)
        }
      }
      keysToDelete.forEach((storageKey) => storage?.removeItem(storageKey))
    } catch (_) {
      // ignore
    }
  }
}

async function requestWithCache(endpoint, {
  requestOptions = {},
  cacheKey = '',
  maxAgeMs = 30000,
  forceRefresh = false,
  dedupe = true,
  fallbackToStaleOnError = true,
} = {}) {
  if (!cacheKey) {
    return request(endpoint, requestOptions)
  }

  if (!forceRefresh) {
    const freshCached = readCachedResponse(cacheKey, maxAgeMs)
    if (freshCached) return freshCached
  }

  if (dedupe && inflightCacheRequests.has(cacheKey)) {
    return inflightCacheRequests.get(cacheKey)
  }

  const pending = request(endpoint, requestOptions)
    .then((payload) => writeResponseCacheEntry(cacheKey, payload))
    .catch((error) => {
      if (fallbackToStaleOnError) {
        const staleCached = peekCachedResponse(cacheKey)
        if (staleCached) return staleCached
      }
      throw error
    })
    .finally(() => {
      inflightCacheRequests.delete(cacheKey)
    })

  if (dedupe) {
    inflightCacheRequests.set(cacheKey, pending)
  }

  return pending
}

function profileCacheKey() {
  return 'profile'
}

function leaderboardCacheKey(type = 'duel') {
  return `leaderboard:${type}`
}

function shopItemsCacheKey(category = null) {
  return `shop_items:${category || 'all'}`
}

function shopHistoryCacheKey() {
  return 'shop_history'
}

// API методы
export const api = {
  // Пользователь
  getUser: () => request('/user'),
  getProfile: () => request('/profile'),
  getProfileCached: ({ maxAgeMs = 30000, forceRefresh = false } = {}) => (
    requestWithCache('/profile', {
      cacheKey: profileCacheKey(),
      maxAgeMs,
      forceRefresh,
    })
  ),
  peekProfileCache: () => peekCachedResponse(profileCacheKey()),
  
  // Дуэли
  getActiveDuel: () => request('/duel/current'),
  createDuel: (mode = 'random', extra = {}) => request('/duel/create', {
    method: 'POST',
    body: JSON.stringify({ mode, ...extra })
  }),
  
  getDuel: (duelId) => request(`/duel/${duelId}`),
  getDuelWsTicket: (duelId) => request(`/duel/ws-ticket?duel_id=${duelId}`),
  
  submitAnswer: (duelId, roundId, answerId) => request('/duel/answer', {
    method: 'POST',
    body: JSON.stringify({ duelId, roundId, answerId })
  }),
  
  cancelDuel: (duelId) => request(`/duel/${duelId}/cancel`, {
    method: 'POST'
  }),
  getIncomingRematch: () => request('/duel/rematch/incoming'),
  acceptRematch: (duelId) => request('/duel/rematch/accept', {
    method: 'POST',
    body: JSON.stringify({ duel_id: duelId })
  }),
  declineRematch: (duelId) => request('/duel/rematch/decline', {
    method: 'POST',
    body: JSON.stringify({ duel_id: duelId })
  }),
  cancelRematch: (duelId) => request('/duel/rematch/cancel', {
    method: 'POST',
    body: JSON.stringify({ duel_id: duelId })
  }),
  
  joinDuel: (code) => request('/duel/join', {
    method: 'POST',
    body: JSON.stringify({ code })
  }),
  
  useHint: (duelId, hintType = 'fifty_fifty') => request('/duel/hint', {
    method: 'POST',
    body: JSON.stringify({ duelId, hintType })
  }),

  // Правда или ложь
  getTrueFalseQuestion: () => request('/truefalse/question'),
  
  submitTrueFalseAnswer: (factId, answer) => request('/truefalse/answer', {
    method: 'POST',
    body: JSON.stringify({ factId, answer })
  }),

  // Рейтинг
  getLeaderboard: (type = 'duel') => request(`/leaderboard?type=${type}`),
  getLeaderboardCached: (type = 'duel', { maxAgeMs = 30000, forceRefresh = false } = {}) => (
    requestWithCache(`/leaderboard?type=${type}`, {
      cacheKey: leaderboardCacheKey(type),
      maxAgeMs,
      forceRefresh,
    })
  ),
  peekLeaderboardCache: (type = 'duel') => peekCachedResponse(leaderboardCacheKey(type)),

  // Статистика
  getStatistics: () => request('/statistics'),
  getQuickStatistics: () => request('/statistics/quick'),

  // Реферальная система
  getReferralStats: () => request('/referral/stats'),
  sendSupportMessage: (message, topic = 'general') => request('/support/message', {
    method: 'POST',
    body: JSON.stringify({ message, topic })
  }),

  // Магазин
  getShopItems: (category = null) => request(`/shop/items${category ? `?category=${category}` : ''}`),
  getShopItemsCached: (category = null, { maxAgeMs = 30000, forceRefresh = false } = {}) => (
    requestWithCache(`/shop/items${category ? `?category=${category}` : ''}`, {
      cacheKey: shopItemsCacheKey(category),
      maxAgeMs,
      forceRefresh,
    })
  ),
  peekShopItemsCache: (category = null) => peekCachedResponse(shopItemsCacheKey(category)),
  purchaseItem: (itemId, quantity = 1) => request('/shop/purchase', {
    method: 'POST',
    body: JSON.stringify({ item_id: itemId, quantity })
  }).then((response) => {
    if (response?.success) {
      invalidateResponseCache('shop_items:', { prefix: true })
      invalidateResponseCache(shopHistoryCacheKey())
      invalidateResponseCache(profileCacheKey())
    }
    return response
  }),
  getShopHistory: () => request('/shop/history'),
  getShopHistoryCached: ({ maxAgeMs = 30000, forceRefresh = false } = {}) => (
    requestWithCache('/shop/history', {
      cacheKey: shopHistoryCacheKey(),
      maxAgeMs,
      forceRefresh,
    })
  ),
  peekShopHistoryCache: () => peekCachedResponse(shopHistoryCacheKey()),

  // Инвентарь
  getInventory: () => request('/inventory'),
  equipCosmetic: (cosmeticId) => request('/inventory/equip', {
    method: 'POST',
    body: JSON.stringify({ cosmetic_id: cosmeticId })
  }),
  unequipCosmetic: (cosmeticType) => request('/inventory/unequip', {
    method: 'POST',
    body: JSON.stringify({ cosmetic_type: cosmeticType })
  }),

  // Колесо фортуны
  getWheelStatus: () => request('/wheel/status'),
  spinWheel: (usePremium = false) => request('/wheel/spin', {
    method: 'POST',
    body: JSON.stringify({ use_premium: usePremium })
  }),
  getWheelConfig: () => request('/wheel/config'),

  // Лутбоксы
  openLootbox: (lootboxType) => request('/lootbox/open', {
    method: 'POST',
    body: JSON.stringify({ lootbox_type: lootboxType })
  }),
  getLootboxConfig: () => request('/lootbox/config'),
  getLootboxHistory: () => request('/lootbox/history'),

  // Бусты
  getActiveBoosts: () => request('/boosts'),

  // Достижения
  getAchievements: () => request('/achievements'),
  getMyAchievements: () => request('/achievements/my'),
  getShowcasedAchievements: () => request('/achievements/showcased'),
  setShowcasedAchievements: (achievementIds) => request('/achievements/showcase', {
    method: 'POST',
    body: JSON.stringify({ achievement_ids: achievementIds })
  }),
  getAchievementStats: () => request('/achievements/stats'),

  // Коллекции
  getCollections: () => request('/collections'),
  getCollectionItems: (collectionId) => request(`/collections/${collectionId}/items`),

  // Разное
  getOnline: () => request('/online'),
  getAdminNotificationsFeed: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/notifications/admin${query ? `?${query}` : ''}`)
  },

  // Админ
  getAdminStats: () => request('/admin/stats'),
  getAdminCategoryAnalytics: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/admin/analytics/categories${query ? `?${query}` : ''}`)
  },
  getAdminQuestionAnalytics: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/admin/analytics/questions${query ? `?${query}` : ''}`)
  },
  getAdminUsers: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/admin/users${query ? `?${query}` : ''}`)
  },
  getAdminDuels: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/admin/duels${query ? `?${query}` : ''}`)
  },
  getAdminDuelDetails: (duelId) => request(`/admin/duels/${duelId}`),
  getAdminFacts: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/admin/facts${query ? `?${query}` : ''}`)
  },
  getAdminNotifications: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return request(`/admin/notifications${query ? `?${query}` : ''}`)
  },
  adminBroadcastNotification: (title, message) => request('/admin/notifications/broadcast', {
    method: 'POST',
    body: JSON.stringify({ title, message })
  }),
  adminGrantLootbox: (payload) => request('/admin/lootbox/grant', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  getAdminFrames: () => request('/admin/frames'),
  adminUpsertFrame: (payload) => request('/admin/frames/upsert', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  adminGrantFrame: (payload) => request('/admin/frames/grant', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  adminUpdateFrameShopItem: (payload) => request('/admin/frames/shop/update', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  adminRemoveFrameShopItem: (payload) => request('/admin/frames/shop/remove', {
    method: 'POST',
    body: JSON.stringify(payload)
  }),
  isAdmin: () => request('/admin/check'),
  adminCancelDuel: (duelId) => request(`/admin/duel/${duelId}/cancel`, { method: 'POST' }),
  adminCancelDuelByCode: (code) => request('/admin/duel/by-code/cancel', {
    method: 'POST',
    body: JSON.stringify({ code })
  }),
  adminCancelAllDuels: () => request('/admin/duels/cancel-all', { method: 'POST' }),
  adminAddQuestion: (data) => request('/admin/question', { method: 'POST', body: JSON.stringify(data) }),
  adminAddFact: (data) => request('/admin/fact', { method: 'POST', body: JSON.stringify(data) }),
  adminToggleFact: (factId, isActive = null) => request(`/admin/fact/${factId}/toggle`, {
    method: 'POST',
    body: JSON.stringify(isActive === null ? {} : { is_active: isActive })
  }),

  prefetchCorePages: ({ forceRefresh = false } = {}) => Promise.allSettled([
    api.getProfileCached({ forceRefresh, maxAgeMs: 45000 }),
    api.getShopItemsCached(null, { forceRefresh, maxAgeMs: 45000 }),
    api.getShopHistoryCached({ forceRefresh, maxAgeMs: 45000 }),
    api.getLeaderboardCached('duel', { forceRefresh, maxAgeMs: 45000 }),
    api.getLeaderboardCached('truefalse', { forceRefresh, maxAgeMs: 45000 }),
  ]),
}


export function getWsBaseUrl() {
  return WS_BASE_URL
}

export default api
