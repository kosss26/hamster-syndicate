import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'

function CollectionCard({ collection, onOpen }) {
  const progress = Math.max(0, Math.min(100, Number(collection.progress_percent || 0)))
  const isCompleted = Boolean(collection.is_completed)

  return (
    <button
      onClick={onOpen}
      className={`w-full text-left rounded-2xl border p-4 transition-all active:scale-[0.99] ${
        isCompleted
          ? 'border-emerald-400/30 bg-emerald-500/10'
          : 'border-white/10 bg-white/5'
      }`}
    >
      <div className="flex items-start gap-3">
        <div className="w-12 h-12 rounded-xl border border-white/10 bg-black/20 flex items-center justify-center text-2xl">
          {collection.icon}
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <h3 className="text-sm font-semibold text-white truncate">{collection.title}</h3>
            {isCompleted && <span className="text-emerald-300 text-xs">Готово</span>}
          </div>
          <p className="text-xs text-white/55 mt-1 line-clamp-2">{collection.description}</p>
          <div className="mt-3">
            <div className="flex items-center justify-between text-[10px] text-white/45 mb-1">
              <span>{collection.owned_items || 0}/{collection.total_items || 0}</span>
              <span>{progress.toFixed(0)}%</span>
            </div>
            <div className="h-1.5 rounded-full bg-black/30 overflow-hidden">
              <div className={`h-full ${isCompleted ? 'bg-emerald-300' : 'bg-cyan-300'}`} style={{ width: `${progress}%` }} />
            </div>
          </div>
          {(Number(collection.reward_coins || 0) > 0 || Number(collection.reward_gems || 0) > 0) && (
            <div className="mt-3 flex items-center gap-3 text-xs">
              {Number(collection.reward_coins || 0) > 0 && (
                <div className="flex items-center gap-1 text-yellow-300">
                  <CoinIcon size={12} />
                  <span>{collection.reward_coins}</span>
                </div>
              )}
              {Number(collection.reward_gems || 0) > 0 && (
                <div className="text-cyan-200">💎 {collection.reward_gems}</div>
              )}
            </div>
          )}
        </div>
      </div>
    </button>
  )
}

export default function CollectionsPage() {
  const navigate = useNavigate()
  const [collections, setCollections] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const loadData = async () => {
    try {
      setLoading(true)
      setError(null)
      const response = await api.getCollections()
      setCollections(response?.data?.collections || [])
    } catch (err) {
      setError(err.message || 'Не удалось загрузить коллекции')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const summary = useMemo(() => {
    const totalItems = collections.reduce((sum, c) => sum + Number(c.total_items || 0), 0)
    const ownedItems = collections.reduce((sum, c) => sum + Number(c.owned_items || 0), 0)
    const completed = collections.filter((c) => Boolean(c.is_completed)).length
    const progress = totalItems > 0 ? Math.round((ownedItems / totalItems) * 100) : 0

    return { totalItems, ownedItems, completed, progress }
  }, [collections])

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="text-center">
          <div className="spinner mx-auto mb-3" />
          <p className="text-white/45 text-sm">Загрузка коллекций...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="aurora-blob aurora-blob-3 opacity-30" />
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
            <h1 className="text-white font-bold">Коллекции</h1>
            <div className="w-9" />
          </div>

          <div className="grid grid-cols-3 gap-2">
            <div className="rounded-xl border border-white/10 bg-white/5 p-2">
              <p className="text-[10px] text-white/45 uppercase">Собрано</p>
              <p className="text-white font-black">{summary.ownedItems}/{summary.totalItems}</p>
            </div>
            <div className="rounded-xl border border-white/10 bg-white/5 p-2">
              <p className="text-[10px] text-white/45 uppercase">Закрыто</p>
              <p className="text-emerald-300 font-black">{summary.completed}</p>
            </div>
            <div className="rounded-xl border border-white/10 bg-white/5 p-2">
              <p className="text-[10px] text-white/45 uppercase">Прогресс</p>
              <p className="text-cyan-200 font-black">{summary.progress}%</p>
            </div>
          </div>
        </div>

        {error && (
          <div className="rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-200">
            {error}
            <button onClick={loadData} className="block mt-2 underline">Повторить</button>
          </div>
        )}

        <div className="space-y-2 pb-6">
          {collections.length === 0 ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-white/45 text-sm">
              Коллекции пока не найдены
            </div>
          ) : (
            collections.map((collection) => (
              <CollectionCard
                key={collection.id}
                collection={collection}
                onOpen={() => navigate(`/collections/${collection.id}`)}
              />
            ))
          )}
        </div>
      </div>
    </div>
  )
}
