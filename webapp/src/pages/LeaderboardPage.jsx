import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { hapticFeedback, useTelegram } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'

const ADMIN_IDS = [1763619724]

const TAB_CONFIG = {
  duel: {
    id: 'duel',
    title: 'Дуэли',
    metric: 'MMR',
    empty: 'Пока нет результатов дуэлей',
    icon: '⚔️',
  },
  truefalse: {
    id: 'truefalse',
    title: 'Правда или ложь',
    metric: 'Серия',
    empty: 'Пока нет результатов режима П/Л',
    icon: '🧠',
  },
}

const PODIUM_META = {
  1: { medal: '🥇', ring: 'border-amber-300/40 bg-amber-500/10', score: 'text-amber-200' },
  2: { medal: '🥈', ring: 'border-slate-300/35 bg-slate-400/10', score: 'text-slate-200' },
  3: { medal: '🥉', ring: 'border-orange-300/35 bg-orange-500/10', score: 'text-orange-200' },
}

function metricForPlayer(tab, player) {
  return tab === 'duel' ? (player?.rating ?? 0) : (player?.record ?? 0)
}

function rankMarker(place) {
  if (place === 1) return '🥇'
  if (place === 2) return '🥈'
  if (place === 3) return '🥉'
  return `#${place}`
}

function PodiumCard({ player, place, tab }) {
  const meta = PODIUM_META[place]
  const value = metricForPlayer(tab, player)

  return (
    <div className={`rounded-2xl border p-3 text-center ${meta.ring}`}>
      <div className="text-xl mb-2">{meta.medal}</div>
      <div className="w-14 h-14 mx-auto rounded-full overflow-hidden bg-black/25 border border-white/10">
        {player?.photo_url ? (
          <img src={player.photo_url} alt={player?.name || 'Игрок'} className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-white font-bold">
            {player?.name?.[0] || '?'}
          </div>
        )}
      </div>
      <div className="mt-2 text-sm font-semibold text-white truncate">{player?.name || '—'}</div>
      <div className={`text-base font-black ${meta.score}`}>{value}</div>
    </div>
  )
}

function RankRow({ player, place, tab, isMe, isAdmin }) {
  return (
    <div className={`rounded-2xl border px-3 py-3 flex items-center gap-3 ${isMe ? 'border-cyan-300/35 bg-cyan-500/12' : 'border-white/10 bg-white/5'}`}>
      <div className="w-10 text-center text-sm font-semibold text-white/55">{rankMarker(place)}</div>
      <div className="w-10 h-10 rounded-full overflow-hidden bg-black/25 border border-white/10 shrink-0">
        {player?.photo_url ? (
          <img src={player.photo_url} alt={player?.name || 'Игрок'} className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-white font-bold text-sm">
            {player?.name?.[0] || '?'}
          </div>
        )}
      </div>

      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 min-w-0">
          <p className="text-sm font-semibold text-white truncate">{player?.name || 'Игрок'}</p>
          {isMe ? <span className="text-[10px] px-1.5 py-0.5 rounded bg-cyan-400/20 text-cyan-200 border border-cyan-300/30">Вы</span> : null}
        </div>
        {isAdmin && player?.username ? <p className="text-[10px] text-white/35 truncate">@{player.username}</p> : null}
      </div>

      <div className="text-right">
        <div className={`text-base font-black ${isMe ? 'text-cyan-200' : 'text-white'}`}>{metricForPlayer(tab, player)}</div>
        <div className="text-[10px] text-white/35">{TAB_CONFIG[tab].metric}</div>
      </div>
    </div>
  )
}

export default function LeaderboardPage() {
  const navigate = useNavigate()
  const { user } = useTelegram()

  const [activeTab, setActiveTab] = useState('duel')
  const [leaderboard, setLeaderboard] = useState({ duel: [], truefalse: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const isAdmin = Boolean(user && ADMIN_IDS.includes(user.id))

  const data = useMemo(() => (activeTab === 'duel' ? leaderboard.duel : leaderboard.truefalse), [activeTab, leaderboard])

  const currentUser = useMemo(() => {
    if (!user?.username) return { player: null, place: null }
    const index = data.findIndex((p) => p.username === user.username)
    return index >= 0 ? { player: data[index], place: index + 1 } : { player: null, place: null }
  }, [data, user?.username])

  const topThree = useMemo(() => [data[0], data[1], data[2]], [data])
  const listTail = useMemo(() => data.slice(3), [data])

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [duelRes, tfRes] = await Promise.all([api.getLeaderboard('duel'), api.getLeaderboard('truefalse')])
      setLeaderboard({
        duel: duelRes.success ? (duelRes.data.players || []) : [],
        truefalse: tfRes.success ? (tfRes.data.players || []) : [],
      })
    } catch (err) {
      setError(err.message || 'Ошибка загрузки рейтинга')
    } finally {
      setLoading(false)
    }
  }

  const setTab = (tabId) => {
    if (tabId === activeTab) return
    setActiveTab(tabId)
    hapticFeedback('light')
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="spinner" />
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="noise-overlay" />

      <div className="relative z-10 px-4 pt-4 space-y-4">
        <div className="rounded-2xl border border-white/10 bg-black/25 backdrop-blur-xl p-4 sticky top-0">
          <div className="flex items-center justify-between mb-3">
            <button
              onClick={() => navigate('/')}
              className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <h1 className="text-white font-bold">Рейтинг</h1>
            <button
              onClick={loadData}
              className="h-9 px-3 rounded-xl border border-white/10 bg-white/5 text-xs text-white/80"
            >
              Обновить
            </button>
          </div>

          <div className="grid grid-cols-2 gap-2 rounded-xl border border-white/10 bg-white/5 p-1">
            <button
              onClick={() => setTab('duel')}
              className={`h-10 rounded-lg text-sm font-semibold ${activeTab === 'duel' ? 'bg-cyan-400/20 border border-cyan-300/40 text-white' : 'text-white/65'}`}
            >
              ⚔️ Дуэли
            </button>
            <button
              onClick={() => setTab('truefalse')}
              className={`h-10 rounded-lg text-sm font-semibold ${activeTab === 'truefalse' ? 'bg-cyan-400/20 border border-cyan-300/40 text-white' : 'text-white/65'}`}
            >
              🧠 П/Л
            </button>
          </div>
        </div>

        {error ? (
          <div className="rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-200">
            {error}
            <button onClick={loadData} className="block mt-2 underline">Повторить</button>
          </div>
        ) : null}

        {data.length === 0 ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-white/50 text-sm">
            {TAB_CONFIG[activeTab].empty}
          </div>
        ) : (
          <>
            <div className="grid grid-cols-3 gap-2">
              <PodiumCard player={topThree[1]} place={2} tab={activeTab} />
              <PodiumCard player={topThree[0]} place={1} tab={activeTab} />
              <PodiumCard player={topThree[2]} place={3} tab={activeTab} />
            </div>

            <div className="space-y-2 pb-6">
              {listTail.map((player, index) => {
                const place = index + 4
                const isMe = Boolean(user?.username && player?.username === user.username)
                return (
                  <RankRow
                    key={`${activeTab}_${player.username || player.name || index}_${place}`}
                    player={player}
                    place={place}
                    tab={activeTab}
                    isMe={isMe}
                    isAdmin={isAdmin}
                  />
                )
              })}
            </div>
          </>
        )}
      </div>

      {currentUser.player && currentUser.place > 3 ? (
        <div className="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] left-4 right-4 z-30">
          <div className="rounded-2xl border border-cyan-300/35 bg-[#0f172a]/90 backdrop-blur-xl p-3 flex items-center gap-3">
            <div className="w-10 text-center text-sm font-semibold text-cyan-100">#{currentUser.place}</div>
            <AvatarWithFrame user={user} size={36} />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-white truncate">Вы</p>
              <p className="text-[11px] text-white/45">{TAB_CONFIG[activeTab].title}</p>
            </div>
            <div className="text-right text-cyan-200 font-black text-lg">{metricForPlayer(activeTab, currentUser.player)}</div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
