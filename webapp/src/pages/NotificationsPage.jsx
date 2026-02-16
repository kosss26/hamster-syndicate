import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  getNotificationItems,
  removeNotificationItem,
  clearNotificationItems,
  subscribeNotifications,
} from '../utils/notificationInbox'

const rarityClasses = {
  common: 'border-white/10 bg-white/5',
  rare: 'border-sky-300/30 bg-sky-500/10',
  epic: 'border-violet-300/30 bg-violet-500/10',
  legendary: 'border-amber-300/35 bg-amber-500/10',
}

export default function NotificationsPage() {
  const navigate = useNavigate()
  const [items, setItems] = useState([])

  useEffect(() => {
    setItems(getNotificationItems())
    return subscribeNotifications((next) => setItems(next))
  }, [])

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="noise-overlay" />

      <div className="relative z-10 px-4 pt-4 space-y-4">
        <div className="rounded-2xl border border-white/10 bg-black/25 backdrop-blur-xl p-4 sticky top-0">
          <div className="flex items-center justify-between mb-3">
            <button
              onClick={() => navigate(-1)}
              className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <h1 className="text-white font-bold">Уведомления</h1>
            <button
              onClick={() => clearNotificationItems()}
              className="h-9 px-3 rounded-xl bg-red-500/15 border border-red-400/30 text-red-200 text-xs"
            >
              Очистить
            </button>
          </div>
          <div className="text-xs text-white/55">{items.length > 0 ? `Всего: ${items.length}` : 'Список пуст'}</div>
        </div>

        <div className="space-y-2 pb-6">
          {items.length === 0 ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-white/45 text-sm">
              Новых уведомлений нет
            </div>
          ) : (
            items.map((item) => {
              const rarityClass = rarityClasses[item.rarity] || rarityClasses.common
              return (
                <div key={item.id} className={`rounded-2xl border p-4 ${rarityClass}`}>
                  <div className="flex items-start gap-3">
                    <div className="w-10 h-10 rounded-xl bg-black/25 border border-white/10 flex items-center justify-center text-lg">
                      {item.icon || '🔔'}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm text-white font-semibold truncate">{item.title}</p>
                      {item.subtitle ? <p className="text-xs text-white/70 mt-1">{item.subtitle}</p> : null}
                      <p className="text-[11px] text-white/40 mt-2">{new Date(item.created_at).toLocaleString('ru-RU')}</p>
                    </div>
                    <button
                      onClick={() => removeNotificationItem(item.id)}
                      className="w-8 h-8 rounded-lg bg-white/5 border border-white/10 text-white/70"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              )
            })
          )}
        </div>
      </div>
    </div>
  )
}
