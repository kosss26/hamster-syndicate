import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { showBackButton } from '../hooks/useTelegram'
import api from '../api/client'

const TOPICS = [
  { value: 'general', label: 'Общий вопрос' },
  { value: 'bug', label: 'Ошибка' },
  { value: 'idea', label: 'Пожелание' },
  { value: 'payment', label: 'Покупки' },
]

function SupportPage() {
  const [topic, setTopic] = useState('general')
  const [message, setMessage] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  useEffect(() => {
    showBackButton(true)
  }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setSuccess('')

    const trimmed = message.trim()
    if (trimmed.length < 5) {
      setError('Введите сообщение минимум из 5 символов')
      return
    }

    setSending(true)
    try {
      const response = await api.sendSupportMessage(trimmed, topic)
      if (!response.success) {
        throw new Error(response.error || 'Не удалось отправить сообщение')
      }
      setMessage('')
      setSuccess(response.data?.message || 'Сообщение отправлено')
    } catch (err) {
      setError(err.message || 'Ошибка отправки сообщения')
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="aurora-blob aurora-blob-1 opacity-60" />
      <div className="aurora-blob aurora-blob-3 opacity-60" />
      <div className="noise-overlay" />

      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.2 }}
        className="relative z-10 p-5"
      >
        <section className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-5 mb-4">
          <h1 className="text-2xl font-black text-white tracking-tight mb-2">Поддержка</h1>
          <p className="text-sm text-white/60">Сообщите о баге или оставьте пожелание, мы передадим это администратору.</p>
        </section>

        <form onSubmit={handleSubmit} className="rounded-3xl border border-white/10 bg-black/25 backdrop-blur-xl p-5 space-y-4">
          <div>
            <label className="block text-xs uppercase tracking-wider text-white/50 mb-2">Тема</label>
            <select
              value={topic}
              onChange={(e) => setTopic(e.target.value)}
              className="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-cyan-300/45"
            >
              {TOPICS.map((item) => (
                <option key={item.value} value={item.value} className="bg-slate-900 text-white">
                  {item.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs uppercase tracking-wider text-white/50 mb-2">Сообщение</label>
            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Опишите проблему или идею"
              maxLength={2000}
              rows={7}
              className="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-3 text-white placeholder:text-white/35 outline-none focus:border-cyan-300/45 resize-none"
            />
            <div className="mt-1 text-right text-xs text-white/40">{message.length}/2000</div>
          </div>

          {error && <p className="text-sm text-red-300">{error}</p>}
          {success && <p className="text-sm text-emerald-300">{success}</p>}

          <button
            type="submit"
            disabled={sending}
            className="w-full rounded-2xl border border-cyan-300/45 bg-cyan-500/20 py-3 text-white font-semibold active:scale-[0.99] transition-transform disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {sending ? 'Отправка...' : 'Отправить'}
          </button>
        </form>
      </motion.div>
    </div>
  )
}

export default SupportPage
