import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, hapticFeedback } from '../hooks/useTelegram'
import api from '../api/client'
import AvatarWithFrame from '../components/AvatarWithFrame'
import CoinIcon from '../components/CoinIcon'
import TicketIcon from '../components/TicketIcon'
import ModeDuelIcon from '../components/ModeDuelIcon'
import ModeTrueFalseIcon from '../components/ModeTrueFalseIcon'
import ReferralIcon from '../components/ReferralIcon'
import { getNotificationItems, subscribeNotifications } from '../utils/notificationInbox'

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

  const formatCount = (value) => {
    const safe = Number(value || 0)
    if (!Number.isFinite(safe)) return '0'
    return safe.toLocaleString('ru-RU')
  }

  const getSeasonProgress = () => {
    const current = Number(liveOps?.season?.points_into_level || 0)
    const perLevel = Number(liveOps?.season?.points_per_level || 100)
    if (!Number.isFinite(current) || !Number.isFinite(perLevel) || perLevel <= 0) return 0
    return Math.max(0, Math.min(100, (current / perLevel) * 100))
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

  const handleCloseMiniApp = () => {
    hapticFeedback('light')
    if (webApp?.close) {
      webApp.close()
    }
  }

  const tickets = Number(profile?.tickets ?? 0)
  const seasonLevel = Number(liveOps?.season?.level || 1)
  const seasonProgress = getSeasonProgress()
  const trueFalseRecord = Number(profile?.true_false_record || 0)
  const availableClaims = Number(liveOps?.summary?.available_claims || 0)

  return (
    <div className="min-h-dvh bg-aurora bg-page-home relative flex flex-col overflow-hidden">
      <div className="noise-overlay" />
      <div className="home-classic-vignette" />

      <div className="relative z-10 px-4 pb-24 safe-top overflow-y-auto">
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          className="home-classic-topbar mt-3 p-3 mb-3"
        >
          <div className="flex items-center justify-between gap-2">
            <button
              onClick={handleCloseMiniApp}
              className="home-classic-icon-btn shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <div className="text-center min-w-0">
              <h1 className="text-[#fbe9c8] font-black text-[31px] leading-none tracking-wide">Битва знаний</h1>
              <p className="text-[#e8cfa6]/80 text-xs tracking-[0.2em] uppercase">мини-приложение</p>
            </div>
            <button
              onClick={() => navigate('/support')}
              className="home-classic-icon-btn shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2.2"/><circle cx="12" cy="12" r="2.2"/><circle cx="19" cy="12" r="2.2"/></svg>
            </button>
          </div>
        </motion.div>

        <motion.section
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.03 }}
          className="home-classic-panel p-3.5 mb-3"
        >
          <div className="home-classic-profile-strip p-3 mb-3">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-3 min-w-0">
                <AvatarWithFrame
                  photoUrl={user?.photo_url}
                  name={user?.first_name}
                  size={54}
                  frameKey={profile?.equipped_frame}
                />
                <div className="min-w-0">
                  <p className="text-[#efcf98]/80 text-[10px] uppercase tracking-[0.2em]">Привет</p>
                  <h2 className="text-[#fff4df] font-bold text-2xl leading-tight truncate">{user?.first_name || 'Игрок'}</h2>
                </div>
              </div>
              <div className="grid gap-2">
                <div className="home-classic-currency px-3 py-1.5 inline-flex items-center gap-1.5 justify-end">
                  <CoinIcon className="w-4 h-4" />
                  <span className="text-[#fff1d6] text-sm font-bold">{formatCount(profile?.coins ?? 0)}</span>
                </div>
                <div className="home-classic-currency px-3 py-1.5 inline-flex items-center gap-1.5 justify-end">
                  <span className="text-cyan-300 text-sm">💎</span>
                  <span className="text-[#dff3ff] text-sm font-bold">{formatCount(profile?.gems ?? 0)}</span>
                </div>
              </div>
            </div>
          </div>

          <div className="home-classic-duel-frame p-3">
            <div className="flex justify-center mb-3">
              <div className="home-classic-duel-title inline-flex items-center gap-2 px-6 py-2">
                <ModeDuelIcon className="w-5 h-5" />
                <span className="font-black text-2xl tracking-wide leading-none">ДУЭЛЬ</span>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2 mb-3">
              <div className="home-classic-pill py-2 px-3 inline-flex items-center justify-center gap-2">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]" />
                <span className="text-[13px] font-semibold">В сети: {onlineCount !== null ? formatCount(onlineCount) : '...'}</span>
              </div>
              <div className="home-classic-pill py-2 px-3 inline-flex items-center justify-center gap-1.5">
                <TicketIcon className="w-4 h-4" />
                <span className="text-[13px] font-semibold">{formatCount(tickets)} билетов</span>
              </div>
            </div>

            {tickets < 10 && (
              <p className="text-[#f3d4a7]/80 text-xs text-center mb-3">
                До восстановления билета: <span className="text-[#ffe3b2] font-semibold">{formatTicketTimer(ticketSecondsLeft)}</span>
              </p>
            )}

            <div className="grid grid-cols-2 gap-2.5">
              <button
                onClick={handlePlay}
                className="home-classic-action home-classic-action-random h-14 px-3 font-black text-lg tracking-wide active:scale-[0.98]"
              >
                <span className="inline-flex items-center gap-2">
                  <ModeDuelIcon className="w-5 h-5" />
                  СЛУЧАЙНЫЙ
                </span>
              </button>
              <button
                onClick={handleQuickFriend}
                className="home-classic-action home-classic-action-friend h-14 px-3 font-black text-lg tracking-wide active:scale-[0.98]"
              >
                <span className="inline-flex items-center gap-2">
                  <ReferralIcon className="w-5 h-5" />
                  С ДРУГОМ
                </span>
              </button>
            </div>
          </div>
        </motion.section>

        <div className="space-y-3">
          {incomingRematch && (
            <motion.div whileTap={{ scale: 0.985 }} className="home-classic-row home-classic-row-cool p-4">
              <div className="flex items-center justify-between gap-2 mb-2">
                <div>
                  <p className="text-[#f3ead8] text-base font-black uppercase tracking-wide">Входящий реванш</p>
                  <p className="text-[#d6dff8]/80 text-sm">{incomingRematch?.initiator?.name || 'Соперник'} зовёт сыграть ещё раз</p>
                </div>
                <div className="text-cyan-200 text-xs font-semibold whitespace-nowrap">
                  {Number.isFinite(incomingRematch?.expires_in) ? `${Math.max(0, Number(incomingRematch.expires_in))}с` : ''}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-2 mt-3">
                <button
                  onClick={handleAcceptRematch}
                  disabled={rematchLoading}
                  className="home-classic-chip-btn py-2 text-sm font-bold disabled:opacity-60"
                >
                  Принять
                </button>
                <button
                  onClick={handleDeclineRematch}
                  disabled={rematchLoading}
                  className="rounded-full border border-[#79abff]/45 text-[#d7e8ff] py-2 text-sm font-semibold disabled:opacity-60"
                >
                  Отказаться
                </button>
              </div>
            </motion.div>
          )}

          {notifications.length > 0 && (
            <motion.button
              whileTap={{ scale: 0.985 }}
              onClick={() => navigate('/notifications')}
              className="home-classic-row home-classic-row-cool p-4 text-left"
            >
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-[#f4efe1] font-black uppercase tracking-wide text-base mb-1">Уведомления ({notifications.length})</div>
                  <div className="text-[#d5e0ff]/80 text-sm truncate">{notifications[0]?.title || 'Открой, чтобы посмотреть'}</div>
                </div>
                <div className="home-classic-chip-btn px-4 py-2 text-xs font-semibold">Открыть</div>
              </div>
            </motion.button>
          )}

          <motion.button
            whileTap={{ scale: 0.985 }}
            onClick={() => navigate('/truefalse')}
            className="home-classic-row home-classic-row-cool p-4 text-left"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <ModeTrueFalseIcon className="w-7 h-7" />
                  <div className="text-[#f3ead7] text-base font-black uppercase tracking-wide">Правда или ложь</div>
                </div>
                <div className="text-[#d1defa]/85 text-sm leading-snug">Лови факты, отвечай на скорость и ставь рекорды</div>
              </div>
              <div className="shrink-0 text-right">
                <div className="text-[#b8c8e8]/75 text-[10px] uppercase tracking-[0.2em]">Рекорд</div>
                <div className="text-3xl font-black text-cyan-200 leading-none">{formatCount(trueFalseRecord)}</div>
              </div>
            </div>
            <div className="home-classic-progress home-classic-progress-blue mt-3">
              <span style={{ width: `${Math.min(100, Math.max(12, trueFalseRecord * 5))}%` }} />
            </div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.985 }}
            onClick={() => navigate('/tasks')}
            className="home-classic-row p-4 text-left"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-2xl">📜</span>
                  <div className="text-[#ffedcc] text-base font-black uppercase tracking-wide">Задания и сезон</div>
                </div>
                <div className="text-[#e9cfab]/90 text-sm">
                  {availableClaims > 0
                    ? `Доступно наград: ${formatCount(availableClaims)}`
                    : 'Ежедневные и еженедельные задания + сезонный прогресс'}
                </div>
              </div>
              <div className="shrink-0 text-right">
                <div className="text-[#fbe4bd] text-xs uppercase tracking-wider">Уровень {seasonLevel}</div>
                <div className="home-classic-chip-btn px-3 py-1.5 mt-2 text-[11px] font-semibold">Открыть</div>
              </div>
            </div>
            <div className="home-classic-progress mt-3">
              <span style={{ width: `${seasonProgress}%` }} />
            </div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.985 }}
            onClick={() => navigate('/referral')}
            className="home-classic-row home-classic-row-cool p-4 text-left"
          >
            <div className="flex items-center justify-between gap-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <ReferralIcon className="w-6 h-6" />
                  <div className="text-[#f2e9d6] text-base font-black uppercase tracking-wide">Приглашай друзей</div>
                </div>
                <div className="text-[#d1ddfb]/85 text-sm">Играйте вместе, получайте бонусы и прокачивайте аккаунт</div>
              </div>
              <div className="home-classic-chip-btn px-4 py-2 text-sm font-semibold whitespace-nowrap">Бонусы</div>
            </div>
          </motion.button>

          <motion.button
            whileTap={{ scale: 0.985 }}
            onClick={() => navigate('/support')}
            className="home-classic-row p-4 text-left"
          >
            <div className="flex items-center justify-between gap-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xl">🛟</span>
                  <div className="text-[#ffedcc] text-base font-black uppercase tracking-wide">Поддержка</div>
                </div>
                <div className="text-[#ebcfaa]/90 text-sm">Сообщи об ошибке или предложи идею</div>
              </div>
              <div className="home-classic-chip-btn px-4 py-2 text-sm font-semibold whitespace-nowrap">Написать</div>
            </div>
          </motion.button>

          {isAdmin && (
            <motion.button
              whileTap={{ scale: 0.985 }}
              onClick={() => navigate('/admin')}
              className="home-classic-row p-3 text-red-200 text-sm font-semibold text-left"
            >
              🛠 Админ панель
            </motion.button>
          )}
        </div>

        {loading && (
          <div className="text-center text-[#f0d7b2]/55 text-xs mt-4">Обновляю данные...</div>
        )}
      </div>

      {showFriendModePicker && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-5">
          <div className="w-full max-w-sm home-classic-panel p-5">
            <h3 className="text-[#fff0d6] font-black text-xl mb-1 uppercase tracking-wide">С другом</h3>
            <p className="text-[#e8cb9f]/80 text-sm mb-4">Выбери, как начать приватную дуэль</p>

            <div className="space-y-2">
              <button
                onClick={handleCreateFriendRoom}
                className="w-full py-3 rounded-xl home-classic-action home-classic-action-random font-bold"
              >
                Создать комнату
              </button>

              <div className="rounded-xl border border-amber-200/30 bg-black/20 p-3">
                <p className="text-[#e8cb9f]/80 text-xs mb-2">Ввести код (5 цифр)</p>
                <input
                  value={friendJoinCode}
                  onChange={(e) => setFriendJoinCode(e.target.value.replace(/\D+/g, '').slice(0, 5))}
                  placeholder="12345"
                  inputMode="numeric"
                  maxLength={5}
                  className="w-full bg-black/25 border border-amber-200/20 rounded-lg py-2.5 text-center tracking-[0.25em] text-[#fff0d0] font-mono font-bold outline-none"
                />
                <button
                  onClick={handleJoinFriendByCode}
                  disabled={!/^\d{5}$/.test(friendJoinCode)}
                  className="w-full mt-2 py-2.5 rounded-lg home-classic-chip-btn font-semibold disabled:opacity-50"
                >
                  Войти по коду
                </button>
              </div>
            </div>

            <button
              onClick={() => setShowFriendModePicker(false)}
              className="w-full mt-3 py-2.5 rounded-xl text-[#efd7b0]/80 border border-amber-200/20"
            >
              Закрыть
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default HomePage
