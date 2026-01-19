import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useTelegram } from '../hooks/useTelegram'

function TelegramBackButton() {
  const { tg } = useTelegram()
  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    if (!tg) return

    // Если мы на главной странице - скрываем кнопку "Назад"
    if (location.pathname === '/') {
      tg.BackButton.hide()
    } else {
      // На всех остальных - показываем
      tg.BackButton.show()
      
      // Настраиваем обработчик
      const handleBack = () => {
        navigate(-1)
      }
      
      tg.BackButton.onClick(handleBack)
      
      return () => {
        tg.BackButton.offClick(handleBack)
      }
    }
  }, [tg, location.pathname, navigate])

  return null
}

export default TelegramBackButton
