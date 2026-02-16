import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../api/client'
import CoinIcon from '../components/CoinIcon'

const rarityNames = {
  common: 'Обычное',
  rare: 'Редкое',
  epic: 'Эпическое',
  legendary: 'Легендарное',
}

const rarityClasses = {
  common: 'border-white/10 bg-white/5',
  rare: 'border-blue-400/25 bg-blue-500/10',
  epic: 'border-violet-400/25 bg-violet-500/10',
  legendary: 'border-amber-400/25 bg-amber-500/10',
}

const categoryNames = {
  duel: 'Дуэли',
  quiz: 'Викторина',
  shop: 'Магазин',
  social: 'Социальные',
  special: 'Особые',
}

function AchievementCard({ achievement, manageMode, selected, onToggle }) {
  const rarityClass = rarityClasses[achievement.rarity] || rarityClasses.common
  const isCompleted = Boolean(achievement.is_completed)
  const progress = Number(achievement.progress || 0)

  return (
    <button
      type="button"
      disabled={!manageMode || !isCompleted}
      onClick={() => onToggle(achievement.id)}
      className={`w-full text-left rounded-2xl border p-4 transition-all ${rarityClass} ${
        manageMode && isCompleted ? 'active:scale-[0.99]' : ''
      } ${selected ? 'ring-2 ring-cyan-300/60' : ''} ${!isCompleted ? 'opacity-70' : ''}`}
    >
      <div className="flex items-start gap-3">
        <div className="w-11 h-11 rounded-xl bg-black/25 border border-white/10 flex items-center justify-center text-xl">
          {achievement.icon}
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <h3 className="text-sm font-semibold text-white truncate">{achievement.title}</h3>
            <div className="text-[10px] text-white/50 uppercase">{rarityNames[achievement.rarity] || rarityNames.common}</div>
          </div>
          <p className="text-xs text-white/60 mt-1">{achievement.description}</p>
          {!isCompleted && achievement.condition_value > 1 && (
            <div className="mt-2">
              <div className="flex items-center justify-between text-[10px] text-white/45 mb-1">
                <span>Прогресс</span>
                <span>{achievement.current_value || 0}/{achievement.condition_value}</span>
              </div>
              <div className="h-1.5 bg-black/30 rounded-full overflow-hidden">
                <div className="h-full bg-cyan-300" style={{ width: `${Math.max(0, Math.min(100, progress))}%` }} />
              </div>
            </div>
          )}
          <div className="mt-3 flex items-center justify-between">
            <div className="flex items-center gap-2">
              {achievement.reward_coins > 0 && (
                <div className="text-xs text-yellow-300 flex items-center gap-1">
                  <CoinIcon size={12} />
                  <span>{achievement.reward_coins}</span>
                </div>
              )}
              {achievement.reward_gems > 0 && (
                <div className="text-xs text-cyan-200">💎 {achievement.reward_gems}</div>
              )}
            </div>
            <div className={`text-xs ${isCompleted ? 'text-emerald-300' : 'text-white/45'}`}>
              {isCompleted ? 'Получено' : 'В процессе'}
            </div>
          </div>
        </div>
      </div>
    </button>
  )
}

export default function AchievementsPage() {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [achievements, setAchievements] = useState([])
  const [stats, setStats] = useState(null)
  const [selectedCategory, setSelectedCategory] = useState('all')
  const [selectedRarity, setSelectedRarity] = useState('all')
  const [selectedStatus, setSelectedStatus] = useState('all')
  const [manageMode, setManageMode] = useState(false)
  const [selectedShowcase, setSelectedShowcase] = useState([])

  const loadData = async () => {
    try {
      setLoading(true)
      setError(null)

      const [achievementsRes, statsRes, showcasedRes] = await Promise.all([
        api.getMyAchievements(),
        api.getAchievementStats(),
        api.getShowcasedAchievements(),
      ])

      setAchievements(achievementsRes?.data?.achievements || [])
      setStats(statsRes?.data || null)
      setSelectedShowcase((showcasedRes?.data?.showcased || []).map((item) => item.id))
    } catch (err) {
      setError(err.message || 'Не удалось загрузить достижения')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const filteredAchievements = useMemo(() => (
    achievements.filter((ach) => {
      if (selectedCategory !== 'all' && ach.category !== selectedCategory) return false
      if (selectedRarity !== 'all' && ach.rarity !== selectedRarity) return false
      if (selectedStatus === 'completed' && !ach.is_completed) return false
      if (selectedStatus === 'in_progress' && ach.is_completed) return false
      return true
    })
  ), [achievements, selectedCategory, selectedRarity, selectedStatus])

  const toggleShowcase = (achievementId) => {
    if (!manageMode) return

    setSelectedShowcase((prev) => {
      const hasId = prev.includes(achievementId)
      if (hasId) {
        return prev.filter((id) => id !== achievementId)
      }
      if (prev.length >= 5) {
        return prev
      }
      return [...prev, achievementId]
    })
  }

  const saveShowcase = async () => {
    try {
      setSaving(true)
      await api.setShowcasedAchievements(selectedShowcase)
      setManageMode(false)
      await loadData()
    } catch (err) {
      setError(err.message || 'Не удалось сохранить витрину')
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="text-center">
          <div className="spinner mx-auto mb-3" />
          <p className="text-white/45 text-sm">Загрузка достижений...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden pb-24">
      <div className="aurora-blob aurora-blob-1 opacity-30" />
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
            <h1 className="text-white font-bold">Достижения</h1>
            <button
              onClick={() => setManageMode((v) => !v)}
              className={`px-3 h-9 rounded-xl text-xs border ${manageMode ? 'border-cyan-300/40 bg-cyan-500/15 text-cyan-200' : 'border-white/10 bg-white/5 text-white/70'}`}
            >
              {manageMode ? 'Отмена' : 'Витрина'}
            </button>
          </div>

          <div className="grid grid-cols-2 gap-2">
            <div className="rounded-xl border border-white/10 bg-white/5 p-3">
              <p className="text-[10px] text-white/45 uppercase">Получено</p>
              <p className="text-white text-xl font-black mt-1">{stats?.completed || 0}<span className="text-sm text-white/45"> / {stats?.total || 0}</span></p>
            </div>
            <div className="rounded-xl border border-white/10 bg-white/5 p-3">
              <p className="text-[10px] text-white/45 uppercase">Прогресс</p>
              <p className="text-cyan-200 text-xl font-black mt-1">{stats?.completion_percent || 0}%</p>
            </div>
          </div>

          {manageMode && (
            <div className="mt-3 rounded-xl border border-cyan-300/25 bg-cyan-500/10 p-3">
              <p className="text-xs text-cyan-100">Выбери до 5 завершенных достижений для витрины профиля ({selectedShowcase.length}/5).</p>
              <button
                onClick={saveShowcase}
                disabled={saving}
                className="mt-2 w-full h-9 rounded-lg bg-cyan-300 text-slate-900 font-semibold text-sm disabled:opacity-60"
              >
                {saving ? 'Сохраняю...' : 'Сохранить витрину'}
              </button>
            </div>
          )}
        </div>

        {error && (
          <div className="rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-200">
            {error}
            <button onClick={loadData} className="block mt-2 underline">Повторить</button>
          </div>
        )}

        <div className="grid grid-cols-3 gap-2">
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            className="h-9 rounded-lg bg-white/5 border border-white/10 text-xs px-2 text-white/85"
          >
            <option value="all">Категория</option>
            {Object.entries(categoryNames).map(([key, name]) => <option key={key} value={key}>{name}</option>)}
          </select>
          <select
            value={selectedRarity}
            onChange={(e) => setSelectedRarity(e.target.value)}
            className="h-9 rounded-lg bg-white/5 border border-white/10 text-xs px-2 text-white/85"
          >
            <option value="all">Редкость</option>
            {Object.entries(rarityNames).map(([key, name]) => <option key={key} value={key}>{name}</option>)}
          </select>
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="h-9 rounded-lg bg-white/5 border border-white/10 text-xs px-2 text-white/85"
          >
            <option value="all">Статус</option>
            <option value="completed">Получено</option>
            <option value="in_progress">В процессе</option>
          </select>
        </div>

        <div className="space-y-2 pb-6">
          {filteredAchievements.length === 0 ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-white/45 text-sm">
              По фильтрам ничего не найдено
            </div>
          ) : (
            filteredAchievements.map((achievement) => (
              <AchievementCard
                key={achievement.id}
                achievement={achievement}
                manageMode={manageMode}
                selected={selectedShowcase.includes(achievement.id)}
                onToggle={toggleShowcase}
              />
            ))
          )}
        </div>
      </div>
    </div>
  )
}
