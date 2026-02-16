const STORAGE_KEY = 'quiz_bot_notifications_v1'
const DISMISSED_ADMIN_KEY = 'quiz_bot_notifications_admin_dismissed_v1'
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

function getDismissedAdminIds() {
  if (typeof window === 'undefined') return []
  return safeParse(window.localStorage.getItem(DISMISSED_ADMIN_KEY))
}

function saveDismissedAdminIds(ids) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(DISMISSED_ADMIN_KEY, JSON.stringify(ids))
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
      source: item.source || 'local',
      source_id: item.source_id ?? null,
      created_at: new Date(now + index).toISOString(),
    }))

  if (normalized.length === 0) return

  const next = [...normalized.reverse(), ...current]
  saveNotificationItems(next)
}

export function removeNotificationItem(id) {
  if (!id) return
  const current = getNotificationItems()
  const target = current.find((item) => item.id === id)
  if (target?.source === 'admin' && target?.source_id) {
    const dismissed = getDismissedAdminIds()
    if (!dismissed.includes(target.source_id)) {
      saveDismissedAdminIds([...dismissed, target.source_id])
    }
  }
  const next = current.filter((item) => item.id !== id)
  saveNotificationItems(next)
}

export function clearNotificationItems() {
  const current = getNotificationItems()
  const adminSourceIds = current
    .filter((item) => item.source === 'admin' && item.source_id)
    .map((item) => item.source_id)
  if (adminSourceIds.length > 0) {
    const dismissed = getDismissedAdminIds()
    const merged = [...new Set([...dismissed, ...adminSourceIds])]
    saveDismissedAdminIds(merged)
  }
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

export function mergeAdminNotifications(items) {
  if (!Array.isArray(items) || items.length === 0) return

  const current = getNotificationItems()
  const currentAdminIds = new Set(
    current.filter((item) => item.source === 'admin' && item.source_id).map((item) => item.source_id)
  )
  const dismissed = new Set(getDismissedAdminIds())

  const incoming = items
    .filter((item) => item && item.id && item.title && !dismissed.has(item.id) && !currentAdminIds.has(item.id))
    .map((item) => ({
      id: `admin_${item.id}`,
      type: 'admin',
      icon: '📢',
      title: item.title,
      subtitle: item.message || '',
      rarity: 'rare',
      source: 'admin',
      source_id: item.id,
      created_at: item.created_at || new Date().toISOString(),
    }))

  if (incoming.length === 0) return
  const next = [...incoming.reverse(), ...current]
  saveNotificationItems(next)
}
