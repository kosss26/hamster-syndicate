import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../api/client';
import CoinIcon from '../components/CoinIcon';

const rarityColors = {
  common: {
    bg: 'from-gray-500/20 to-gray-600/10',
    border: 'border-gray-500/30',
    text: 'text-gray-400',
    glow: 'shadow-glow-gray'
  },
  rare: {
    bg: 'from-blue-500/20 to-blue-600/10',
    border: 'border-blue-500/30',
    text: 'text-blue-400',
    glow: 'shadow-glow-blue'
  },
  epic: {
    bg: 'from-purple-500/20 to-purple-600/10',
    border: 'border-purple-500/30',
    text: 'text-purple-400',
    glow: 'shadow-glow-epic'
  },
  legendary: {
    bg: 'from-orange-500/20 to-yellow-500/10',
    border: 'border-orange-400/30',
    text: 'text-orange-400',
    glow: 'shadow-glow-legendary'
  }
};

const rarityNames = {
  common: 'Обычное',
  rare: 'Редкое',
  epic: 'Эпическое',
  legendary: 'Легендарное'
};

const categoryNames = {
  duel: 'Дуэли',
  quiz: 'Викторина',
  shop: 'Магазин',
  social: 'Социальные',
  special: 'Особые'
};

function AchievementCard({ achievement }) {
  const rarity = rarityColors[achievement.rarity] || rarityColors.common;
  const isCompleted = achievement.is_completed;
  const progress = achievement.progress || 0;

  return (
    <motion.div
      layout
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.9 }}
      className={`relative overflow-hidden rounded-2xl bg-gradient-to-br ${rarity.bg} 
        border ${rarity.border} backdrop-blur-sm ${isCompleted ? rarity.glow : ''}`}
    >
      {/* Блики для завершённых */}
      {isCompleted && (
        <motion.div
          className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent"
          animate={{ x: ['-100%', '200%'] }}
          transition={{ duration: 2, repeat: Infinity, repeatDelay: 3 }}
        />
      )}

      <div className="relative p-4">
        {/* Иконка и заголовок */}
        <div className="flex items-start gap-3 mb-3">
          <div className={`text-4xl ${isCompleted ? '' : 'opacity-50 grayscale'}`}>
            {achievement.icon}
          </div>
          
          <div className="flex-1">
            <h3 className={`font-bold text-lg mb-1 ${isCompleted ? 'text-white' : 'text-gray-400'}`}>
              {achievement.title}
            </h3>
            <p className="text-sm text-gray-400 leading-snug">
              {achievement.description}
            </p>
          </div>
        </div>

        {/* Прогресс */}
        {!isCompleted && achievement.current_value !== undefined && achievement.condition_value > 1 && (
          <div className="mb-3">
            <div className="flex justify-between text-xs text-gray-400 mb-1">
              <span>Прогресс</span>
              <span>{achievement.current_value}/{achievement.condition_value}</span>
            </div>
            <div className="h-1.5 bg-gray-700/50 rounded-full overflow-hidden">
              <motion.div
                className={`h-full bg-gradient-to-r ${rarity.bg.replace('/20', '/60').replace('/10', '/40')}`}
                initial={{ width: 0 }}
                animate={{ width: `${progress}%` }}
                transition={{ duration: 0.5 }}
              />
            </div>
          </div>
        )}

        {/* Футер: редкость и награды */}
        <div className="flex items-center justify-between pt-3 border-t border-white/5">
          <div className="flex items-center gap-2">
            <span className={`text-xs font-medium ${rarity.text}`}>
              {rarityNames[achievement.rarity]}
            </span>
            <span className="text-xs text-gray-500">•</span>
            <span className="text-xs text-gray-500">
              {categoryNames[achievement.category] || achievement.category}
            </span>
          </div>

          <div className="flex items-center gap-2">
            {achievement.reward_coins > 0 && (
              <div className="flex items-center gap-1 text-xs">
                <CoinIcon size={14} />
                <span className="text-yellow-400 font-medium">{achievement.reward_coins}</span>
              </div>
            )}
            {achievement.reward_gems > 0 && (
              <div className="flex items-center gap-1 text-xs">
                <span className="text-lg">💎</span>
                <span className="text-purple-400 font-medium">{achievement.reward_gems}</span>
              </div>
            )}
          </div>
        </div>

        {/* Статус завершения */}
        {isCompleted && achievement.completed_at && (
          <div className="absolute top-3 right-3">
            <div className="bg-green-500/20 border border-green-500/30 rounded-full px-2 py-0.5 flex items-center gap-1">
              <span className="text-xs">✓</span>
              <span className="text-xs text-green-400">Получено</span>
            </div>
          </div>
        )}
      </div>
    </motion.div>
  );
}

export default function AchievementsPage() {
  const navigate = useNavigate();
  const [achievements, setAchievements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState(null);
  
  // Фильтры
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [selectedRarity, setSelectedRarity] = useState('all');
  const [selectedStatus, setSelectedStatus] = useState('all');

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const [achievementsRes, statsRes] = await Promise.all([
        api.getMyAchievements(),
        api.getAchievementStats()
      ]);
      
      setAchievements(achievementsRes.data.achievements || []);
      setStats(statsRes.data || {});
    } catch (error) {
      console.error('Ошибка загрузки достижений:', error);
    } finally {
      setLoading(false);
    }
  };

  // Фильтрация
  const filteredAchievements = achievements.filter(ach => {
    if (selectedCategory !== 'all' && ach.category !== selectedCategory) return false;
    if (selectedRarity !== 'all' && ach.rarity !== selectedRarity) return false;
    if (selectedStatus === 'completed' && !ach.is_completed) return false;
    if (selectedStatus === 'in_progress' && ach.is_completed) return false;
    return true;
  });

  // Подсчёт по категориям
  const categories = ['all', ...Object.keys(categoryNames)];
  const categoryCounts = categories.reduce((acc, cat) => {
    acc[cat] = cat === 'all' 
      ? achievements.length 
      : achievements.filter(a => a.category === cat).length;
    return acc;
  }, {});

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-game-dark via-game-dark/95 to-game-dark flex items-center justify-center">
        <div className="text-center">
          <div className="text-6xl mb-4 animate-bounce">🏆</div>
          <div className="text-gray-400">Загрузка достижений...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-game-dark via-game-dark/95 to-game-dark pb-20">
      {/* Хедер */}
      <div className="sticky top-0 z-10 bg-game-dark/80 backdrop-blur-md border-b border-white/10">
        <div className="px-4 py-4">
          <div className="flex items-center justify-between mb-4">
            <button
              onClick={() => navigate('/')}
              className="p-2 rounded-xl bg-white/5 hover:bg-white/10 transition-colors"
            >
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            
            <h1 className="text-2xl font-bold">🏆 Достижения</h1>
            
            <button
              onClick={() => navigate('/achievements/showcase')}
              className="p-2 rounded-xl bg-white/5 hover:bg-white/10 transition-colors"
            >
              <span className="text-xl">⭐</span>
            </button>
          </div>

          {/* Статистика */}
          {stats && (
            <div className="grid grid-cols-2 gap-2 mb-4">
              <div className="bg-gradient-to-br from-purple-500/20 to-purple-600/10 rounded-xl p-3 border border-purple-500/30">
                <div className="text-2xl font-bold text-white">{stats.completed}/{stats.total}</div>
                <div className="text-xs text-gray-400">Получено</div>
              </div>
              <div className="bg-gradient-to-br from-blue-500/20 to-blue-600/10 rounded-xl p-3 border border-blue-500/30">
                <div className="text-2xl font-bold text-white">{stats.completion_percent}%</div>
                <div className="text-xs text-gray-400">Прогресс</div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Фильтры */}
      <div className="px-4 py-4 space-y-3">
        {/* Категории */}
        <div>
          <h3 className="text-sm text-gray-400 mb-2">Категория</h3>
          <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
            <button
              onClick={() => setSelectedCategory('all')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
                selectedCategory === 'all'
                  ? 'bg-game-primary text-white'
                  : 'bg-white/5 text-gray-400 hover:bg-white/10'
              }`}
            >
              Все ({categoryCounts.all})
            </button>
            {Object.entries(categoryNames).map(([key, name]) => (
              <button
                key={key}
                onClick={() => setSelectedCategory(key)}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
                  selectedCategory === key
                    ? 'bg-game-primary text-white'
                    : 'bg-white/5 text-gray-400 hover:bg-white/10'
                }`}
              >
                {name} ({categoryCounts[key] || 0})
              </button>
            ))}
          </div>
        </div>

        {/* Редкость и статус */}
        <div className="flex gap-2">
          <select
            value={selectedRarity}
            onChange={(e) => setSelectedRarity(e.target.value)}
            className="flex-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm"
          >
            <option value="all">Все редкости</option>
            {Object.entries(rarityNames).map(([key, name]) => (
              <option key={key} value={key}>{name}</option>
            ))}
          </select>

          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="flex-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm"
          >
            <option value="all">Все</option>
            <option value="completed">Получено</option>
            <option value="in_progress">В процессе</option>
          </select>
        </div>
      </div>

      {/* Список достижений */}
      <div className="px-4 pb-4">
        {filteredAchievements.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-6xl mb-4 opacity-50">🏆</div>
            <div className="text-gray-400">Нет достижений по выбранным фильтрам</div>
          </div>
        ) : (
          <motion.div layout className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <AnimatePresence>
              {filteredAchievements.map((achievement) => (
                <AchievementCard key={achievement.id} achievement={achievement} />
              ))}
            </AnimatePresence>
          </motion.div>
        )}
      </div>
    </div>
  );
}

