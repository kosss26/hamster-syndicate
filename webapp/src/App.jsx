import { useState, useEffect } from 'react'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { TelegramContext } from './hooks/useTelegram'
import api from './api/client'
import Layout from './components/Layout'
import HomePage from './pages/HomePage'
import DuelPage from './pages/DuelPage'
import ProfilePage from './pages/ProfilePage'
import LeaderboardPage from './pages/LeaderboardPage'
import StatsPage from './pages/StatsPage'
import TrueFalsePage from './pages/TrueFalsePage'
import ReferralPage from './pages/ReferralPage'
import AdminPage from './pages/AdminPage'
import ShopPage from './pages/ShopPage'
import InventoryPage from './pages/InventoryPage'
import FortuneWheelPage from './pages/FortuneWheelPage'
import LootboxPage from './pages/LootboxPage'
import AchievementsPage from './pages/AchievementsPage'
import CollectionsPage from './pages/CollectionsPage'
import CollectionDetailPage from './pages/CollectionDetailPage'
import AdminButton from './components/AdminButton'
import TelegramBackButton from './components/TelegramBackButton'

function App() {
  const [tg, setTg] = useState(null)
  const [user, setUser] = useState(null)
  const [isReady, setIsReady] = useState(false)

  useEffect(() => {
    const telegram = window.Telegram?.WebApp
    
    if (telegram) {
      telegram.ready()
      telegram.expand()
      
      // Настройка темы
      telegram.setHeaderColor('#0a0a0f')
      telegram.setBackgroundColor('#0a0a0f')
      
      setTg(telegram)
      
      // Загружаем полные данные пользователя с сервера (включая photo_url)
      const loadUserData = async () => {
        try {
          const response = await api.getProfile()
          if (response.success && response.data) {
            // Объединяем данные из Telegram с данными с сервера
            const fullUser = {
              ...(telegram.initDataUnsafe?.user || {}),
              photo_url: response.data.photo_url,
              id: response.data.telegram_id || telegram.initDataUnsafe?.user?.id,
              first_name: response.data.first_name || telegram.initDataUnsafe?.user?.first_name,
              last_name: response.data.last_name || telegram.initDataUnsafe?.user?.last_name,
              username: response.data.username || telegram.initDataUnsafe?.user?.username,
            }
            setUser(fullUser)
          } else {
            setUser(telegram.initDataUnsafe?.user || null)
          }
        } catch (err) {
          console.error('Failed to load user data:', err)
          setUser(telegram.initDataUnsafe?.user || null)
        } finally {
          setIsReady(true)
        }
      }
      
      loadUserData()
      console.log('Telegram WebApp initialized:', telegram.initDataUnsafe)
    } else {
      // Для разработки без Telegram
      setUser({
        id: 123456789,
        first_name: 'Тестовый',
        last_name: 'Пользователь',
        username: 'test_user'
      })
      setIsReady(true)
      console.log('Running in development mode')
    }
  }, [])

  if (!isReady) {
    return (
      <div className="min-h-screen bg-aurora relative overflow-hidden flex items-center justify-center">
        {/* Aurora Background */}
        <div className="aurora-blob aurora-blob-1" />
        <div className="aurora-blob aurora-blob-2" />
        <div className="noise-overlay" />
        
        <div className="relative z-10 text-center">
          {/* Animated Logo */}
          <div className="relative mb-6">
            <div className="absolute inset-0 rounded-full bg-game-primary/30 blur-2xl animate-pulse" />
            <div className="relative text-7xl animate-bounce-in">⚔️</div>
          </div>
          
          {/* Spinner */}
          <div className="spinner mx-auto mb-4" />
          
          {/* Text */}
          <p className="text-white/40 text-sm">Загрузка...</p>
        </div>
      </div>
    )
  }

  return (
    <TelegramContext.Provider value={{ tg, user }}>
      <BrowserRouter basename="/webapp">
        <div className="min-h-screen">
          <TelegramBackButton />
          <AdminButton />
          <Routes>
            {/* Routes with Bottom Menu */}
            <Route element={<Layout />}>
              <Route path="/" element={<HomePage />} />
              <Route path="/profile" element={<ProfilePage />} />
              <Route path="/stats" element={<StatsPage />} />
              <Route path="/leaderboard" element={<LeaderboardPage />} />
              <Route path="/referral" element={<ReferralPage />} />
              <Route path="/shop" element={<ShopPage />} />
              <Route path="/inventory" element={<InventoryPage />} />
              <Route path="/wheel" element={<FortuneWheelPage />} />
              <Route path="/lootbox" element={<LootboxPage />} />
              <Route path="/achievements" element={<AchievementsPage />} />
              <Route path="/collections" element={<CollectionsPage />} />
              <Route path="/collections/:collectionId" element={<CollectionDetailPage />} />
              <Route path="/admin" element={<AdminPage />} />
            </Route>

            {/* Routes WITHOUT Bottom Menu */}
            <Route path="/duel" element={<DuelPage />} />
            <Route path="/duel/:id" element={<DuelPage />} />
            <Route path="/truefalse" element={<TrueFalsePage />} />
          </Routes>
        </div>
      </BrowserRouter>
    </TelegramContext.Provider>
  )
}

export default App
