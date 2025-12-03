import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { showBackButton } from '../hooks/useTelegram'
import api from '../api/client'

function StatsPage() {
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    showBackButton(true)
    loadStats()
  }, [])

  const loadStats = async () => {
    try {
      setLoading(true)
      setError(null)
      
      const response = await api.getStatistics()
      
      if (response.success) {
        setStats(response.data)
      } else {
        setError(response.error || 'Не удалось загрузить статистику')
      }
    } catch (err) {
      console.error('Failed to load statistics:', err)
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <div className="w-12 h-12 border-4 border-game-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-telegram-hint">Загрузка статистики...</p>
        </div>
      </div>
    )
  }

  if (error || !stats) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <div className="text-center">
          <div className="text-4xl mb-4">📊</div>
          <p className="text-telegram-hint mb-4">{error || 'Статистика пока недоступна'}</p>
          <p className="text-sm text-telegram-hint/70 mb-4">Начните играть, чтобы собрать данные!</p>
          <button 
            onClick={loadStats}
            className="px-4 py-2 bg-game-primary rounded-lg"
          >
            Повторить
          </button>
        </div>
      </div>
    )
  }

  const { overview, strengths, weaknesses, best_day, best_hour, categories, activity } = stats

  return (
    <div className="min-h-screen p-4 pb-8">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center pt-2 mb-6"
      >
        <h1 className="text-2xl font-bold mb-1">📊 Статистика</h1>
        <p className="text-telegram-hint text-sm">Анализ твоих знаний</p>
      </motion.div>

      {/* Overview Card */}
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ delay: 0.1 }}
        className="glass rounded-2xl p-5 mb-4"
      >
        <h3 className="text-sm text-telegram-hint mb-4 flex items-center gap-2">
          <span>🎯</span> Общие показатели
        </h3>
        
        <div className="grid grid-cols-2 gap-4 mb-4">
          <div className="text-center p-3 bg-white/5 rounded-xl">
            <p className="text-3xl font-bold text-game-primary">{overview.accuracy}%</p>
            <p className="text-xs text-telegram-hint">Точность</p>
          </div>
          <div className="text-center p-3 bg-white/5 rounded-xl">
            <p className="text-3xl font-bold text-game-gold">{overview.average_time}с</p>
            <p className="text-xs text-telegram-hint">Ср. время</p>
          </div>
        </div>

        <div className="grid grid-cols-3 gap-2 text-center">
          <div>
            <p className="text-lg font-semibold">{overview.total_questions}</p>
            <p className="text-xs text-telegram-hint">Вопросов</p>
          </div>
          <div>
            <p className="text-lg font-semibold text-game-success">{overview.correct_answers}</p>
            <p className="text-xs text-telegram-hint">Верных</p>
          </div>
          <div>
            <p className="text-lg font-semibold text-game-warning">{overview.best_streak}</p>
            <p className="text-xs text-telegram-hint">Макс. серия</p>
          </div>
        </div>
      </motion.div>

      {/* Strengths & Weaknesses */}
      <div className="grid grid-cols-2 gap-3 mb-4">
        {/* Strengths */}
        <motion.div
          initial={{ opacity: 0, x: -20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.2 }}
          className="glass rounded-2xl p-4"
        >
          <h3 className="text-xs text-telegram-hint mb-3 flex items-center gap-1">
            <span>💪</span> Сильные стороны
          </h3>
          {strengths && strengths.length > 0 ? (
            <div className="space-y-2">
              {strengths.map((cat, index) => (
                <div key={cat.category_id} className="flex items-center justify-between">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="text-sm">{cat.category_icon}</span>
                    <span className="text-xs truncate">{cat.category_name}</span>
                  </div>
                  <span className="text-xs font-semibold text-game-success ml-1">{cat.accuracy}%</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-xs text-telegram-hint/70">Нужно больше данных</p>
          )}
        </motion.div>

        {/* Weaknesses */}
        <motion.div
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.25 }}
          className="glass rounded-2xl p-4"
        >
          <h3 className="text-xs text-telegram-hint mb-3 flex items-center gap-1">
            <span>📚</span> Тренировать
          </h3>
          {weaknesses && weaknesses.length > 0 ? (
            <div className="space-y-2">
              {weaknesses.map((cat, index) => (
                <div key={cat.category_id} className="flex items-center justify-between">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="text-sm">{cat.category_icon}</span>
                    <span className="text-xs truncate">{cat.category_name}</span>
                  </div>
                  <span className="text-xs font-semibold text-game-warning ml-1">{cat.accuracy}%</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-xs text-telegram-hint/70">Нужно больше данных</p>
          )}
        </motion.div>
      </div>

      {/* Best Time */}
      {(best_day || best_hour) && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="glass rounded-2xl p-4 mb-4"
        >
          <h3 className="text-sm text-telegram-hint mb-3 flex items-center gap-2">
            <span>⏰</span> Лучшее время для игры
          </h3>
          <div className="grid grid-cols-2 gap-4">
            {best_day && (
              <div className="flex items-center gap-3 p-3 bg-gradient-to-r from-game-primary/20 to-purple-500/20 rounded-xl">
                <div className="w-10 h-10 rounded-full bg-game-primary/30 flex items-center justify-center text-lg">
                  📅
                </div>
                <div>
                  <p className="font-semibold text-sm">{best_day.day_name}</p>
                  <p className="text-xs text-telegram-hint">+{Math.round(best_day.accuracy - overview.accuracy)}% к точности</p>
                </div>
              </div>
            )}
            {best_hour && (
              <div className="flex items-center gap-3 p-3 bg-gradient-to-r from-game-gold/20 to-orange-500/20 rounded-xl">
                <div className="w-10 h-10 rounded-full bg-game-gold/30 flex items-center justify-center text-lg">
                  🕐
                </div>
                <div>
                  <p className="font-semibold text-sm">{best_hour.hour_formatted}</p>
                  <p className="text-xs text-telegram-hint">{best_hour.accuracy}% точности</p>
                </div>
              </div>
            )}
          </div>
        </motion.div>
      )}

      {/* Categories Stats */}
      {categories && categories.length > 0 && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.35 }}
          className="glass rounded-2xl p-4 mb-4"
        >
          <h3 className="text-sm text-telegram-hint mb-4 flex items-center gap-2">
            <span>📂</span> По категориям
          </h3>
          <div className="space-y-3">
            {categories.map((cat) => (
              <div key={cat.category_id}>
                <div className="flex items-center justify-between mb-1">
                  <div className="flex items-center gap-2">
                    <span>{cat.category_icon}</span>
                    <span className="text-sm">{cat.category_name}</span>
                  </div>
                  <div className="flex items-center gap-3 text-xs">
                    <span className="text-telegram-hint">{cat.total} вопр.</span>
                    <span className={`font-semibold ${
                      cat.accuracy >= 70 ? 'text-game-success' : 
                      cat.accuracy >= 50 ? 'text-game-gold' : 'text-game-danger'
                    }`}>
                      {cat.accuracy}%
                    </span>
                  </div>
                </div>
                <div className="h-2 bg-white/10 rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${cat.accuracy}%` }}
                    transition={{ delay: 0.5, duration: 0.8 }}
                    className={`h-full rounded-full ${
                      cat.accuracy >= 70 ? 'bg-game-success' : 
                      cat.accuracy >= 50 ? 'bg-game-gold' : 'bg-game-danger'
                    }`}
                  />
                </div>
              </div>
            ))}
          </div>
        </motion.div>
      )}

      {/* Activity Chart */}
      {activity && Object.keys(activity).length > 0 && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="glass rounded-2xl p-4"
        >
          <h3 className="text-sm text-telegram-hint mb-4 flex items-center gap-2">
            <span>📈</span> Активность за 7 дней
          </h3>
          <div className="flex items-end justify-between gap-1 h-20">
            {Object.entries(activity).map(([date, count]) => {
              const maxCount = Math.max(...Object.values(activity), 1)
              const height = (count / maxCount) * 100
              const dayName = new Date(date).toLocaleDateString('ru-RU', { weekday: 'short' })
              
              return (
                <div key={date} className="flex flex-col items-center flex-1">
                  <motion.div
                    initial={{ height: 0 }}
                    animate={{ height: `${Math.max(height, 5)}%` }}
                    transition={{ delay: 0.6, duration: 0.5 }}
                    className={`w-full rounded-t-md ${
                      count > 0 ? 'bg-gradient-to-t from-game-primary to-purple-500' : 'bg-white/10'
                    }`}
                    style={{ minHeight: '4px' }}
                  />
                  <span className="text-[10px] text-telegram-hint mt-1">{dayName}</span>
                  {count > 0 && (
                    <span className="text-[9px] text-game-primary">{count}</span>
                  )}
                </div>
              )
            })}
          </div>
        </motion.div>
      )}

      {/* Duel Win Streak */}
      {overview.best_duel_win_streak > 0 && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.45 }}
          className="mt-4 p-4 bg-gradient-to-r from-game-primary/20 to-purple-500/20 rounded-2xl border border-game-primary/30"
        >
          <div className="flex items-center gap-4">
            <div className="text-4xl">🔥</div>
            <div>
              <p className="text-sm text-telegram-hint">Лучшая серия побед в дуэлях</p>
              <p className="text-2xl font-bold">{overview.best_duel_win_streak} подряд</p>
            </div>
          </div>
        </motion.div>
      )}
    </div>
  )
}

export default StatsPage

