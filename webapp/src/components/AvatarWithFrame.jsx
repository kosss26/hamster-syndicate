import { useEffect, useMemo, useState } from 'react'
import { motion } from 'framer-motion'

// Градиенты для разных рамок
const FRAME_STYLES = {
  default: {
    gradient: 'from-gray-500 to-gray-600',
    glow: 'from-gray-500/20 to-gray-600/20',
  },
  winner: {
    gradient: 'from-yellow-400 to-amber-600',
    glow: 'from-yellow-400/30 to-amber-600/30',
  },
  streak: {
    gradient: 'from-orange-500 to-red-600',
    glow: 'from-orange-500/30 to-red-600/30',
  },
  legend: {
    gradient: 'from-purple-500 to-purple-700',
    glow: 'from-purple-500/30 to-purple-700/30',
  },
  rainbow: {
    gradient: 'from-pink-500 via-purple-500 to-blue-500',
    glow: 'from-pink-500/30 via-purple-500/30 to-blue-500/30',
  },
  diamond: {
    gradient: 'from-cyan-400 via-blue-500 to-purple-600',
    glow: 'from-cyan-400/30 via-blue-500/30 to-purple-600/30',
  },
  royal: {
    gradient: 'from-yellow-300 via-yellow-500 to-yellow-700',
    glow: 'from-yellow-300/40 via-yellow-500/40 to-yellow-700/40',
  },
  lightning: {
    gradient: 'from-yellow-300 via-purple-500 to-blue-600',
    glow: 'from-yellow-300/30 via-purple-500/30 to-blue-600/30',
  },
}

function resolveFrameAssetUrl(frameKey) {
  const normalized = String(frameKey || '').trim()
  if (!normalized || normalized === 'default' || FRAME_STYLES[normalized]) return null

  const filename = normalized.replace(/\.png$/i, '')
  if (!/^[a-z0-9._-]+$/i.test(filename)) return null

  return `/api/images/cosmetics/${filename}.png`
}

function resolveDisplayName(name, user) {
  if (typeof name === 'string' && name.trim() && name !== '?') return name
  if (typeof user?.first_name === 'string' && user.first_name.trim()) return user.first_name
  if (typeof user?.name === 'string' && user.name.trim()) return user.name
  if (typeof user?.username === 'string' && user.username.trim()) return user.username
  return '?'
}

function AvatarWithFrame({
  user = null,
  photoUrl,
  name,
  frameKey,
  size = 112,
  animated = false,
  showGlow = true,
  className = ''
}) {
  const resolvedPhotoUrl = photoUrl ?? user?.photo_url ?? user?.photoUrl ?? null
  const resolvedFrameKey = frameKey ?? user?.equipped_frame ?? user?.equippedFrame ?? 'default'
  const frameStyle = FRAME_STYLES[resolvedFrameKey] || FRAME_STYLES.default
  const initial = resolveDisplayName(name, user)[0]?.toUpperCase() || '?'
  const frameAssetUrl = useMemo(() => resolveFrameAssetUrl(resolvedFrameKey), [resolvedFrameKey])
  const [avatarBroken, setAvatarBroken] = useState(false)
  const [frameBroken, setFrameBroken] = useState(false)
  const hasFrameAsset = Boolean(frameAssetUrl && !frameBroken)
  const frameWidth = Math.max(3, size * 0.06)

  useEffect(() => {
    setAvatarBroken(false)
  }, [resolvedPhotoUrl])

  useEffect(() => {
    setFrameBroken(false)
  }, [frameAssetUrl])

  return (
    <div className={`relative ${className}`} style={{ width: size, height: size }}>
      {showGlow && (
        <div className={`absolute -inset-2 rounded-full bg-gradient-to-r ${frameStyle.glow} blur-lg opacity-50`} />
      )}

      <motion.div
        className="relative w-full h-full"
        animate={animated ? { rotate: 360 } : {}}
        transition={animated ? {
          duration: 3,
          repeat: Infinity,
          ease: 'linear'
        } : {}}
      >
        {hasFrameAsset ? (
          <>
            <div className="absolute inset-0 rounded-full bg-gradient-to-br from-game-primary to-purple-700 overflow-hidden flex items-center justify-center">
              {resolvedPhotoUrl && !avatarBroken ? (
                <img
                  src={resolvedPhotoUrl}
                  alt={resolveDisplayName(name, user)}
                  className="w-full h-full object-cover"
                  onError={() => setAvatarBroken(true)}
                />
              ) : null}
              {(!resolvedPhotoUrl || avatarBroken) ? (
                <span
                  className="absolute inset-0 flex items-center justify-center font-bold text-white"
                  style={{ fontSize: size * 0.4 }}
                >
                  {initial}
                </span>
              ) : null}
            </div>

            <img
              src={frameAssetUrl}
              alt=""
              aria-hidden="true"
              className="absolute inset-0 w-full h-full object-contain pointer-events-none"
              onError={() => setFrameBroken(true)}
            />
          </>
        ) : (
          <div
            className={`absolute inset-0 rounded-full bg-gradient-to-br ${frameStyle.gradient}`}
            style={{ padding: frameWidth }}
          >
            <div className="w-full h-full rounded-full bg-gradient-to-br from-game-primary to-purple-700 overflow-hidden flex items-center justify-center">
              {resolvedPhotoUrl && !avatarBroken ? (
                <img
                  src={resolvedPhotoUrl}
                  alt={resolveDisplayName(name, user)}
                  className="w-full h-full object-cover"
                  onError={() => setAvatarBroken(true)}
                />
              ) : null}
              {(!resolvedPhotoUrl || avatarBroken) ? (
                <span
                  className="absolute inset-0 flex items-center justify-center font-bold text-white"
                  style={{ fontSize: size * 0.4 }}
                >
                  {initial}
                </span>
              ) : null}
            </div>
          </div>
        )}
      </motion.div>
    </div>
  )
}

export default AvatarWithFrame
