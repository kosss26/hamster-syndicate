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
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <div className="spinner mx-auto mb-4" />
          <p className="text-white/40">Загрузка статистики...</p>
        </div>
      </div>
    )
  }

  if (error || !stats) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center p-4">
        <div className="aurora-blob aurora-blob-1" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            className="text-6xl mb-4"
          >
            📊
          </motion.div>
          <p className="text-white/50 mb-2">{error || 'Статистика пока недоступна'}</p>
          <p className="text-sm text-white/30 mb-6">Начните играть, чтобы собрать данные!</p>
          <button 
            onClick={loadStats}
            className="px-6 py-3 bg-gradient-to-r from-game-primary to-purple-600 rounded-xl font-medium shadow-glow"
          >
            Повторить
          </button>
        </div>
      </div>
    )
  }

  const { overview, strengths, weaknesses, best_day, best_hour, categories, activity } = stats

  return (
    <div className="min-h-screen bg-aurora relative overflow-hidden">
      <div className="aurora-blob aurora-blob-1" />
      <div className="aurora-blob aurora-blob-2" />
      <div className="aurora-blob aurora-blob-3" />
      <div className="noise-overlay" />

      <div className="relative z-10 p-4 pb-8">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center pt-2 mb-6"
        >
          <h1 className="text-3xl font-bold text-gradient-primary mb-1">📊 Статистика</h1>
          <p className="text-white/40 text-sm">Анализ твоих знаний</p>
        </motion.div>

        {/* Overview Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bento-card p-6 mb-4"
        >
          <div className="bento-glow bg-gradient-to-br from-game-primary/20 via-purple-500/10 to-transparent blur-2xl" />
          
          <h3 className="relative text-sm text-white/50 mb-4 flex items-center gap-2">
            <span>🎯</span> Общие показатели
          </h3>
          
          <div className="relative grid grid-cols-2 gap-4 mb-5">
            <div className="text-center p-4 bg-white/5 rounded-2xl">
              <p className="text-4xl font-bold text-gradient-primary">{overview.accuracy}%</p>
              <p className="text-xs text-white/40 mt-1">Точность</p>
            </div>
            <div className="text-center p-4 bg-white/5 rounded-2xl">
              <p className="text-4xl font-bold text-gradient-gold">{overview.average_time}с</p>
              <p className="text-xs text-white/40 mt-1">Ср. время</p>
            </div>
          </div>

          <div className="relative grid grid-cols-3 gap-3 text-center">
            <div>
              <p className="text-xl font-semibold text-white">{overview.total_questions}</p>
              <p className="text-2xs text-white/40">Вопросов</p>
            </div>
            <div>
              <p className="text-xl font-semibold text-game-success">{overview.correct_answers}</p>
              <p className="text-2xs text-white/40">Верных</p>
            </div>
            <div>
              <p className="text-xl font-semibold text-game-warning">{overview.best_streak}</p>
              <p className="text-2xs text-white/40">Макс. серия</p>
            </div>
          </div>
        </motion.div>

        {/* Strengths & Weaknesses */}
        <div className="grid grid-cols-2 gap-3 mb-4">
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.2 }}
            className="glass rounded-3xl p-4"
          >
            <h3 className="text-xs text-white/50 mb-3 flex items-center gap-1">
              <span>💪</span> Сильные стороны
            </h3>
            {strengths && strengths.length > 0 ? (
              <div className="space-y-2">
                {strengths.map((cat) => (
                  <div key={cat.category_id} className="flex items-center justify-between">
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm">{cat.category_icon}</span>
                      <span className="text-xs truncate text-white/70">{cat.category_name}</span>
                    </div>
                    <span className="text-xs font-semibold text-game-success ml-1">{cat.accuracy}%</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-white/30">Нужно больше данных</p>
            )}
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.25 }}
            className="glass rounded-3xl p-4"
          >
            <h3 className="text-xs text-white/50 mb-3 flex items-center gap-1">
              <span>📚</span> Тренировать
            </h3>
            {weaknesses && weaknesses.length > 0 ? (
              <div className="space-y-2">
                {weaknesses.map((cat) => (
                  <div key={cat.category_id} className="flex items-center justify-between">
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm">{cat.category_icon}</span>
                      <span className="text-xs truncate text-white/70">{cat.category_name}</span>
                    </div>
                    <span className="text-xs font-semibold text-game-warning ml-1">{cat.accuracy}%</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-white/30">Нужно больше данных</p>
            )}
          </motion.div>
        </div>

        {/* Best Time */}
        {(best_day || best_hour) && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="glass rounded-3xl p-5 mb-4"
          >
            <h3 className="text-sm text-white/50 mb-4 flex items-center gap-2">
              <span>⏰</span> Лучшее время для игры
            </h3>
            <div className="grid grid-cols-2 gap-4">
              {best_day && (
                <div className="flex items-center gap-3 p-4 bg-gradient-to-r from-game-primary/10 to-purple-500/5 rounded-2xl">
                  <div className="w-12 h-12 rounded-xl bg-game-primary/20 flex items-center justify-center text-xl">
                    📅
                  </div>
                  <div>
                    <p className="font-semibold text-white">{best_day.day_name}</p>
                    <p className="text-xs text-white/40">+{Math.round(best_day.accuracy - overview.accuracy)}% к точности</p>
                  </div>
                </div>
              )}
              {best_hour && (
                <div className="flex items-center gap-3 p-4 bg-gradient-to-r from-game-gold/10 to-orange-500/5 rounded-2xl">
                  <div className="w-12 h-12 rounded-xl bg-game-gold/20 flex items-center justify-center text-xl">
                    🕐
                  </div>
                  <div>
                    <p className="font-semibold text-white">{best_hour.hour_formatted}</p>
                    <p className="text-xs text-white/40">{best_hour.accuracy}% точности</p>
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
            className="glass rounded-3xl p-5 mb-4"
          >
            <h3 className="text-sm text-white/50 mb-4 flex items-center gap-2">
              <span>📂</span> По категориям
            </h3>
            <div className="space-y-4">
              {categories.map((cat, index) => (
                <div key={cat.category_id}>
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <span>{cat.category_icon}</span>
                      <span className="text-sm text-white/80">{cat.category_name}</span>
                    </div>
                    <div className="flex items-center gap-3 text-xs">
                      <span className="text-white/40">{cat.total} вопр.</span>
                      <span className={`font-bold ${
                        cat.accuracy >= 70 ? 'text-game-success' : 
                        cat.accuracy >= 50 ? 'text-game-gold' : 'text-game-danger'
                      }`}>
                        {cat.accuracy}%
                      </span>
                    </div>
                  </div>
                  <div className="h-2 bg-white/5 rounded-full overflow-hidden">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${cat.accuracy}%` }}
                      transition={{ delay: 0.5 + index * 0.1, duration: 0.8 }}
                      className={`h-full rounded-full ${
                        cat.accuracy >= 70 ? 'bg-gradient-to-r from-game-success to-emerald-400 shadow-glow-success' : 
                        cat.accuracy >= 50 ? 'bg-gradient-to-r from-game-gold to-amber-400 shadow-glow-warning' : 
                        'bg-gradient-to-r from-game-danger to-red-400 shadow-glow-danger'
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
            className="glass rounded-3xl p-5 mb-4"
          >
            <h3 className="text-sm text-white/50 mb-4 flex items-center gap-2">
              <span>📈</span> Активность за 7 дней
            </h3>
            <div className="flex items-end justify-between gap-2 h-24">
              {Object.entries(activity).map(([date, count], index) => {
                const maxCount = Math.max(...Object.values(activity), 1)
                const height = (count / maxCount) * 100
                const dayName = new Date(date).toLocaleDateString('ru-RU', { weekday: 'short' })
                
                return (
                  <div key={date} className="flex flex-col items-center flex-1">
                    <motion.div
                      initial={{ height: 0 }}
                      animate={{ height: `${Math.max(height, 5)}%` }}
                      transition={{ delay: 0.6 + index * 0.05, duration: 0.5 }}
                      className={`w-full rounded-t-lg ${
                        count > 0 
                          ? 'bg-gradient-to-t from-game-primary to-purple-400 shadow-glow' 
                          : 'bg-white/10'
                      }`}
                      style={{ minHeight: '4px' }}
                    />
                    <span className="text-2xs text-white/40 mt-2">{dayName}</span>
                    {count > 0 && (
                      <span className="text-2xs text-game-primary font-medium">{count}</span>
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
            className="bento-card p-5"
          >
            <div className="bento-glow bg-gradient-to-br from-game-primary/20 via-purple-500/10 to-transparent blur-2xl" />
            
            <div className="relative flex items-center gap-4">
              <motion.div 
                className="text-5xl"
                animate={{ scale: [1, 1.1, 1] }}
                transition={{ duration: 1.5, repeat: Infinity }}
              >
                🔥
              </motion.div>
              <div>
                <p className="text-sm text-white/50">Лучшая серия побед в дуэлях</p>
                <p className="text-3xl font-bold text-gradient-primary">{overview.best_duel_win_streak} подряд</p>
              </div>
            </div>
          </motion.div>
        )}
      </div>
    </div>
  )
}

export default StatsPage
