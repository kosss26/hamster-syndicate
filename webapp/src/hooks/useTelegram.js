import { createContext, useContext } from 'react'

export const TelegramContext = createContext({
  tg: null,
  user: null
})

export function useTelegram() {
  const context = useContext(TelegramContext)
  // Добавляем webApp как алиас для tg для совместимости
  return {
    ...context,
    webApp: context.tg
  }
}

// Утилиты для работы с Telegram
export function hapticFeedback(type = 'light') {
  const tg = window.Telegram?.WebApp
  if (tg?.HapticFeedback) {
    switch (type) {
      case 'light':
        tg.HapticFeedback.impactOccurred('light')
        break
      case 'medium':
        tg.HapticFeedback.impactOccurred('medium')
        break
      case 'heavy':
        tg.HapticFeedback.impactOccurred('heavy')
        break
      case 'success':
        tg.HapticFeedback.notificationOccurred('success')
        break
      case 'error':
        tg.HapticFeedback.notificationOccurred('error')
        break
      case 'warning':
        tg.HapticFeedback.notificationOccurred('warning')
        break
    }
  }
}

export function showBackButton(show = true) {
  const tg = window.Telegram?.WebApp
  if (tg?.BackButton) {
    if (show) {
      tg.BackButton.show()
    } else {
      tg.BackButton.hide()
    }
  }
}

export function closeApp() {
  const tg = window.Telegram?.WebApp
  if (tg) {
    tg.close()
  }
}

