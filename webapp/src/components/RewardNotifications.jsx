import { AnimatePresence, motion } from 'framer-motion'

const rarityClassByKey = {
  common: 'border-white/15 bg-white/10',
  rare: 'border-sky-300/45 bg-sky-500/15',
  epic: 'border-violet-300/50 bg-violet-500/15',
  legendary: 'border-amber-300/55 bg-amber-500/15',
}

export default function RewardNotifications({ items = [], onDismiss }) {
  return (
    <div className="fixed top-4 left-0 right-0 z-[90] pointer-events-none px-4">
      <div className="mx-auto w-full max-w-md space-y-2">
        <AnimatePresence initial={false}>
          {items.map((item) => {
            const rarity = item.rarity || 'common'
            const rarityClass = rarityClassByKey[rarity] || rarityClassByKey.common

            return (
              <motion.div
                key={item.id}
                initial={{ opacity: 0, y: -16, scale: 0.97 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: -12, scale: 0.98 }}
                transition={{ duration: 0.22 }}
                className={`pointer-events-auto rounded-2xl border backdrop-blur-xl p-3 shadow-xl ${rarityClass}`}
              >
                <div className="flex items-start gap-3">
                  <div className="w-10 h-10 rounded-xl bg-black/25 border border-white/10 flex items-center justify-center text-lg">
                    {item.icon || (item.type === 'achievement' ? '🏆' : '🃏')}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                      <p className="text-xs text-white/60 uppercase tracking-wide">{item.type === 'achievement' ? 'Новое достижение' : 'Новая карточка'}</p>
                      <button
                        type="button"
                        onClick={() => onDismiss?.(item.id)}
                        className="text-white/60 hover:text-white text-sm"
                      >
                        ✕
                      </button>
                    </div>
                    <p className="text-sm font-semibold text-white truncate mt-0.5">{item.title}</p>
                    {item.subtitle ? <p className="text-xs text-white/70 mt-0.5">{item.subtitle}</p> : null}
                  </div>
                </div>
              </motion.div>
            )
          })}
        </AnimatePresence>
      </div>
    </div>
  )
}
