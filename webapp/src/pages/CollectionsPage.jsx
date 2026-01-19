import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import api from '../api/client';
import CoinIcon from '../components/CoinIcon';

function CollectionCard({ collection, onClick }) {
  const isCompleted = collection.is_completed;
  const progress = collection.progress_percent || 0;

  return (
    <motion.div
      whileTap={{ scale: 0.98 }}
      onClick={onClick}
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="relative overflow-hidden rounded-3xl bg-white/5 border border-white/5 backdrop-blur-md group cursor-pointer"
    >
      {/* Background Gradient based on completion */}
      <div className={`absolute inset-0 bg-gradient-to-br ${
        isCompleted 
          ? 'from-green-500/20 to-emerald-600/10' 
          : 'from-purple-500/10 to-blue-500/5'
      } opacity-0 group-hover:opacity-100 transition-opacity duration-500`} />

      <div className="relative p-5">
        <div className="flex items-start gap-4">
          {/* Icon Box */}
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-white/10 to-white/5 flex items-center justify-center text-4xl shadow-inner border border-white/10 shrink-0">
            {collection.icon}
          </div>

          <div className="flex-1 min-w-0 py-1">
            <h3 className="font-bold text-lg text-white mb-1 truncate">
              {collection.title}
            </h3>
            <p className="text-xs text-white/40 line-clamp-2 leading-relaxed">
              {collection.description}
            </p>
          </div>

          {isCompleted && (
            <div className="bg-green-500/20 p-2 rounded-full border border-green-500/30 text-green-400">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
          )}
        </div>

        {/* Progress Section */}
        <div className="mt-5">
          <div className="flex justify-between items-end mb-2">
            <div className="text-xs font-bold text-white/60">
              {collection.owned_items} <span className="text-white/30 font-normal">/ {collection.total_items} карт</span>
            </div>
            <div className={`text-sm font-black ${isCompleted ? 'text-green-400' : 'text-white'}`}>
              {progress.toFixed(0)}%
            </div>
          </div>
          
          <div className="h-2 bg-black/20 rounded-full overflow-hidden border border-white/5">
            <motion.div
              className={`h-full ${isCompleted ? 'bg-gradient-to-r from-green-400 to-emerald-500' : 'bg-gradient-to-r from-game-primary to-purple-500'}`}
              initial={{ width: 0 }}
              animate={{ width: `${progress}%` }}
              transition={{ duration: 1, ease: "circOut" }}
            />
          </div>
        </div>

        {/* Rewards */}
        {(collection.reward_coins > 0 || collection.reward_gems > 0) && (
          <div className="mt-4 pt-3 border-t border-white/5 flex items-center justify-between">
            <span className="text-[10px] uppercase tracking-wider text-white/30 font-bold">Награда</span>
            <div className="flex items-center gap-3">
              {collection.reward_coins > 0 && (
                <div className="flex items-center gap-1.5">
                  <CoinIcon size={14} />
                  <span className="text-xs font-bold text-yellow-400">{collection.reward_coins}</span>
                </div>
              )}
              {collection.reward_gems > 0 && (
                <div className="flex items-center gap-1.5">
                  <span className="text-sm">💎</span>
                  <span className="text-xs font-bold text-purple-400">{collection.reward_gems}</span>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </motion.div>
  );
}

export default function CollectionsPage() {
  const navigate = useNavigate();
  const [collections, setCollections] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const response = await api.getCollections();
      setCollections(response.data.collections || []);
    } catch (error) {
      console.error('Ошибка загрузки коллекций:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-dvh bg-aurora flex items-center justify-center">
        <div className="text-center">
          <div className="spinner mx-auto mb-4" />
          <div className="text-white/40 text-sm font-mono">LOADING_COLLECTIONS...</div>
        </div>
      </div>
    );
  }

  const totalItems = collections.reduce((sum, c) => sum + c.total_items, 0);
  const ownedItems = collections.reduce((sum, c) => sum + c.owned_items, 0);
  const completedCollections = collections.filter(c => c.is_completed).length;
  const overallProgress = totalItems > 0 ? ((ownedItems / totalItems) * 100).toFixed(1) : 0;

  return (
    <div className="min-h-dvh bg-aurora relative overflow-hidden flex flex-col pb-24">
      <div className="aurora-blob aurora-blob-3 opacity-30" />
      <div className="noise-overlay" />

      {/* Header */}
      <div className="relative z-10 px-4 pt-4 pb-2 bg-black/20 backdrop-blur-xl border-b border-white/5 sticky top-0">
        <div className="flex items-center justify-between mb-6">
          <button
            onClick={() => navigate(-1)}
            className="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-colors"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <h1 className="text-lg font-bold text-white">Коллекции</h1>
          <div className="w-10" />
        </div>

        {/* Hero Stats */}
        <div className="bg-white/5 border border-white/5 rounded-3xl p-5 mb-4 relative overflow-hidden">
          <div className="absolute inset-0 bg-gradient-to-br from-game-primary/10 to-transparent" />
          <div className="relative z-10 flex justify-between items-center">
            <div>
              <p className="text-white/40 text-xs font-bold uppercase tracking-wider mb-1">Собрано карт</p>
              <div className="flex items-baseline gap-2">
                <span className="text-3xl font-black text-white">{ownedItems}</span>
                <span className="text-sm text-white/40">/ {totalItems}</span>
              </div>
            </div>
            
            <div className="text-right">
              <div className="inline-flex items-center justify-center w-12 h-12 rounded-full border-4 border-white/10 relative">
                <span className="text-xs font-bold text-white">{overallProgress}%</span>
                <svg className="absolute inset-0 w-full h-full -rotate-90 scale-110">
                  <circle cx="24" cy="24" r="22" fill="none" stroke="currentColor" strokeWidth="2" className="text-game-primary" strokeDasharray="138" strokeDashoffset={138 - (138 * overallProgress / 100)} strokeLinecap="round" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* List */}
      <div className="relative z-10 px-4 py-4 flex-1">
        {collections.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-48 text-center">
            <div className="text-4xl mb-3 opacity-30">📦</div>
            <p className="text-white/40 text-sm">Коллекции скоро появятся</p>
          </div>
        ) : (
          <div className="space-y-4">
            {collections.map((collection) => (
              <CollectionCard
                key={collection.id}
                collection={collection}
                onClick={() => navigate(`/collections/${collection.id}`)}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
