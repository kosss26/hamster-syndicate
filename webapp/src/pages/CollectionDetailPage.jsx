import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../api/client';

const rarityColors = {
  common: {
    bg: 'from-gray-500/20 to-gray-600/10',
    border: 'border-gray-500/30',
    text: 'text-gray-400'
  },
  rare: {
    bg: 'from-blue-500/20 to-blue-600/10',
    border: 'border-blue-500/30',
    text: 'text-blue-400'
  },
  epic: {
    bg: 'from-purple-500/20 to-purple-600/10',
    border: 'border-purple-500/30',
    text: 'text-purple-400'
  },
  legendary: {
    bg: 'from-orange-500/20 to-yellow-500/10',
    border: 'border-orange-400/30',
    text: 'text-orange-400'
  }
};

function CollectionItemCard({ item, onClick }) {
  const isOwned = item.is_owned;
  const rarity = rarityColors[item.rarity] || rarityColors.common;

  return (
    <motion.div
      whileTap={{ scale: 0.95 }}
      onClick={onClick}
      className={`relative overflow-hidden rounded-2xl bg-gradient-to-br ${rarity.bg} 
        border ${rarity.border} backdrop-blur-sm cursor-pointer hover:scale-[1.02] transition-transform
        ${!isOwned ? 'opacity-60' : ''}`}
    >
      {/* Блики для собранных */}
      {isOwned && (
        <motion.div
          className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent"
          animate={{ x: ['-100%', '200%'] }}
          transition={{ duration: 2, repeat: Infinity, repeatDelay: 5 }}
        />
      )}

      <div className="relative aspect-square p-4 flex flex-col items-center justify-center">
        {isOwned ? (
          <>
            {/* Изображение или иконка */}
            {item.image_url ? (
              <img src={item.image_url} alt={item.name} className="w-full h-full object-cover" />
            ) : (
              <div className="text-6xl mb-2">
                {item.rarity === 'legendary' ? '👑' : 
                 item.rarity === 'epic' ? '⭐' :
                 item.rarity === 'rare' ? '💫' : '✨'}
              </div>
            )}
            
            {/* Название */}
            <div className="mt-auto text-center">
              <h3 className="text-sm font-bold text-white mb-0.5 line-clamp-1">{item.name}</h3>
              <p className={`text-xs ${rarity.text} font-medium`}>
                {item.rarity === 'legendary' ? 'Легендарная' :
                 item.rarity === 'epic' ? 'Эпическая' :
                 item.rarity === 'rare' ? 'Редкая' : 'Обычная'}
              </p>
            </div>

            {/* Бейдж "Новая" */}
            {item.obtained_at && isNewItem(item.obtained_at) && (
              <div className="absolute top-2 right-2 bg-green-500/20 border border-green-500/30 rounded-full px-2 py-0.5">
                <span className="text-xs text-green-400">NEW</span>
              </div>
            )}
          </>
        ) : (
          <>
            {/* Силуэт для несобранных */}
            <div className="text-6xl mb-2 opacity-30 grayscale">❓</div>
            <div className="text-center">
              <h3 className="text-sm font-bold text-gray-500">???</h3>
              <p className="text-xs text-gray-600 mt-1">Не собрано</p>
            </div>
          </>
        )}
      </div>
    </motion.div>
  );
}

// Проверка, была ли карточка получена недавно (последние 24 часа)
function isNewItem(obtainedAt) {
  const obtained = new Date(obtainedAt);
  const now = new Date();
  const diff = now - obtained;
  return diff < 24 * 60 * 60 * 1000; // 24 hours
}

// Модальное окно с деталями карточки
function ItemModal({ item, isOpen, onClose }) {
  if (!isOpen || !item) return null;
  
  const rarity = rarityColors[item.rarity] || rarityColors.common;

  return (
    <AnimatePresence>
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80"
        onClick={onClose}
      >
        <motion.div
          initial={{ scale: 0.9, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          exit={{ scale: 0.9, opacity: 0 }}
          onClick={(e) => e.stopPropagation()}
          className={`relative max-w-md w-full bg-gradient-to-br ${rarity.bg} 
            border ${rarity.border} rounded-3xl p-6 backdrop-blur-md`}
        >
          {/* Закрыть */}
          <button
            onClick={onClose}
            className="absolute top-4 right-4 p-2 rounded-xl bg-white/5 hover:bg-white/10 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          {/* Содержимое */}
          <div className="text-center">
            {item.image_url ? (
              <img 
                src={item.image_url} 
                alt={item.name} 
                className="w-32 h-32 mx-auto rounded-2xl object-cover mb-4"
              />
            ) : (
              <div className="text-8xl mb-4">
                {item.rarity === 'legendary' ? '👑' : 
                 item.rarity === 'epic' ? '⭐' :
                 item.rarity === 'rare' ? '💫' : '✨'}
              </div>
            )}

            <h2 className="text-2xl font-bold text-white mb-2">{item.name}</h2>
            <p className={`text-sm ${rarity.text} font-medium mb-4`}>
              {item.rarity === 'legendary' ? 'Легендарная' :
               item.rarity === 'epic' ? 'Эпическая' :
               item.rarity === 'rare' ? 'Редкая' : 'Обычная'}
            </p>

            <p className="text-gray-300 mb-4 leading-relaxed">
              {item.description}
            </p>

            {item.is_owned && item.obtained_at && (
              <div className="pt-4 border-t border-white/10">
                <p className="text-sm text-gray-400">
                  Получено: {new Date(item.obtained_at).toLocaleDateString('ru-RU')}
                </p>
                {item.obtained_from && (
                  <p className="text-xs text-gray-500 mt-1">
                    Источник: {item.obtained_from === 'lootbox' ? 'Лутбокс' : 'Достижение'}
                  </p>
                )}
              </div>
            )}
          </div>
        </motion.div>
      </motion.div>
    </AnimatePresence>
  );
}

export default function CollectionDetailPage() {
  const navigate = useNavigate();
  const { collectionId } = useParams();
  const [collection, setCollection] = useState(null);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedItem, setSelectedItem] = useState(null);
  const [showModal, setShowModal] = useState(false);

  useEffect(() => {
    loadData();
  }, [collectionId]);

  const loadData = async () => {
    try {
      setLoading(true);
      const [collectionsRes, itemsRes] = await Promise.all([
        api.getCollections(),
        api.getCollectionItems(parseInt(collectionId))
      ]);
      
      const currentCollection = collectionsRes.data.collections.find(
        c => c.id === parseInt(collectionId)
      );
      setCollection(currentCollection);
      setItems(itemsRes.data.items || []);
    } catch (error) {
      console.error('Ошибка загрузки коллекции:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleItemClick = (item) => {
    if (item.is_owned) {
      setSelectedItem(item);
      setShowModal(true);
    }
  };

  if (loading || !collection) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-game-dark via-game-dark/95 to-game-dark flex items-center justify-center">
        <div className="text-center">
          <div className="text-6xl mb-4 animate-bounce">📚</div>
          <div className="text-gray-400">Загрузка коллекции...</div>
        </div>
      </div>
    );
  }

  const ownedCount = items.filter(i => i.is_owned).length;
  const progress = items.length > 0 ? ((ownedCount / items.length) * 100).toFixed(1) : 0;

  return (
    <div className="min-h-screen bg-gradient-to-b from-game-dark via-game-dark/95 to-game-dark pb-20">
      {/* Хедер */}
      <div className="sticky top-0 z-10 bg-game-dark/80 backdrop-blur-md border-b border-white/10">
        <div className="px-4 py-4">
          <div className="flex items-center justify-between mb-4">
            <button
              onClick={() => navigate('/collections')}
              className="p-2 rounded-xl bg-white/5 hover:bg-white/10 transition-colors"
            >
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            
            <div className="flex-1 text-center">
              <div className="text-3xl mb-1">{collection.icon}</div>
              <h1 className="text-xl font-bold">{collection.title}</h1>
            </div>
            
            <div className="w-10" /> {/* Spacer */}
          </div>

          {/* Прогресс */}
          <div className="mb-3">
            <div className="flex justify-between text-sm mb-2">
              <span className="text-gray-300">Собрано: {ownedCount}/{items.length}</span>
              <span className="text-gray-400">{progress}%</span>
            </div>
            <div className="h-2 bg-gray-700/50 rounded-full overflow-hidden">
              <motion.div
                className="h-full bg-gradient-to-r from-purple-500/60 to-purple-600/40"
                initial={{ width: 0 }}
                animate={{ width: `${progress}%` }}
                transition={{ duration: 0.5 }}
              />
            </div>
          </div>

          {/* Награда */}
          {collection.is_completed && (
            <div className="bg-green-500/10 border border-green-500/30 rounded-xl p-3 text-center">
              <p className="text-sm text-green-400 font-medium">
                🎉 Коллекция завершена! Награда получена
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Сетка карточек */}
      <div className="px-4 py-4">
        <div className="grid grid-cols-3 gap-3">
          {items.map((item) => (
            <CollectionItemCard
              key={item.id}
              item={item}
              onClick={() => handleItemClick(item)}
            />
          ))}
        </div>
      </div>

      {/* Модальное окно */}
      <ItemModal
        item={selectedItem}
        isOpen={showModal}
        onClose={() => setShowModal(false)}
      />
    </div>
  );
}

