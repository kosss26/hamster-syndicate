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
      ])
    } finally {
      setLoading(false)
    }
  }

  const loadProfile = async () => {
    try {
      const response = await api.getProfile()
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

  return (
    <div className="min-h-dvh bg-aurora bg-page-home relative flex flex-col overflow-hidden">
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="noise-overlay" />

      <div className="relative z-10 px-5 pt-5 safe-top">
        <div className="rounded-3xl border border-white/10 bg-black/20 backdrop-blur-xl p-4 mb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3 min-w-0">
              <AvatarWithFrame
                photoUrl={user?.photo_url}
                name={user?.first_name}
                size={46}
                frameKey={profile?.equipped_frame}
              />
              <div className="min-w-0">
                <p className="text-white/50 text-[11px] uppercase tracking-wider">Привет</p>
                <h2 className="text-white font-bold text-base truncate">{user?.first_name || 'Игрок'}</h2>
              </div>
            </div>

            <div className="flex items-center gap-2 flex-wrap justify-end max-w-[58%]">
              <div className="flex items-center gap-1.5 rounded-full border border-amber-300/30 bg-amber-500/10 px-3 py-1.5 min-w-fit">
                <CoinIcon className="w-4 h-4" />
                <span className="text-white font-semibold text-sm">{profile?.coins ?? '...'}</span>
              </div>
              <div className="flex items-center gap-1.5 rounded-full border border-cyan-300/30 bg-cyan-500/10 px-3 py-1.5 min-w-fit">
                <span className="text-sm">💎</span>
                <span className="text-white font-semibold text-sm">{profile?.gems ?? '...'}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="relative z-10 px-5 pb-24 overflow-y-auto">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-[34px] border border-cyan-300/20 bg-[radial-gradient(circle_at_15%_20%,rgba(56,189,248,0.22),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(16,185,129,0.22),transparent_40%),linear-gradient(165deg,rgba(15,23,42,0.78),rgba(3,7,18,0.9))] backdrop-blur-xl p-5 mb-4 overflow-hidden"
        >
          <div className="absolute -top-20 -right-20 w-52 h-52 rounded-full bg-cyan-400/25 blur-3xl" />
          <div className="absolute -bottom-24 -left-16 w-56 h-56 rounded-full bg-emerald-400/20 blur-3xl" />
          <div className="relative text-center">
            <div className="inline-flex items-center gap-2 rounded-2xl bg-white text-slate-900 px-4 py-2 mb-4">
              <ModeDuelIcon className="w-5 h-5" />
              <h1 className="text-xl font-black leading-tight">Дуэль</h1>
            </div>
            <div className="flex justify-center mb-4">
              <div className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/30 bg-emerald-500/10 px-3 py-1.5 mr-2">
                <span className="relative flex h-2.5 w-2.5">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-400"></span>
                </span>
                <span className="text-white font-semibold text-sm">{onlineCount !== null ? onlineCount : '...'}</span>
                <span className="text-white/60 text-xs">в сети</span>
              </div>
              <div className="inline-flex items-center gap-1.5 rounded-full border border-amber-300/30 bg-amber-500/10 px-3 py-1.5">
                <TicketIcon className="w-4 h-4" />
                <span className="text-white font-semibold text-sm">{profile?.tickets ?? '...'}</span>
                <span className="text-white/60 text-xs">билеты</span>
              </div>
            </div>
            {(profile?.tickets ?? 0) < 10 && (
              <p className="text-white/65 text-xs mb-4">
                До восстановления билета: <span className="text-amber-200 font-semibold">{formatTicketTimer(ticketSecondsLeft)}</span>
              </p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-2.5 relative">
            <button
              onClick={handleQuickRandom}
              className="rounded-xl border border-indigo-300/45 bg-indigo-500/25 text-white py-4 text-sm font-semibold"
            >
              🎲 Случайный
            </button>
            <button
              onClick={handleQuickFriend}
              className="rounded-xl border border-cyan-300/45 bg-cyan-500/25 text-white py-4 text-sm font-semibold"
            >
              <span className="inline-flex items-center gap-1.5">
                <ReferralIcon className="w-4 h-4" />
                С другом
              </span>
            </button>
          </div>
        </motion.div>

        <div className="grid grid-cols-2 gap-3">
          {incomingRematch && (
            <motion.div
              whileTap={{ scale: 0.98 }}
              className="col-span-2 rounded-2xl border border-cyan-300/30 bg-cyan-500/10 p-4"
            >
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
                  className="py-2.5 rounded-xl bg-emerald-400 text-slate-900 font-bold disabled:opacity-60"
                >
                  Принять
                </button>
                <button
                  onClick={handleDeclineRematch}
                  disabled={rematchLoading}
                  className="py-2.5 rounded-xl border border-white/20 text-white font-semibold disabled:opacity-60"
                >
                  Отказаться
                </button>
              </div>
            </motion.div>
          )}

          {notifications.length > 0 && (
            <motion.button
              whileTap={{ scale: 0.98 }}
              onClick={() => navigate('/notifications')}
              className="col-span-2 rounded-2xl border border-amber-300/25 bg-amber-500/10 p-4 text-left"
            >
              <div className="flex items-center justify-between gap-3">
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
            className="col-span-2 rounded-2xl border border-cyan-300/25 bg-cyan-500/10 p-4 text-left"
          >
            <div className="flex items-start justify-between gap-4">
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
            className="col-span-2 rounded-2xl border border-emerald-300/25 bg-emerald-500/10 p-4 text-left"
          >
            <div className="flex items-center justify-between">
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
            className="col-span-2 rounded-2xl border border-sky-300/25 bg-sky-500/10 p-4 text-left"
          >
            <div className="flex items-center justify-between">
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
              className="col-span-2 mt-1 rounded-2xl border border-red-400/30 bg-red-500/10 p-3 text-red-300 text-sm font-semibold"
            >
              🛠 Админ панель
            </motion.button>
          )}
        </div>

        {loading && (
          <div className="text-center text-white/40 text-xs mt-4">Обновляю данные...</div>
        )}
      </div>

      {showFriendModePicker && (
        <div className="fixed inset-0 z-50 bg-black/65 backdrop-blur-sm flex items-center justify-center p-5">
          <div className="w-full max-w-sm rounded-3xl border border-white/15 bg-slate-950/95 p-5">
            <h3 className="text-white font-bold text-lg mb-1">С другом</h3>
            <p className="text-white/60 text-sm mb-4">Выбери, как начать приватную дуэль</p>

            <div className="space-y-2">
              <button
                onClick={handleCreateFriendRoom}
                className="w-full py-3 rounded-xl bg-white text-slate-900 font-bold"
              >
                Создать комнату
              </button>

              <div className="rounded-xl border border-white/15 p-3">
                <p className="text-white/60 text-xs mb-2">Ввести код (5 цифр)</p>
                <input
                  value={friendJoinCode}
                  onChange={(e) => setFriendJoinCode(e.target.value.replace(/\D+/g, '').slice(0, 5))}
                  placeholder="12345"
                  inputMode="numeric"
                  maxLength={5}
                  className="w-full bg-white/5 border border-white/10 rounded-lg py-2.5 text-center tracking-[0.25em] text-white font-mono font-bold outline-none"
                />
                <button
                  onClick={handleJoinFriendByCode}
                  disabled={!/^\d{5}$/.test(friendJoinCode)}
                  className="w-full mt-2 py-2.5 rounded-lg border border-cyan-300/35 bg-cyan-500/15 text-white font-semibold disabled:opacity-50"
                >
                  Войти по коду
                </button>
              </div>
            </div>

            <button
              onClick={() => setShowFriendModePicker(false)}
              className="w-full mt-3 py-2.5 rounded-xl text-white/70 border border-white/10"
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
