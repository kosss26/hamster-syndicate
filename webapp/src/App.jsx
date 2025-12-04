import { useState, useEffect } from 'react'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { TelegramContext } from './hooks/useTelegram'
import HomePage from './pages/HomePage'
import DuelPage from './pages/DuelPage'
import ProfilePage from './pages/ProfilePage'
import LeaderboardPage from './pages/LeaderboardPage'
import StatsPage from './pages/StatsPage'
import TrueFalsePage from './pages/TrueFalsePage'

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
      telegram.setHeaderColor('#1a1a2e')
      telegram.setBackgroundColor('#1a1a2e')
      
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
      <div className="min-h-screen bg-gradient-game flex items-center justify-center">
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-game-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-telegram-hint">Загрузка...</p>
        </div>
      </div>
    )
  }

  return (
    <TelegramContext.Provider value={{ tg, user }}>
      <BrowserRouter>
        <div className="min-h-screen bg-gradient-game">
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/duel" element={<DuelPage />} />
            <Route path="/duel/:id" element={<DuelPage />} />
            <Route path="/profile" element={<ProfilePage />} />
            <Route path="/stats" element={<StatsPage />} />
            <Route path="/leaderboard" element={<LeaderboardPage />} />
            <Route path="/truefalse" element={<TrueFalsePage />} />
          </Routes>
        </div>
      </BrowserRouter>
    </TelegramContext.Provider>
  )
}

export default App

