// Базовый URL API
// В продакшене используем относительный путь /api, который nginx проксирует на PHP
const API_BASE_URL = import.meta.env.VITE_API_URL || '/api'

// Получаем initData из Telegram для авторизации
function getInitData() {
  const initData = window.Telegram?.WebApp?.initData || ''
  
  // Для отладки: если нет initData, но есть user - создаём фейковый
  if (!initData && window.Telegram?.WebApp?.initDataUnsafe?.user) {
    const user = window.Telegram.WebApp.initDataUnsafe.user
    return `user=${encodeURIComponent(JSON.stringify(user))}`
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
      throw new Error(error.message || `HTTP ${response.status}`)
    }

    return response.json()
  } catch (error) {
    console.error('API Error:', error)
    throw error
  }
}

// API методы
export const api = {
  // Пользователь
  getUser: () => request('/user'),
  getProfile: () => request('/profile'),
  
  // Дуэли
  getActiveDuel: () => request('/duel/current'),
  createDuel: (mode = 'random') => request('/duel/create', {
    method: 'POST',
    body: JSON.stringify({ mode })
  }),
  
  getDuel: (duelId) => request(`/duel/${duelId}`),
  
  submitAnswer: (duelId, roundId, answerId) => request('/duel/answer', {
    method: 'POST',
    body: JSON.stringify({ duelId, roundId, answerId })
  }),
  
  cancelDuel: (duelId) => request(`/duel/${duelId}/cancel`, {
    method: 'POST'
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

  // Статистика
  getStatistics: () => request('/statistics'),
  getQuickStatistics: () => request('/statistics/quick'),

  // Реферальная система
  getReferralStats: () => request('/referral/stats'),

  // Магазин
  getShopItems: (category = null) => request(`/shop/items${category ? `?category=${category}` : ''}`),
  purchaseItem: (itemId, quantity = 1) => request('/shop/purchase', {
    method: 'POST',
    body: JSON.stringify({ item_id: itemId, quantity })
  }),
  getShopHistory: () => request('/shop/history'),

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

  // Админ
  getAdminStats: () => request('/admin/stats'),
  isAdmin: () => request('/admin/check'),
  adminCancelDuel: (duelId) => request(`/admin/duel/${duelId}/cancel`, { method: 'POST' }),
  adminCancelAllDuels: () => request('/admin/duels/cancel-all', { method: 'POST' }),
  adminAddQuestion: (data) => request('/admin/question', { method: 'POST', body: JSON.stringify(data) }),
}

export default api

