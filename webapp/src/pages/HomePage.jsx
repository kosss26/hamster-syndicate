import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'
import TicketIcon from '../components/TicketIcon'
import ModeDuelIcon from '../components/ModeDuelIcon'
import ModeTrueFalseIcon from '../components/ModeTrueFalseIcon'
import ReferralIcon from '../components/ReferralIcon'
import { getNotificationItems, subscribeNotifications } from '../utils/notificationInbox'

function resolveAvatarRingTier(frameKey) {
  const normalized = String(frameKey || '').toLowerCase()
  if (!normalized) return 'default'
  if (normalized.includes('legend')) return 'legend'
  if (
    normalized.includes('epic')
    || normalized.includes('diamond')
    || normalized.includes('rainbow')
    || normalized.includes('royal')
    || normalized.includes('lightning')
  ) {
    return 'epic'
  }
  if (
    normalized.includes('rare')
    || normalized.includes('winner')
    || normalized.includes('streak')
  ) {
    return 'rare'
  }
  return 'default'
}

function HomePage() {
  const { user, webApp } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [onlineCount, setOnlineCount] = useState(null)
  const [isAdmin, setIsAdmin] = useState(false)
  const [loading, setLoading] = useState(true)
  const [notifications, setNotifications] = useState([])
  const [ticketSecondsLeft, setTicketSecondsLeft] = useState(0)
  const [showFriendModePicker, setShowFriendModePicker] = useState(false)
  const [friendJoinCode, setFriendJoinCode] = useState('')
  const [incomingRematch, setIncomingRematch] = useState(null)
  const [rematchLoading, setRematchLoading] = useState(false)
  const [liveOps, setLiveOps] = useState(null)

  useEffect(() => {
    checkActiveDuel()
    loadData()
    setNotifications(getNotificationItems())
    const unsubscribeNotifications = subscribeNotifications((items) => setNotifications(items))

    const interval = setInterval(() => {
      loadOnline()
    }, 30000)

    return () => {
      clearInterval(interval)
      unsubscribeNotifications()
    }
  }, [])

  const loadIncomingRematch = useCallback(async () => {
    try {
      const response = await api.getIncomingRematch()
      if (response.success) {
        setIncomingRematch(response.data?.incoming || null)
      }
    } catch (_) {
      // ignore
    }
  }, [])

  useEffect(() => {
    loadIncomingRematch()
    const interval = setInterval(() => {
      loadIncomingRematch()
    }, 5000)
    return () => clearInterval(interval)
  }, [loadIncomingRematch])

  useEffect(() => {
    const prefetch = () => {
      api.prefetchCorePages().catch(() => {
        // ignore prefetch errors
      })
    }

    let idleId = null
    let timeoutId = null

    if (typeof window !== 'undefined' && typeof window.requestIdleCallback === 'function') {
      idleId = window.requestIdleCallback(prefetch, { timeout: 1200 })
    } else {
      timeoutId = setTimeout(prefetch, 700)
    }

    return () => {
      if (idleId !== null && typeof window.cancelIdleCallback === 'function') {
        window.cancelIdleCallback(idleId)
      }
      if (timeoutId !== null) {
        clearTimeout(timeoutId)
      }
    }
  }, [])

  const checkActiveDuel = async () => {
    try {
      const response = await api.getActiveDuel()
      if (response.success && response.data.duel_id) {
        navigate(`/duel/${response.data.duel_id}`)
      }
    } catch (err) {
      console.error('Failed to check active duel:', err)
    }
  }

  const loadData = async () => {
    setLoading(true)
    try {
      await Promise.all([
        loadProfile(),
        loadOnline(),
        checkAdmin(),
        loadLiveOps(),
      ])
    } finally {
      setLoading(false)
    }
  }

  const loadProfile = async () => {
    try {
      const response = await api.getProfileCached({ forceRefresh: true })
      if (response.success) {
        setProfile(response.data)
        setTicketSecondsLeft(Number(response.data.ticket_seconds_to_next || 0))
      }
    } catch (err) {
      console.error('Failed to load profile:', err)
    }
  }

  const loadOnline = async () => {
    try {
      const response = await api.getOnline()
      if (response.success) {
        setOnlineCount(response.data.online)
      }
    } catch (err) {
      console.error('Failed to load online:', err)
    }
  }

  const loadLiveOps = async () => {
    try {
      const response = await api.getLiveOpsDashboard()
      if (response.success) {
        setLiveOps(response.data)
      }
    } catch (err) {
      console.error('Failed to load liveops dashboard:', err)
    }
  }

  const checkAdmin = async () => {
    try {
      const response = await api.isAdmin()
      if (response.success) {
        setIsAdmin(response.data.is_admin)
      }
    } catch (err) {
      // ignore
    }
  }

  useEffect(() => {
    if (!profile) return
    if ((profile.tickets ?? 0) >= 10) return

    const timer = setInterval(() => {
      setTicketSecondsLeft((prev) => {
        if (prev <= 1) {
          loadProfile()
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => clearInterval(timer)
  }, [profile?.tickets])

  const formatTicketTimer = (seconds) => {
    const safe = Math.max(0, Number(seconds || 0))
    const mm = String(Math.floor(safe / 60)).padStart(2, '0')
    const ss = String(safe % 60).padStart(2, '0')
    return `${mm}:${ss}`
  }

  const ensureTicketsForDuel = () => {
    const tickets = Number(profile?.tickets ?? 0)
    if (tickets > 0) return true

    const text = 'Нет билетов для дуэли. Подожди восстановление или купи билеты в магазине.'
    if (webApp?.showPopup) {
      webApp.showPopup({
        title: 'Билеты закончились',
        message: text,
        buttons: [{ type: 'close', text: 'Ок' }],
      })
    } else {
      window.alert(text)
    }
    hapticFeedback('error')
    return false
  }

  const handlePlay = () => {
    if (!ensureTicketsForDuel()) return
    hapticFeedback('heavy')
    navigate('/duel?mode=random')
  }

  const handleQuickRandom = () => {
    if (!ensureTicketsForDuel()) return
    hapticFeedback('medium')
    navigate('/duel?mode=random')
  }

  const handleQuickFriend = () => {
    if (!ensureTicketsForDuel()) return
    hapticFeedback('medium')
    setFriendJoinCode('')
    setShowFriendModePicker(true)
  }

  const handleCreateFriendRoom = () => {
    if (!ensureTicketsForDuel()) return
    setShowFriendModePicker(false)
    navigate('/duel?mode=invite')
  }

  const handleJoinFriendByCode = () => {
    const code = friendJoinCode.replace(/\D+/g, '').slice(0, 5)
    if (!/^\d{5}$/.test(code)) return
    if (!ensureTicketsForDuel()) return
    setShowFriendModePicker(false)
    navigate(`/duel?mode=enter_code&code=${code}`)
  }

  const handleAcceptRematch = async () => {
    if (!incomingRematch?.duel_id) return
    setRematchLoading(true)
    try {
      const response = await api.acceptRematch(incomingRematch.duel_id)
      if (!response.success) {
        throw new Error(response.error || 'Не удалось принять реванш')
      }
      setIncomingRematch(null)
      navigate(`/duel/${incomingRematch.duel_id}`)
      hapticFeedback('success')
    } catch (err) {
      hapticFeedback('error')
    } finally {
      setRematchLoading(false)
    }
  }

  const handleDeclineRematch = async () => {
    if (!incomingRematch?.duel_id) return
    setRematchLoading(true)
    try {
      await api.declineRematch(incomingRematch.duel_id)
      setIncomingRematch(null)
      hapticFeedback('warning')
    } catch (err) {
      hapticFeedback('error')
    } finally {
      setRematchLoading(false)
    }
  }

  const avatarUrl = profile?.avatar_url || user?.photo_url || null
  const avatarRingTier = resolveAvatarRingTier(profile?.equipped_frame)
  const avatarInitial = (user?.first_name || 'Игрок').slice(0, 1).toUpperCase()
  const assetBase = import.meta.env.BASE_URL || '/'
  const asset = (path) => `${assetBase}${String(path || '').replace(/^\/+/, '')}`
  const uiCover = (fileName) => ({
    backgroundImage: `url(${asset(`assets/ui/${fileName}`)})`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
  })

  return (
    <div className="min-h-dvh bg-aurora bg-page-home relative flex flex-col overflow-hidden">
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-5 pt-5 safe-top">
        <div className="relative rounded-3xl p-4 mb-4 overflow-hidden">
          <div className="absolute inset-0" style={uiCover('header_card_bg@2x.png')} />
          <div className="absolute inset-0 pointer-events-none" style={uiCover('header_card_border@2x.png')} />
          <div
            className="absolute inset-0 pointer-events-none opacity-10 mix-blend-overlay"
            style={{
              backgroundImage: `url(${asset('assets/ui/noise_tile.png')})`,
              backgroundRepeat: 'repeat',
              backgroundSize: '256px 256px',
            }}
          />

          <div className="relative z-10 flex items-center justify-between">
            <div className="flex items-center gap-3 min-w-0">
              <div className="relative w-[52px] h-[52px] shrink-0">
                <img
                  src={asset(`assets/ui/avatar_ring_${avatarRingTier}@2x.png`)}
                  className="absolute inset-0 w-full h-full"
                  alt=""
                />
                {avatarUrl ? (
                  <img
                    src={avatarUrl}
                    className="absolute inset-[6px] rounded-full w-[calc(100%-12px)] h-[calc(100%-12px)] object-cover"
                    alt={user?.first_name || 'Avatar'}
                  />
                ) : (
                  <div className="absolute inset-[6px] rounded-full w-[calc(100%-12px)] h-[calc(100%-12px)] bg-white/12 text-white font-bold text-sm flex items-center justify-center">
                    {avatarInitial}
                  </div>
                )}
              </div>
              <div className="min-w-0">
                <p className="text-white/50 text-[11px] uppercase tracking-wider">Привет</p>
                <h2 className="text-white font-bold text-base truncate">{user?.first_name || 'Игрок'}</h2>
              </div>
            </div>

            <div className="flex items-center gap-2 flex-wrap justify-end max-w-[58%]">
              <div
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-full overflow-hidden min-w-fit"
                style={{
                  backgroundImage: `url(${asset('assets/ui/pill_currency_bg@2x.png')})`,
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
                }}
              >
                <CoinIcon className="w-4 h-4" />
                <span className="text-slate-900 font-semibold text-sm">{profile?.coins ?? '...'}</span>
              </div>
              <div
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-full overflow-hidden min-w-fit"
                style={{
                  backgroundImage: `url(${asset('assets/ui/pill_currency_bg@2x.png')})`,
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
                }}
              >
                <span className="text-sm">💎</span>
                <span className="text-slate-900 font-semibold text-sm">{profile?.gems ?? '...'}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="relative z-10 px-5 pb-24 overflow-y-auto">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="relative rounded-[34px] border border-white/10 p-5 mb-4 overflow-hidden"
        >
          <div className="absolute inset-0" style={uiCover('hero_duel_card_bg@2x.png')} />
          <div className="absolute inset-0 pointer-events-none" style={uiCover('hero_duel_card_border@2x.png')} />

          <div className="relative z-10">
            <div className="text-center">
              <div className="inline-flex items-center gap-2 rounded-2xl bg-white text-slate-900 px-4 py-2 mb-4">
                <ModeDuelIcon className="w-5 h-5" />
                <h1 className="text-xl font-black leading-tight">Дуэль</h1>
              </div>
              <div className="flex justify-center mb-4">
                <div
                  className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 mr-2 overflow-hidden"
                  style={uiCover('pill_online_bg@2x.png')}
                >
                  <span className="relative flex h-2.5 w-2.5">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-400"></span>
                  </span>
                  <span className="text-white font-semibold text-sm">{onlineCount !== null ? onlineCount : '...'}</span>
                  <span className="text-white/70 text-xs">в сети</span>
                </div>
                <div
                  className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 overflow-hidden"
                  style={uiCover('pill_tickets_bg@2x.png')}
                >
                  <TicketIcon className="w-4 h-4" />
                  <span className="text-white font-semibold text-sm">{profile?.tickets ?? '...'}</span>
                  <span className="text-white/70 text-xs">билеты</span>
                </div>
              </div>
              {(profile?.tickets ?? 0) < 10 && (
                <p className="text-white/65 text-xs mb-4">
                  До восстановления билета: <span className="text-amber-200 font-semibold">{formatTicketTimer(ticketSecondsLeft)}</span>
                </p>
              )}
            </div>

            <div className="grid grid-cols-2 gap-2.5">
              <button
                onClick={handleQuickRandom}
                className="relative rounded-xl overflow-hidden py-4 text-white text-sm font-semibold"
              >
                <div className="absolute inset-0" style={uiCover('btn_quick_indigo@2x.png')} />
                <span className="relative z-10 inline-flex items-center gap-1.5">
                  <ModeDuelIcon className="w-4 h-4" />
                  Случайный
                </span>
              </button>
              <button
                onClick={handleQuickFriend}
                className="relative rounded-xl overflow-hidden py-4 text-white text-sm font-semibold"
              >
                <div className="absolute inset-0" style={uiCover('btn_quick_cyan@2x.png')} />
                <span className="relative z-10 inline-flex items-center gap-1.5">
                  <ReferralIcon className="w-4 h-4" />
                  С другом
                </span>
              </button>
            </div>
          </div>
        </motion.div>

        <div className="grid grid-cols-2 gap-3">
          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/tasks')}
            className="relative col-span-2 rounded-2xl p-4 overflow-hidden border border-white/10 text-left"
          >
            <div className="absolute inset-0" style={uiCover('card_violet_bg@2x.png')} />
            <div className="absolute inset-0 pointer-events-none" style={uiCover('card_violet_border@2x.png')} />
            <div className="relative z-10 flex items-center justify-between gap-4">
              <div className="min-w-0">
                <div className="text-white font-semibold text-sm mb-1">Задания и сезон</div>
                <div className="text-white/65 text-xs">
                  {liveOps?.summary?.available_claims
                    ? `Доступно наград: ${liveOps.summary.available_claims}`
                    : 'Ежедневные и еженедельные задания + сезонный прогресс'}
                </div>
              </div>
              <div className="shrink-0 text-right">
                <div className="text-violet-200 text-xs font-semibold">Открыть</div>
                <div className="text-white/65 text-[11px] mt-1">
                  LVL {Number(liveOps?.season?.level || 1)}
                </div>
              </div>
            </div>
          </motion.button>

          {incomingRematch && (
            <motion.div
              whileTap={{ scale: 0.98 }}
              className="relative col-span-2 rounded-2xl p-4 overflow-hidden border border-white/10"
            >
              <div className="absolute inset-0" style={uiCover('card_neutral_bg@2x.png')} />
              <div className="absolute inset-0 pointer-events-none" style={uiCover('card_neutral_border@2x.png')} />
              <div className="relative z-10">
                <div className="flex items-center justify-between gap-3 mb-2">
                  <div className="min-w-0">
                    <div className="text-white font-semibold text-sm mb-1">Входящий реванш</div>
                    <div className="text-white/70 text-xs truncate">
                      {incomingRematch?.initiator?.name || 'Соперник'} зовёт сыграть ещё раз
                    </div>
                  </div>
                  <div className="text-cyan-200 text-xs font-semibold whitespace-nowrap">
                    {Number.isFinite(incomingRematch?.expires_in) ? `${Math.max(0, Number(incomingRematch.expires_in))}с` : ''}
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2 mt-3">
                  <button
                    onClick={handleAcceptRematch}
                    disabled={rematchLoading}
                    className="relative py-2.5 rounded-xl overflow-hidden text-slate-900 font-bold disabled:opacity-60"
                  >
                    <div className="absolute inset-0" style={uiCover('btn_accept_emerald@2x.png')} />
                    <span className="relative z-10">Принять</span>
                  </button>
                  <button
                    onClick={handleDeclineRematch}
                    disabled={rematchLoading}
                    className="relative py-2.5 rounded-xl overflow-hidden text-white font-semibold disabled:opacity-60"
                  >
                    <div className="absolute inset-0 bg-red-500/10" />
                    <div className="absolute inset-0 pointer-events-none" style={uiCover('btn_outline_red@2x.png')} />
                    <span className="relative z-10">Отказаться</span>
                  </button>
                </div>
              </div>
            </motion.div>
          )}

          {notifications.length > 0 && (
            <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => navigate('/notifications')}
              className="relative col-span-2 rounded-2xl p-4 overflow-hidden border border-white/10 text-left"
            >
              <div className="absolute inset-0" style={uiCover('card_amber_bg@2x.png')} />
              <div className="absolute inset-0 pointer-events-none" style={uiCover('card_amber_border@2x.png')} />
              <div className="relative z-10 flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-white font-semibold text-sm mb-1">🔔 Новые уведомления ({notifications.length})</div>
                  <div className="text-white/65 text-xs truncate">
                    {notifications[0]?.title || 'Открой, чтобы посмотреть'}
                  </div>
                </div>
                <div className="text-amber-200 text-xs font-semibold whitespace-nowrap">Открыть</div>
              </div>
            </motion.button>
          )}

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/truefalse')}
            className="relative col-span-2 rounded-2xl p-4 overflow-hidden border border-white/10 text-left"
          >
            <div className="absolute inset-0" style={uiCover('card_cyan_bg@2x.png')} />
            <div className="absolute inset-0 pointer-events-none" style={uiCover('card_cyan_border@2x.png')} />
            <div className="relative z-10 flex items-start justify-between gap-4">
              <div className="min-w-0">
                <div className="flex items-center gap-2 mb-2">
                  <ModeTrueFalseIcon className="w-7 h-7" />
                  <div className="text-white font-semibold text-sm">Правда или ложь</div>
                </div>
                <div className="text-white/70 text-xs leading-relaxed">Быстрые факты, мгновенная проверка и серия без пауз</div>
              </div>
              <div className="shrink-0 text-right">
                <div className="text-[10px] uppercase tracking-wider text-white/45 mb-1">Рекорд</div>
                <div className="text-xl font-black text-cyan-200 leading-none">{profile?.true_false_record ?? 0}</div>
              </div>
            </div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/referral')}
            className="relative col-span-2 rounded-2xl p-4 overflow-hidden border border-white/10 text-left"
          >
            <div className="absolute inset-0" style={uiCover('card_emerald_bg@2x.png')} />
            <div className="absolute inset-0 pointer-events-none" style={uiCover('card_emerald_border@2x.png')} />
            <div className="relative z-10 flex items-center justify-between">
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <ReferralIcon className="w-5 h-5" />
                  <div className="text-white font-semibold text-sm">Приглашай друзей</div>
                </div>
                <div className="text-white/55 text-xs">Увеличивай награды и развивай аккаунт быстрее</div>
              </div>
              <div className="text-emerald-200 text-xs font-semibold whitespace-nowrap">Бонусы</div>
            </div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.98 }}
            onClick={() => navigate('/support')}
            className="relative col-span-2 rounded-2xl p-4 overflow-hidden border border-white/10 text-left"
          >
            <div className="absolute inset-0" style={uiCover('card_sky_bg@2x.png')} />
            <div className="absolute inset-0 pointer-events-none" style={uiCover('card_sky_border@2x.png')} />
            <div className="relative z-10 flex items-center justify-between">
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <div className="text-base">🛟</div>
                  <div className="text-white font-semibold text-sm">Поддержка</div>
                </div>
                <div className="text-white/55 text-xs">Сообщи об ошибке или оставь предложение</div>
              </div>
              <div className="text-sky-200 text-xs font-semibold whitespace-nowrap">Написать</div>
            </div>
          </motion.button>

          {isAdmin && (
            <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => navigate('/admin')}
              className="relative col-span-2 mt-1 rounded-2xl p-3 overflow-hidden border border-white/10 text-red-200 text-sm font-semibold"
            >
              <div className="absolute inset-0" style={uiCover('card_red_bg@2x.png')} />
              <div className="absolute inset-0 pointer-events-none" style={uiCover('card_red_border@2x.png')} />
              <div className="relative z-10">🛠 Админ панель</div>
            </motion.button>
          )}
        </div>

        {loading && (
          <div className="text-center text-white/40 text-xs mt-4">Обновляю данные...</div>
        )}
      </div>

      {showFriendModePicker && (
        <div className="fixed inset-0 z-50 bg-black/65 backdrop-blur-sm flex items-center justify-center p-5">
          <div className="relative w-full max-w-sm rounded-3xl border border-white/15 p-5 overflow-hidden">
            <div className="absolute inset-0" style={uiCover('modal_sheet_bg@2x.png')} />
            <div className="absolute inset-0 pointer-events-none" style={uiCover('modal_sheet_border@2x.png')} />

            <div className="relative z-10">
              <h3 className="text-white font-bold text-lg mb-1">С другом</h3>
              <p className="text-white/60 text-sm mb-4">Выбери, как начать приватную дуэль</p>

              <div className="space-y-2">
                <button
                  onClick={handleCreateFriendRoom}
                  className="relative w-full py-3 rounded-xl overflow-hidden text-slate-900 font-bold"
                >
                  <div className="absolute inset-0" style={uiCover('btn_primary_white@2x.png')} />
                  <span className="relative z-10">Создать комнату</span>
                </button>

                <div className="rounded-xl border border-white/15 p-3">
                  <p className="text-white/60 text-xs mb-2">Ввести код (5 цифр)</p>
                  <div className="relative rounded-lg overflow-hidden">
                    <div className="absolute inset-0" style={uiCover('input_code_bg@2x.png')} />
                    <div className="absolute inset-0 pointer-events-none" style={uiCover('input_code_border@2x.png')} />
                    <input
                      value={friendJoinCode}
                      onChange={(e) => setFriendJoinCode(e.target.value.replace(/\D+/g, '').slice(0, 5))}
                      placeholder="12345"
                      inputMode="numeric"
                      maxLength={5}
                      className="relative z-10 w-full bg-transparent py-2.5 text-center tracking-[0.25em] text-white font-mono font-bold outline-none"
                    />
                  </div>
                  <button
                    onClick={handleJoinFriendByCode}
                    disabled={!/^\d{5}$/.test(friendJoinCode)}
                    className="relative w-full mt-2 py-2.5 rounded-lg overflow-hidden text-white font-semibold disabled:opacity-50"
                  >
                    <div className="absolute inset-0 bg-cyan-500/15" />
                    <div className="absolute inset-0 pointer-events-none" style={uiCover('btn_outline_cyan@2x.png')} />
                    <span className="relative z-10">Войти по коду</span>
                  </button>
                </div>
              </div>

              <button
                onClick={() => setShowFriendModePicker(false)}
                className="relative w-full mt-3 py-2.5 rounded-xl overflow-hidden text-white/80 font-semibold"
              >
                <div className="absolute inset-0 bg-white/5" />
                <div className="absolute inset-0 pointer-events-none" style={uiCover('btn_outline_white@2x.png')} />
                <span className="relative z-10">Закрыть</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default HomePage
