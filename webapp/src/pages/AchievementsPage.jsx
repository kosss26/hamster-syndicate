import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../api/client';
import CoinIcon from '../components/CoinIcon';

const rarityColors = {
  common: {
    bg: 'from-gray-500/10 to-gray-600/5',
    border: 'border-gray-500/20',
    text: 'text-gray-400',
    glow: 'shadow-none'
  },
  rare: {
    bg: 'from-blue-500/20 to-blue-600/10',
    border: 'border-blue-500/30',
    text: 'text-blue-400',
    glow: 'shadow-[0_0_15px_rgba(59,130,246,0.15)]'
  },
  epic: {
    bg: 'from-purple-500/20 to-purple-600/10',
    border: 'border-purple-500/30',
    text: 'text-purple-400',
    glow: 'shadow-[0_0_15px_rgba(168,85,247,0.15)]'
  },
  legendary: {
    bg: 'from-amber-500/20 to-orange-500/10',
    border: 'border-amber-500/30',
    text: 'text-amber-400',
    glow: 'shadow-[0_0_15px_rgba(245,158,11,0.15)]'
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
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.95 }}
      whileTap={{ scale: 0.98 }}
      className={`relative overflow-hidden rounded-2xl bg-gradient-to-br ${rarity.bg} 
        border ${rarity.border} backdrop-blur-md ${isCompleted ? rarity.glow : ''} transition-all`}
    >
      {/* Shimmer for completed */}
      {isCompleted && (
        <motion.div
          className="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent"
          animate={{ x: ['-100%', '200%'] }}
          transition={{ duration: 3, repeat: Infinity, repeatDelay: 5, ease: "linear" }}
        />
      )}

      <div className="relative p-4">
        {/* Header */}
        <div className="flex items-start gap-3 mb-3">
          <div className={`text-3xl p-2 rounded-xl bg-white/5 ${isCompleted ? '' : 'opacity-50 grayscale'}`}>
            {achievement.icon}
          </div>
          
          <div className="flex-1 min-w-0">
            <h3 className={`font-bold text-sm leading-tight mb-1 truncate ${isCompleted ? 'text-white' : 'text-gray-400'}`}>
              {achievement.title}
            </h3>
            <p className="text-xs text-gray-500 line-clamp-2 leading-relaxed">
              {achievement.description}
            </p>
          </div>
        </div>

        {/* Progress */}
        {!isCompleted && achievement.current_value !== undefined && achievement.condition_value > 1 && (
          <div className="mb-3">
            <div className="flex justify-between text-[10px] text-gray-500 mb-1 font-medium">
              <span>Прогресс</span>
              <span>{achievement.current_value}/{achievement.condition_value}</span>
            </div>
            <div className="h-1.5 bg-black/20 rounded-full overflow-hidden border border-white/5">
              <motion.div
                className={`h-full bg-gradient-to-r ${rarity.bg.replace('/20', '/80').replace('/10', '/60')}`}
                initial={{ width: 0 }}
                animate={{ width: `${progress}%` }}
                transition={{ duration: 1, ease: "circOut" }}
              />
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="flex items-center justify-between pt-3 border-t border-white/5">
          <div className="flex items-center gap-2">
            <span className={`text-[10px] font-bold uppercase tracking-wider ${rarity.text}`}>
              {rarityNames[achievement.rarity]}
            </span>
          </div>

          <div className="flex items-center gap-2">
            {achievement.reward_coins > 0 && (
              <div className="flex items-center gap-1 text-xs bg-black/20 rounded-full px-2 py-0.5 border border-white/5">
                <CoinIcon size={12} />
                <span className="text-yellow-400 font-bold">{achievement.reward_coins}</span>
              </div>
            )}
            {achievement.reward_gems > 0 && (
              <div className="flex items-center gap-1 text-xs bg-black/20 rounded-full px-2 py-0.5 border border-white/5">
                <span className="text-xs">💎</span>
                <span className="text-purple-400 font-bold">{achievement.reward_gems}</span>
              </div>
            )}
          </div>
        </div>

        {/* Status Badge */}
        {isCompleted && (
          <div className="absolute top-0 right-0">
            <div className="bg-gradient-to-bl from-green-500/20 to-transparent p-2 pl-3 pb-3 rounded-bl-2xl">
              <span className="text-xs">✅</span>
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

  const filteredAchievements = achievements.filter(ach => {
    if (selectedCategory !== 'all' && ach.category !== selectedCategory) return false;
    if (selectedRarity !== 'all' && ach.rarity !== selectedRarity) return false;
    if (selectedStatus === 'completed' && !ach.is_completed) return false;
    if (selectedStatus === 'in_progress' && ach.is_completed) return false;
    return true;
  });

  const categoryCounts = ['all', ...Object.keys(categoryNames)].reduce((acc, cat) => {
    acc[cat] = cat === 'all' 
      ? achievements.length 
      : achievements.filter(a => a.category === cat).length;
    return acc;
  }, {});

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="text-center">
          <div className="spinner mx-auto mb-4" />
          <div className="text-white/40 text-sm font-mono">LOADING_ACHIEVEMENTS...</div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col pb-24">
      <div className="aurora-blob aurora-blob-1 opacity-30" />
      <div className="aurora-blob aurora-blob-2 opacity-30" />
      <div className="noise-overlay" />

      {/* Header */}
      <div className="relative z-10 px-4 pt-4 pb-2 bg-black/20 backdrop-blur-xl border-b border-white/5 sticky top-0">
        <div className="flex items-center justify-between mb-4">
          <button
            onClick={() => navigate(-1)}
            className="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-colors"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <h1 className="text-lg font-bold text-white">Достижения</h1>
          <button
            onClick={() => navigate('/achievements/showcase')}
            className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/30 flex items-center justify-center text-lg"
          >
            ⭐
          </button>
        </div>

        {/* Stats Cards */}
        {stats && (
          <div className="grid grid-cols-2 gap-3 mb-2">
            <div className="bg-white/5 rounded-2xl p-3 border border-white/5">
              <div className="flex items-end gap-2">
                <span className="text-2xl font-bold text-white leading-none">{stats.completed}</span>
                <span className="text-xs text-white/40 mb-1">/ {stats.total}</span>
              </div>
              <div className="text-[10px] text-white/40 uppercase tracking-wider font-bold mt-1">Получено</div>
            </div>
            <div className="bg-white/5 rounded-2xl p-3 border border-white/5 relative overflow-hidden">
              <div className="relative z-10">
                <span className="text-2xl font-bold text-gradient-primary leading-none">{stats.completion_percent}%</span>
                <div className="text-[10px] text-white/40 uppercase tracking-wider font-bold mt-1">Прогресс</div>
              </div>
              {/* Progress BG */}
              <div className="absolute bottom-0 left-0 h-1 bg-game-primary/50 w-full">
                <div className="h-full bg-game-primary" style={{ width: `${stats.completion_percent}%` }} />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Filters */}
      <div className="relative z-10 px-4 py-4 space-y-3">
        {/* Categories */}
        <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-hide -mx-4 px-4">
          <button
            onClick={() => setSelectedCategory('all')}
            className={`px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-all ${
              selectedCategory === 'all'
                ? 'bg-white text-black shadow-glow'
                : 'bg-white/5 text-white/60 border border-white/5'
            }`}
          >
            Все ({categoryCounts.all})
          </button>
          {Object.entries(categoryNames).map(([key, name]) => (
            <button
              key={key}
              onClick={() => setSelectedCategory(key)}
              className={`px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-all ${
                selectedCategory === key
                  ? 'bg-white text-black shadow-glow'
                  : 'bg-white/5 text-white/60 border border-white/5'
              }`}
            >
              {name} ({categoryCounts[key] || 0})
            </button>
          ))}
        </div>

        {/* Dropdowns */}
        <div className="flex gap-2">
          <select
            value={selectedRarity}
            onChange={(e) => setSelectedRarity(e.target.value)}
            className="flex-1 px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-medium text-white/80 focus:outline-none focus:border-white/20"
          >
            <option value="all">Все редкости</option>
            {Object.entries(rarityNames).map(([key, name]) => (
              <option key={key} value={key}>{name}</option>
            ))}
          </select>

          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="flex-1 px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-medium text-white/80 focus:outline-none focus:border-white/20"
          >
            <option value="all">Любой статус</option>
            <option value="completed">Полученные</option>
            <option value="in_progress">В процессе</option>
          </select>
        </div>
      </div>

      {/* Grid */}
      <div className="relative z-10 px-4 pb-4 flex-1">
        {filteredAchievements.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-48 text-center">
            <div className="text-4xl mb-3 opacity-30">🔍</div>
            <p className="text-white/40 text-sm">Ничего не найдено</p>
          </div>
        ) : (
          <motion.div layout className="grid grid-cols-1 gap-3">
            <AnimatePresence mode="popLayout">
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
