import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import api from '../api/client'
import { hapticFeedback } from '../hooks/useTelegram'
import CoinIcon from '../components/CoinIcon'
import TicketIcon from '../components/TicketIcon'

const LOCALE_RU = 'ru-RU'

function formatShortDate(isoString) {
  if (!isoString) return '—'
  try {
    return new Date(isoString).toLocaleDateString(LOCALE_RU, {
      day: '2-digit',
      month: '2-digit',
    })
  } catch (_) {
    return '—'
  }
}

function formatCount(value) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) return '0'
  return number.toLocaleString(LOCALE_RU)
}

function getSeasonProgressPercent(season) {
  const current = Number(season?.points_into_level || 0)
  const perLevel = Number(season?.points_per_level || 100)
  if (!Number.isFinite(current) || !Number.isFinite(perLevel) || perLevel <= 0) return 0
  return Math.max(0, Math.min(100, (current / perLevel) * 100))
}

function RewardChips({ reward }) {
  const coins = Math.max(0, Number(reward?.coins || 0))
  const experience = Math.max(0, Number(reward?.experience || 0))
  const tickets = Math.max(0, Number(reward?.tickets || 0))

  return (
    <div className="flex items-center gap-2 text-[11px] text-white/75 flex-wrap">
      {coins > 0 && (
        <span className="inline-flex items-center gap-1 rounded-full border border-amber-300/35 bg-amber-500/12 px-2 py-1">
          <CoinIcon className="w-3.5 h-3.5" />+{coins}
        </span>
      )}
      {experience > 0 && (
        <span className="inline-flex items-center gap-1 rounded-full border border-violet-300/35 bg-violet-500/12 px-2 py-1">
          ⭐ +{experience} XP
        </span>
      )}
      {tickets > 0 && (
        <span className="inline-flex items-center gap-1 rounded-full border border-cyan-300/35 bg-cyan-500/12 px-2 py-1">
          <TicketIcon className="w-3.5 h-3.5" />+{tickets}
        </span>
      )}
    </div>
  )
}

function MissionListSection({
  title,
  subtitle,
  missions,
  claimingRewardKey,
  onClaim,
}) {
  if (!Array.isArray(missions) || missions.length === 0) {
    return null
  }

  return (
    <section className="rounded-2xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
      <div className="flex items-center justify-between gap-2 mb-3">
        <h3 className="text-white font-semibold text-sm">{title}</h3>
        {subtitle ? <span className="text-[11px] text-white/50">{subtitle}</span> : null}
      </div>

      <div className="space-y-2">
        {missions.map((mission) => {
          const progress = Math.max(0, Number(mission?.progress || 0))
          const target = Math.max(1, Number(mission?.target || 1))
          const isClaimed = Boolean(mission?.claimed)
          const isClaiming = claimingRewardKey === mission?.claim_key
          const periodTitle = `${formatShortDate(mission?.period?.start)} - ${formatShortDate(mission?.period?.end)}`

          return (
            <div key={`${mission.id}-${mission.claim_key}`} className="rounded-xl border border-white/10 bg-white/5 p-3">
              <div className="flex items-start justify-between gap-3 mb-2">
                <div className="min-w-0">
                  <p className="text-white text-sm font-medium">{mission.title}</p>
                  <p className="text-white/55 text-xs">{mission.description}</p>
                  <p className="text-white/35 text-[10px] mt-1">{periodTitle}</p>
                </div>
                <span className="text-[11px] text-cyan-200 whitespace-nowrap">
                  {formatCount(progress)}/{formatCount(target)}
                </span>
              </div>

              <div className="h-1.5 rounded-full bg-white/10 overflow-hidden mb-2">
                <div
                  className="h-full bg-gradient-to-r from-emerald-400 to-cyan-400"
                  style={{ width: `${Math.max(0, Math.min(100, (progress / target) * 100))}%` }}
                />
              </div>

              <div className="flex items-center justify-between gap-2">
                <RewardChips reward={mission.reward} />
                <button
                  onClick={() => onClaim(mission.claim_key)}
                  disabled={isClaimed || !mission.can_claim || isClaiming}
                  className="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold border border-cyan-300/35 bg-cyan-500/15 text-cyan-100 disabled:opacity-45 disabled:cursor-not-allowed"
                >
                  {isClaimed ? 'Получено' : isClaiming ? '...' : mission.can_claim ? 'Забрать' : 'В процессе'}
                </button>
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}

function TasksPage() {
  const [loading, setLoading] = useState(true)
  const [liveOps, setLiveOps] = useState(null)
  const [claimingRewardKey, setClaimingRewardKey] = useState('')
  const [error, setError] = useState('')

  const dailyMissions = Array.isArray(liveOps?.daily_missions)
    ? liveOps.daily_missions
    : (Array.isArray(liveOps?.missions) ? liveOps.missions.filter((item) => item?.frequency === 'daily') : [])

  const weeklyMissions = Array.isArray(liveOps?.weekly_missions)
    ? liveOps.weekly_missions
    : (Array.isArray(liveOps?.missions) ? liveOps.missions.filter((item) => item?.frequency === 'weekly') : [])

  useEffect(() => {
    loadLiveOps()
  }, [])

  const loadLiveOps = async () => {
    setLoading(true)
    setError('')
    try {
      const response = await api.getLiveOpsDashboard()
      if (response.success) {
        setLiveOps(response.data)
      } else {
        throw new Error(response.error || 'Не удалось загрузить задания')
      }
    } catch (err) {
      setError(String(err?.message || 'Не удалось загрузить задания'))
    } finally {
      setLoading(false)
    }
  }

  const claimReward = async (claimKey) => {
    if (!claimKey || claimingRewardKey) return
    setClaimingRewardKey(claimKey)
    try {
      const response = await api.claimLiveOpsReward(claimKey)
      if (response.success) {
        hapticFeedback('success')
        if (response.data?.dashboard) {
          setLiveOps(response.data.dashboard)
        } else {
          await loadLiveOps()
        }
        api.getProfileCached({ forceRefresh: true }).catch(() => {})
      }
    } catch (_) {
      hapticFeedback('error')
    } finally {
      setClaimingRewardKey('')
    }
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="noise-overlay" />
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/45 text-sm">Загрузка заданий...</p>
        </div>
      </div>
    )
  }

  if (error || !liveOps) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden p-5">
        <div className="noise-overlay" />
        <div className="relative z-10 max-w-md mx-auto pt-16">
          <div className="rounded-2xl border border-red-400/35 bg-red-500/12 p-4">
            <h2 className="text-red-200 font-semibold mb-2">Ошибка загрузки</h2>
            <p className="text-red-100/80 text-sm mb-4">{error || 'Не удалось получить задания'}</p>
            <button onClick={loadLiveOps} className="w-full py-3 rounded-xl bg-white text-slate-900 font-semibold">
              Обновить
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="aurora-blob aurora-blob-1 opacity-70" />
      <div className="aurora-blob aurora-blob-3 opacity-55" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-4 pt-4 space-y-3">
        <section className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-2">
            <button
              onClick={() => window.history.back()}
              className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center"
            >
              ←
            </button>
            <h1 className="text-white font-bold text-lg">Задания</h1>
            <button
              onClick={loadLiveOps}
              className="px-2.5 py-2 rounded-xl bg-white/5 border border-white/10 text-white/80 text-xs"
            >
              Обновить
            </button>
          </div>
          <p className="text-white/60 text-sm">Ежедневные и еженедельные задания разной направленности + сезон.</p>
        </section>

        <motion.section initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="rounded-2xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between gap-3 mb-2">
            <div>
              <p className="text-[10px] uppercase tracking-wider text-white/45 mb-1">Сезон</p>
              <h3 className="text-white font-semibold text-sm">{liveOps.season?.title || 'Сезонный прогресс'}</h3>
            </div>
            <div className="text-right">
              <p className="text-white font-bold text-lg leading-none">LVL {liveOps.season?.level || 1}</p>
              <p className="text-white/45 text-[10px]">{liveOps.season?.season_key || ''}</p>
            </div>
          </div>

          <div className="h-2 rounded-full bg-white/10 overflow-hidden mb-2">
            <div
              className="h-full bg-gradient-to-r from-fuchsia-400 to-cyan-400"
              style={{ width: `${getSeasonProgressPercent(liveOps.season)}%` }}
            />
          </div>

          <div className="text-[11px] text-white/65 mb-3">
            {formatCount(liveOps.season?.points_into_level)} / {formatCount(liveOps.season?.points_per_level)} очков до уровня
          </div>

          <div className="grid grid-cols-2 gap-2 text-[11px]">
            <div className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-2 text-white/75">
              Победы в дуэлях: <span className="text-white">{formatCount(liveOps.season?.sources?.duel_wins)}</span>
            </div>
            <div className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-2 text-white/75">
              Верно П/Л: <span className="text-white">{formatCount(liveOps.season?.sources?.truefalse_correct)}</span>
            </div>
            <div className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-2 text-white/75">
              Покупки: <span className="text-white">{formatCount(liveOps.season?.sources?.shop_purchases)}</span>
            </div>
            <div className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-2 text-white/75">
              Инвайты: <span className="text-white">{formatCount(liveOps.season?.sources?.friend_invites)}</span>
            </div>
          </div>
        </motion.section>

        <MissionListSection
          title="Ежедневные задания"
          subtitle={`${formatCount(dailyMissions.length)} шт.`}
          missions={dailyMissions}
          claimingRewardKey={claimingRewardKey}
          onClaim={claimReward}
        />

        <MissionListSection
          title="Еженедельные задания"
          subtitle={`${formatCount(liveOps.summary?.available_claims)} наград доступно`}
          missions={weeklyMissions}
          claimingRewardKey={claimingRewardKey}
          onClaim={claimReward}
        />
      </div>
    </div>
  )
}

export default TasksPage
