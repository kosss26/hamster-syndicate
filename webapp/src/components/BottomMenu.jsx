import { Link, useLocation } from 'react-router-dom'
import { motion } from 'framer-motion'

export default function BottomMenu() {
  const location = useLocation()
  
  const tabs = [
    {
      id: 'home',
      path: '/',
      label: 'Главная',
      icon: (active) => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={active ? "currentColor" : "currentColor"} strokeWidth={active ? "2.5" : "2"} strokeLinecap="round" strokeLinejoin="round">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      )
    },
    {
      id: 'shop',
      path: '/shop',
      label: 'Магазин',
      icon: (active) => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={active ? "currentColor" : "currentColor"} strokeWidth={active ? "2.5" : "2"} strokeLinecap="round" strokeLinejoin="round">
          <circle cx="8" cy="21" r="1"/>
          <circle cx="19" cy="21" r="1"/>
          <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>
        </svg>
      )
    },
    {
      id: 'leaderboard',
      path: '/leaderboard',
      label: 'Рейтинг',
      icon: (active) => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={active ? "currentColor" : "currentColor"} strokeWidth={active ? "2.5" : "2"} strokeLinecap="round" strokeLinejoin="round">
          <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
          <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
          <path d="M4 22h16"/>
          <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
          <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
          <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
        </svg>
      )
    },
    {
      id: 'profile',
      path: '/profile',
      label: 'Профиль',
      icon: (active) => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={active ? "currentColor" : "currentColor"} strokeWidth={active ? "2.5" : "2"} strokeLinecap="round" strokeLinejoin="round">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      )
    }
  ]

  const isTabActive = (path) => {
    if (path === '/') {
      return location.pathname === '/'
    }
    return location.pathname === path || location.pathname.startsWith(`${path}/`)
  }

  return (
    <div className="fixed bottom-0 left-0 right-0 z-50 px-4 pb-4 pt-2">
      <div className="absolute inset-0 bg-gradient-to-t from-black via-black/85 to-transparent pointer-events-none" />

      <motion.div
        initial={{ y: 90, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        transition={{ duration: 0.35, ease: 'easeOut' }}
        className="relative rounded-[28px] border border-white/10 bg-black/45 backdrop-blur-2xl p-1.5 shadow-[0_20px_40px_rgba(0,0,0,0.45)]"
      >
        <div className="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-white/35 to-transparent" />
        <div className="grid grid-cols-4 gap-1">
          {tabs.map((tab) => {
            const isActive = isTabActive(tab.path)

            return (
              <Link
                key={tab.id}
                to={tab.path}
                className="relative h-14 rounded-2xl flex items-center justify-center group"
              >
                {isActive && (
                  <motion.div
                    layoutId="activeDockTab"
                    className="absolute inset-0 rounded-2xl border border-cyan-300/35 bg-gradient-to-b from-cyan-300/20 to-blue-500/15"
                    initial={false}
                    transition={{ type: 'spring', stiffness: 420, damping: 32 }}
                  />
                )}

                <div className={`relative z-10 flex flex-col items-center transition-colors ${isActive ? 'text-cyan-200' : 'text-white/45 group-hover:text-white/70'}`}>
                  {tab.icon(isActive)}
                  <span className={`text-[10px] mt-0.5 ${isActive ? 'text-white' : 'text-white/45'}`}>{tab.label}</span>
                </div>
              </Link>
            )
          })}
        </div>
      </motion.div>
    </div>
  )
}
