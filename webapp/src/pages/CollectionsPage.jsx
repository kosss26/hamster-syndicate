import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import api from '../api/client';
import CoinIcon from '../components/CoinIcon';

const rarityColors = {
  common: 'from-gray-500/20 to-gray-600/10 border-gray-500/30',
  rare: 'from-blue-500/20 to-blue-600/10 border-blue-500/30',
  epic: 'from-purple-500/20 to-purple-600/10 border-purple-500/30',
  legendary: 'from-orange-500/20 to-yellow-500/10 border-orange-400/30'
};

function CollectionCard({ collection, onClick }) {
  const isCompleted = collection.is_completed;
  const progress = collection.progress_percent || 0;

  return (
    <motion.div
      whileTap={{ scale: 0.98 }}
      onClick={onClick}
      className={`relative overflow-hidden rounded-2xl bg-gradient-to-br ${rarityColors[collection.rarity] || rarityColors.common} 
        border backdrop-blur-sm cursor-pointer hover:scale-[1.02] transition-transform`}
    >
      {/* Блики для завершённых */}
      {isCompleted && (
        <>
          <motion.div
            className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent"
            animate={{ x: ['-100%', '200%'] }}
            transition={{ duration: 2, repeat: Infinity, repeatDelay: 3 }}
          />
          <div className="absolute top-3 right-3 bg-green-500/20 border border-green-500/30 rounded-full px-2 py-0.5">
            <span className="text-xs text-green-400">✓ Завершена</span>
          </div>
        </>
      )}

      <div className="relative p-4">
        {/* Иконка и заголовок */}
        <div className="flex items-start gap-3 mb-3">
          <div className="text-4xl">{collection.icon}</div>
          
          <div className="flex-1">
            <h3 className="font-bold text-lg text-white mb-1">
              {collection.title}
            </h3>
            <p className="text-sm text-gray-400 leading-snug">
              {collection.description}
            </p>
          </div>
        </div>

        {/* Прогресс */}
        <div className="mb-3">
          <div className="flex justify-between text-sm mb-2">
            <span className="text-gray-300 font-medium">
              Собрано: {collection.owned_items}/{collection.total_items}
            </span>
            <span className="text-gray-400">
              {progress.toFixed(0)}%
            </span>
          </div>
          <div className="h-2 bg-gray-700/50 rounded-full overflow-hidden">
            <motion.div
              className={`h-full bg-gradient-to-r ${rarityColors[collection.rarity].split('border')[0]}`}
              initial={{ width: 0 }}
              animate={{ width: `${progress}%` }}
              transition={{ duration: 0.5 }}
            />
          </div>
        </div>

        {/* Награды */}
        {(collection.reward_coins > 0 || collection.reward_gems > 0) && (
          <div className="pt-3 border-t border-white/5">
            <div className="flex items-center justify-between">
              <span className="text-xs text-gray-400">Награда за полную коллекцию:</span>
              <div className="flex items-center gap-2">
                {collection.reward_coins > 0 && (
                  <div className="flex items-center gap-1">
                    <CoinIcon size={16} />
                    <span className="text-yellow-400 font-medium">{collection.reward_coins}</span>
                  </div>
                )}
                {collection.reward_gems > 0 && (
                  <div className="flex items-center gap-1">
                    <span className="text-lg">💎</span>
                    <span className="text-purple-400 font-medium">{collection.reward_gems}</span>
                  </div>
                )}
              </div>
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

  const handleCollectionClick = (collectionId) => {
    navigate(`/collections/${collectionId}`);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-game-dark via-game-dark/95 to-game-dark flex items-center justify-center">
        <div className="text-center">
          <div className="text-6xl mb-4 animate-bounce">📚</div>
          <div className="text-gray-400">Загрузка коллекций...</div>
        </div>
      </div>
    );
  }

  // Статистика
  const totalItems = collections.reduce((sum, c) => sum + c.total_items, 0);
  const ownedItems = collections.reduce((sum, c) => sum + c.owned_items, 0);
  const completedCollections = collections.filter(c => c.is_completed).length;
  const overallProgress = totalItems > 0 ? ((ownedItems / totalItems) * 100).toFixed(1) : 0;

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
            
            <h1 className="text-2xl font-bold">📚 Коллекции</h1>
            
            <div className="w-10" /> {/* Spacer */}
          </div>

          {/* Общая статистика */}
          <div className="grid grid-cols-3 gap-2">
            <div className="bg-gradient-to-br from-purple-500/20 to-purple-600/10 rounded-xl p-3 border border-purple-500/30">
              <div className="text-xl font-bold text-white">{ownedItems}/{totalItems}</div>
              <div className="text-xs text-gray-400">Карточек</div>
            </div>
            <div className="bg-gradient-to-br from-blue-500/20 to-blue-600/10 rounded-xl p-3 border border-blue-500/30">
              <div className="text-xl font-bold text-white">{overallProgress}%</div>
              <div className="text-xs text-gray-400">Прогресс</div>
            </div>
            <div className="bg-gradient-to-br from-green-500/20 to-green-600/10 rounded-xl p-3 border border-green-500/30">
              <div className="text-xl font-bold text-white">{completedCollections}/{collections.length}</div>
              <div className="text-xs text-gray-400">Полных</div>
            </div>
          </div>
        </div>
      </div>

      {/* Список коллекций */}
      <div className="px-4 py-4">
        {collections.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-6xl mb-4 opacity-50">📚</div>
            <div className="text-gray-400">Коллекции скоро появятся</div>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-3">
            {collections.map((collection) => (
              <CollectionCard
                key={collection.id}
                collection={collection}
                onClick={() => handleCollectionClick(collection.id)}
              />
            ))}
          </div>
        )}
      </div>

      {/* Подсказка */}
      <div className="px-4 py-4">
        <div className="bg-blue-500/10 border border-blue-500/30 rounded-xl p-4">
          <div className="flex gap-3">
            <div className="text-2xl">💡</div>
            <div>
              <h3 className="font-bold text-white mb-1">Как собирать карточки?</h3>
              <p className="text-sm text-gray-400">
                Открывайте лутбоксы и выполняйте достижения, чтобы получить новые карточки.
                За полную коллекцию вы получите награду!
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

