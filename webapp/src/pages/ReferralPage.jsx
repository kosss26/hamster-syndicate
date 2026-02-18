import { useEffect, useMemo, useState } from 'react'
import { motion } from 'framer-motion'
import { useNavigate } from 'react-router-dom'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'
import TicketIcon from '../components/TicketIcon'
import ReferralIcon from '../components/ReferralIcon'

function ReferralPage() {
  const { webApp } = useTelegram()
  const navigate = useNavigate()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [copiedLink, setCopiedLink] = useState(false)
  const [copiedCode, setCopiedCode] = useState(false)
  const [copiedDuelLink, setCopiedDuelLink] = useState(false)
  const [playNowLoading, setPlayNowLoading] = useState(false)
  const [playNowInvite, setPlayNowInvite] = useState(null)
  const [playNowError, setPlayNowError] = useState('')

  useEffect(() => {
    if (!webApp?.BackButton) return undefined
    const onBack = () => window.history.back()
    webApp.BackButton.show()
    webApp.BackButton.onClick(onBack)
    return () => {
      webApp.BackButton.offClick(onBack)
      webApp.BackButton.hide()
    }
  }, [webApp])

  useEffect(() => {
    loadStats()
  }, [])

  const loadStats = async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await api.getReferralStats()
      if (!response.success) {
        throw new Error(response.error || 'Ошибка загрузки данных')
      }
      setStats(response.data)
    } catch (err) {
      setError(err.message || 'Ошибка загрузки данных')
    } finally {
      setLoading(false)
    }
  }

  const inviteFriend = () => {
    if (!stats?.referral_link) return
    const text = 'Присоединяйся к Битве знаний! 🎮 Получи бонус за старт.'
    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(stats.referral_link)}&text=${encodeURIComponent(text)}`

    if (webApp?.openLink) {
      webApp.openLink(shareUrl)
    } else {
      window.open(shareUrl, '_blank')
    }
  }

  const copyToClipboard = async (text, type) => {
    if (!text) return
    try {
      await navigator.clipboard.writeText(text)
      if (type === 'code') {
        setCopiedCode(true)
        setTimeout(() => setCopiedCode(false), 1800)
      } else if (type === 'duel') {
        setCopiedDuelLink(true)
        setTimeout(() => setCopiedDuelLink(false), 1800)
      } else {
        setCopiedLink(true)
        setTimeout(() => setCopiedLink(false), 1800)
      }
      hapticFeedback('success')
    } catch (_) {
      hapticFeedback('error')
    }
  }

  const milestoneProgress = useMemo(() => {
    if (!stats?.next_milestone) return 0
    const total = Number(stats.next_milestone.referrals_needed || 0)
    if (total <= 0) return 0
    const current = Number(stats.next_milestone.progress || 0)
    return Math.max(0, Math.min(100, (current / total) * 100))
  }, [stats?.next_milestone])

  const resolveBotUsername = () => {
    const fallback = 'duelquizbot'
    const rawLink = String(stats?.referral_link || '').trim()
    if (!rawLink) return fallback

    try {
      const parsed = new URL(rawLink)
      if (!/(^|\.)t\.me$/i.test(parsed.hostname)) {
        return fallback
      }
      const username = parsed.pathname.replace(/^\/+/, '').split('/')[0]
      return username || fallback
    } catch (_) {
      return fallback
    }
  }

  const buildDuelInviteLink = (inviteCode) => {
    const normalizedCode = String(inviteCode || '').replace(/\D+/g, '').slice(0, 5)
    if (!/^\d{5}$/.test(normalizedCode)) return ''
    const botUsername = resolveBotUsername()
    return `https://t.me/${botUsername}/app?startapp=duel_${normalizedCode}`
  }

  const sharePlayNowInvite = (invite) => {
    if (!invite?.url || !invite?.code) return
    const text = `⚔️ Дуэль уже создана. Заходи прямо сейчас!\nКод: ${invite.code}`
    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(invite.url)}&text=${encodeURIComponent(text)}`

    if (webApp?.openLink) {
      webApp.openLink(shareUrl)
    } else {
      window.open(shareUrl, '_blank')
    }
  }

  const inviteAndPlayNow = async () => {
    if (playNowLoading) return

    setPlayNowLoading(true)
    setPlayNowError('')
    try {
      const response = await api.createDuel('invite')
      if (!response.success) {
        throw new Error(response.error || 'Не удалось создать приватную дуэль')
      }

      const duelId = Number(response.data?.duel_id || 0)
      const duelStatus = String(response.data?.status || '')
      const opponentId = Number(response.data?.opponent_id || 0)
      const isExisting = Boolean(response.data?.existing)
      const inviteCode = String(response.data?.code || '').replace(/\D+/g, '').slice(0, 5)

      if (!/^\d{5}$/.test(inviteCode)) {
        throw new Error('Сервер не вернул корректный код дуэли')
      }

      if (isExisting || duelStatus !== 'waiting' || opponentId > 0) {
        if (duelId > 0) {
          navigate(`/duel/${duelId}`)
        }
        throw new Error('У тебя уже есть активная дуэль. Сначала заверши её.')
      }

      const inviteLink = buildDuelInviteLink(inviteCode)
      const payload = {
        duelId,
        code: inviteCode,
        url: inviteLink,
      }

      setPlayNowInvite(payload)
      sharePlayNowInvite(payload)
      hapticFeedback('success')
    } catch (err) {
      setPlayNowError(err?.message || 'Не удалось запустить приглашение')
      hapticFeedback('error')
    } finally {
      setPlayNowLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="noise-overlay" />
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/45 text-sm">Загрузка рефералов...</p>
        </div>
      </div>
    )
  }

  if (error || !stats) {
    return (
      <div className="min-h-dvh bg-aurora relative overflow-hidden p-5">
        <div className="noise-overlay" />
        <div className="relative z-10 max-w-md mx-auto pt-16">
          <div className="rounded-2xl border border-red-400/35 bg-red-500/12 p-4">
            <h2 className="text-red-200 font-semibold mb-2">Ошибка загрузки</h2>
            <p className="text-red-100/80 text-sm mb-4">{error || 'Не удалось получить данные'}</p>
            <button onClick={loadStats} className="w-full py-3 rounded-xl bg-white text-slate-900 font-semibold">
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

      <div className="relative z-10 px-4 pt-4 space-y-4">
        <section className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
          <div className="flex items-center justify-between mb-2">
            <button
              onClick={() => window.history.back()}
              className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center"
            >
              ←
            </button>
            <h1 className="text-white font-bold text-lg">Приглашай друзей</h1>
            <div className="w-9" />
          </div>
          <p className="text-white/60 text-sm">За активных друзей получаешь монеты, опыт и билеты.</p>
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-4">
          <p className="text-[10px] uppercase tracking-wide text-white/45 mb-2">Твой код</p>
          <button
            onClick={() => copyToClipboard(stats.referral_code, 'code')}
            className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-4 text-left"
          >
            <div className="flex items-center justify-between gap-3">
              <span className="text-xl font-bold tracking-[0.2em] text-white">{stats.referral_code}</span>
              <span className={`text-xs font-semibold ${copiedCode ? 'text-emerald-200' : 'text-white/60'}`}>
                {copiedCode ? 'Скопировано' : 'Копировать'}
              </span>
            </div>
          </button>

          <div className="grid grid-cols-2 gap-2 mt-3">
            <button
              onClick={inviteFriend}
              className="py-3 rounded-xl border border-cyan-300/35 bg-cyan-500/15 text-white font-semibold active:scale-[0.98]"
            >
              Пригласить
            </button>
            <button
              onClick={() => copyToClipboard(stats.referral_link, 'link')}
              className="py-3 rounded-xl border border-white/15 bg-white/5 text-white font-semibold active:scale-[0.98]"
            >
              {copiedLink ? 'Скопировано' : 'Ссылка'}
            </button>
          </div>
        </section>

        <section className="rounded-3xl border border-cyan-300/20 bg-cyan-500/10 backdrop-blur-xl p-4">
          <div className="flex items-start justify-between gap-3 mb-3">
            <div>
              <h3 className="text-white font-semibold text-sm">Invite & Play Now</h3>
              <p className="text-white/70 text-xs mt-1">Создаём приватную дуэль и сразу шэрим другу ссылку входа.</p>
            </div>
            <div className="text-cyan-200 text-xs font-semibold">Мгновенно</div>
          </div>

          <button
            onClick={inviteAndPlayNow}
            disabled={playNowLoading}
            className="w-full py-3 rounded-xl border border-cyan-300/35 bg-cyan-500/20 text-white font-semibold disabled:opacity-50 active:scale-[0.98]"
          >
            {playNowLoading ? 'Создаём комнату...' : 'Пригласи и сыграй сейчас'}
          </button>

          {playNowError && (
            <p className="text-red-200/90 text-xs mt-2">{playNowError}</p>
          )}

          {playNowInvite && (
            <div className="mt-3 rounded-2xl border border-white/10 bg-black/25 p-3">
              <p className="text-[11px] text-white/45 mb-1">Код приватной дуэли</p>
              <p className="text-white text-lg font-bold tracking-[0.2em]">{playNowInvite.code}</p>

              <div className="grid grid-cols-3 gap-2 mt-3">
                <button
                  onClick={() => sharePlayNowInvite(playNowInvite)}
                  className="py-2 rounded-lg border border-emerald-300/35 bg-emerald-500/15 text-emerald-100 text-xs font-semibold"
                >
                  Поделиться
                </button>
                <button
                  onClick={() => copyToClipboard(playNowInvite.url, 'duel')}
                  className="py-2 rounded-lg border border-white/15 bg-white/10 text-white text-xs font-semibold"
                >
                  {copiedDuelLink ? 'Скопировано' : 'Копия'}
                </button>
                <button
                  onClick={() => playNowInvite.duelId && navigate(`/duel/${playNowInvite.duelId}`)}
                  className="py-2 rounded-lg border border-cyan-300/35 bg-cyan-500/15 text-cyan-100 text-xs font-semibold"
                >
                  В лобби
                </button>
              </div>
            </div>
          )}
        </section>

        <section className="grid grid-cols-2 gap-2">
          <StatCard icon={<ReferralIcon className="w-4 h-4" />} label="Приглашено" value={stats.total_referrals} />
          <StatCard icon="✅" label="Активных" value={stats.active_referrals} />
          <StatCard icon={<CoinIcon className="w-4 h-4" />} label="Монет получено" value={stats.total_coins_earned} />
          <StatCard icon={<TicketIcon className="w-4 h-4" />} label="Билетов получено" value={stats.total_tickets_earned || 0} />
        </section>

        <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
          <h3 className="text-white font-semibold text-sm mb-2">Награды за активного реферала</h3>
          <div className="flex items-center gap-2 text-sm text-white/75">
            <CoinIcon className="w-4 h-4" />
            <span>+100 монет</span>
            <span className="text-white/30">•</span>
            <span>⭐ +50 опыта</span>
            <span className="text-white/30">•</span>
            <TicketIcon className="w-4 h-4" />
            <span>+1 билет</span>
          </div>
          <p className="text-[11px] text-white/45 mt-2">
            Реферал активируется после 3 сыгранных матчей.
          </p>
        </section>

        {stats.next_milestone && (
          <motion.section
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4"
          >
            <div className="flex items-start justify-between gap-3 mb-3">
              <div>
                <p className="text-white font-semibold text-sm">{stats.next_milestone.title}</p>
                <p className="text-white/45 text-xs">Следующая milestone-награда</p>
              </div>
              <div className="text-cyan-200 text-xs font-semibold">
                {stats.next_milestone.progress}/{stats.next_milestone.referrals_needed}
              </div>
            </div>

            <div className="h-2 rounded-full bg-white/10 overflow-hidden mb-3">
              <div className="h-full bg-gradient-to-r from-cyan-400 to-indigo-400" style={{ width: `${milestoneProgress}%` }} />
            </div>

            <div className="flex items-center gap-3 text-xs text-white/75">
              <span className="inline-flex items-center gap-1.5"><CoinIcon className="w-3.5 h-3.5" /> +{stats.next_milestone.reward_coins}</span>
              <span>⭐ +{stats.next_milestone.reward_experience}</span>
              {Number(stats.next_milestone.reward_tickets || 0) > 0 && (
                <span className="inline-flex items-center gap-1.5"><TicketIcon className="w-3.5 h-3.5" /> +{stats.next_milestone.reward_tickets}</span>
              )}
            </div>
          </motion.section>
        )}

        {stats.referrals?.length > 0 && (
          <section className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4">
            <h3 className="text-white font-semibold text-sm mb-3">Твои рефералы</h3>
            <div className="space-y-2">
              {stats.referrals.map((referral, index) => (
                <ReferralRow key={`${referral.user?.id || index}-${index}`} referral={referral} />
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  )
}

function StatCard({ icon, label, value }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">
      <p className="text-[11px] text-white/45 mb-1">{label}</p>
      <p className="text-white font-semibold text-sm flex items-center gap-1.5">
        {icon}
        {value ?? 0}
      </p>
    </div>
  )
}

function ReferralRow({ referral }) {
  const isActive = referral.status === 'active'
  return (
    <div className="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3">
      <div className="min-w-0">
        <p className="text-white text-sm font-medium truncate">{referral.user?.name || 'Пользователь'}</p>
        <p className="text-[11px] text-white/45 mt-1">
          {referral.games_played || 0} игр • {referral.created_at || '—'}
        </p>
      </div>
      <span className={`text-[11px] px-2 py-1 rounded-full border ${isActive ? 'border-emerald-300/35 bg-emerald-500/12 text-emerald-200' : 'border-amber-300/35 bg-amber-500/12 text-amber-200'}`}>
        {isActive ? 'Активен' : 'Ожидает'}
      </span>
    </div>
  )
}

export default ReferralPage
