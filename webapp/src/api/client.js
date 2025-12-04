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
}

export default api

