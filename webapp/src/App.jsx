import { useState, useEffect } from 'react'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { TelegramContext } from './hooks/useTelegram'
import HomePage from './pages/HomePage'
import DuelPage from './pages/DuelPage'
import ProfilePage from './pages/ProfilePage'
import LeaderboardPage from './pages/LeaderboardPage'
import StatsPage from './pages/StatsPage'
import TrueFalsePage from './pages/TrueFalsePage'
import AdminPage from './pages/AdminPage'

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
      
      // Включаем кнопку "Назад"
      telegram.BackButton.onClick(() => {
        window.history.back()
      })
      
      setTg(telegram)
      setUser(telegram.initDataUnsafe?.user || null)
      setIsReady(true)
      
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
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/duel" element={<DuelPage />} />
            <Route path="/duel/:id" element={<DuelPage />} />
            <Route path="/profile" element={<ProfilePage />} />
            <Route path="/stats" element={<StatsPage />} />
            <Route path="/leaderboard" element={<LeaderboardPage />} />
            <Route path="/truefalse" element={<TrueFalsePage />} />
            <Route path="/admin" element={<AdminPage />} />
          </Routes>
        </div>
      </BrowserRouter>
    </TelegramContext.Provider>
  )
}

export default App
