const STORAGE_KEY = 'quiz_bot_notifications_v1'
const EVENT_NAME = 'quizbot:notifications_updated'
const MAX_ITEMS = 150

function safeParse(raw) {
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch (_) {
    return []
  }
}

export function getNotificationItems() {
  if (typeof window === 'undefined') return []
  return safeParse(window.localStorage.getItem(STORAGE_KEY))
}

function saveNotificationItems(items) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, MAX_ITEMS)))
  window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { count: items.length } }))
}

export function addNotificationItems(items) {
  if (!Array.isArray(items) || items.length === 0) return

  const current = getNotificationItems()
  const now = Date.now()
  const normalized = items
    .filter((item) => item && item.title)
    .map((item, index) => ({
      id: item.id || `notif_${now}_${index}_${Math.random().toString(36).slice(2, 8)}`,
      type: item.type || 'system',
      icon: item.icon || '🔔',
      title: String(item.title),
      subtitle: item.subtitle ? String(item.subtitle) : '',
      rarity: item.rarity || 'common',
      created_at: new Date(now + index).toISOString(),
    }))

  if (normalized.length === 0) return

  const next = [...normalized.reverse(), ...current]
  saveNotificationItems(next)
}

export function removeNotificationItem(id) {
  if (!id) return
  const next = getNotificationItems().filter((item) => item.id !== id)
  saveNotificationItems(next)
}

export function clearNotificationItems() {
  saveNotificationItems([])
}

export function subscribeNotifications(callback) {
  if (typeof window === 'undefined') return () => {}

  const handler = () => callback(getNotificationItems())
  window.addEventListener(EVENT_NAME, handler)

  return () => {
    window.removeEventListener(EVENT_NAME, handler)
  }
}
