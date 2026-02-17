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

const EXTERNAL_FRAME_FALLBACK = {
  avatarInsetPercent: 9,
  frameScale: 1.08,
}

const frameMetricsCache = new Map()
const FRAME_EXTENSIONS = ['png', 'webp', 'gif', 'svg']

function resolveFrameAssetUrls(frameKey) {
  const normalized = String(frameKey || '').trim()
  if (!normalized || normalized === 'default' || FRAME_STYLES[normalized]) return []

  const hasExtension = /\.[a-z0-9]+$/i.test(normalized)
  const filename = normalized.replace(/\.(png|webp|gif|svg)$/i, '')
  if (!/^[a-z0-9._-]+$/i.test(filename)) return []

  if (hasExtension) {
    return [`/api/images/cosmetics/${normalized}`]
  }

  return FRAME_EXTENSIONS.map((ext) => `/api/images/cosmetics/${filename}.${ext}`)
}

function resolveDisplayName(name, user) {
  if (typeof name === 'string' && name.trim() && name !== '?') return name
  if (typeof user?.first_name === 'string' && user.first_name.trim()) return user.first_name
  if (typeof user?.name === 'string' && user.name.trim()) return user.name
  if (typeof user?.username === 'string' && user.username.trim()) return user.username
  return '?'
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value))
}

function median(values) {
  if (!Array.isArray(values) || values.length === 0) return 0
  const sorted = [...values].sort((a, b) => a - b)
  const mid = Math.floor(sorted.length / 2)
  if (sorted.length % 2 === 0) {
    return (sorted[mid - 1] + sorted[mid]) / 2
  }
  return sorted[mid]
}

function quantile(values, q) {
  if (!Array.isArray(values) || values.length === 0) return 0
  const sorted = [...values].sort((a, b) => a - b)
  const clampedQ = clamp(q, 0, 1)
  const pos = (sorted.length - 1) * clampedQ
  const base = Math.floor(pos)
  const rest = pos - base
  const next = sorted[base + 1] ?? sorted[base]
  return sorted[base] + rest * (next - sorted[base])
}

function detectExternalFrameMetrics(imageData, width, height) {
  const alphaThreshold = 20
  const minSide = Math.min(width, height)
  if (minSide < 16) return EXTERNAL_FRAME_FALLBACK

  const pixels = imageData.data
  let minX = width
  let maxX = -1
  let minY = height
  let maxY = -1

  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const alpha = pixels[(y * width + x) * 4 + 3]
      if (alpha > alphaThreshold) {
        if (x < minX) minX = x
        if (x > maxX) maxX = x
        if (y < minY) minY = y
        if (y > maxY) maxY = y
      }
    }
  }

  if (maxX < minX || maxY < minY) {
    return EXTERNAL_FRAME_FALLBACK
  }

  // Центр всегда берём из центра изображения, а не по opaque-boundary:
  // это защищает от смещения при асимметричных "хвостах" рамок (огонь/блики и т.д.).
  const centerX = (width - 1) / 2
  const centerY = (height - 1) / 2
  const maxRadius = minSide / 2
  const anglesCount = 84
  const innerSamples = []
  const outerSamples = []

  for (let i = 0; i < anglesCount; i += 1) {
    const angle = (Math.PI * 2 * i) / anglesCount
    const cosA = Math.cos(angle)
    const sinA = Math.sin(angle)
    let firstOpaque = -1
    let lastOpaque = -1

    for (let r = 0; r <= maxRadius; r += 1) {
      const px = Math.round(centerX + (cosA * r))
      const py = Math.round(centerY + (sinA * r))
      if (px < 0 || px >= width || py < 0 || py >= height) continue

      const alpha = pixels[(py * width + px) * 4 + 3]
      if (alpha > alphaThreshold) {
        if (firstOpaque < 0) firstOpaque = r
        lastOpaque = r
      }
    }

    if (firstOpaque > 0 && lastOpaque > 0 && lastOpaque > firstOpaque) {
      innerSamples.push(firstOpaque)
      outerSamples.push(lastOpaque)
    }
  }

  if (innerSamples.length < 16 || outerSamples.length < 16) {
    return EXTERNAL_FRAME_FALLBACK
  }

  const innerRadius = quantile(innerSamples, 0.5)
  // Для внешнего радиуса берём lower-quantile, чтобы игнорировать длинные "шипы".
  const outerRadius = quantile(outerSamples, 0.3)
  if (innerRadius <= 0 || outerRadius <= 0 || innerRadius >= outerRadius) {
    return EXTERNAL_FRAME_FALLBACK
  }

  const outerNorm = outerRadius / maxRadius
  const innerNorm = innerRadius / maxRadius
  if (!Number.isFinite(outerNorm) || !Number.isFinite(innerNorm) || outerNorm <= 0) {
    return EXTERNAL_FRAME_FALLBACK
  }

  const frameScale = clamp(1 / outerNorm, 1, 1.75)
  const avatarRadiusRatio = clamp((innerNorm / outerNorm) * 0.97, 0.5, 0.92)
  const avatarInsetPercent = clamp((1 - avatarRadiusRatio) * 50, 3, 12)

  return {
    avatarInsetPercent: Number(avatarInsetPercent.toFixed(2)),
    frameScale: Number(frameScale.toFixed(3)),
  }
}

function loadExternalFrameMetrics(frameUrl) {
  if (!frameUrl) {
    return Promise.resolve(EXTERNAL_FRAME_FALLBACK)
  }

  if (frameMetricsCache.has(frameUrl)) {
    return frameMetricsCache.get(frameUrl)
  }

  const promise = new Promise((resolve) => {
    const image = new Image()
    image.crossOrigin = 'anonymous'
    image.onload = () => {
      try {
        const width = image.naturalWidth || image.width
        const height = image.naturalHeight || image.height
        if (!width || !height) {
          resolve(EXTERNAL_FRAME_FALLBACK)
          return
        }

        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const ctx = canvas.getContext('2d', { willReadFrequently: true })
        if (!ctx) {
          resolve(EXTERNAL_FRAME_FALLBACK)
          return
        }
        ctx.drawImage(image, 0, 0)
        const imageData = ctx.getImageData(0, 0, width, height)
        resolve(detectExternalFrameMetrics(imageData, width, height))
      } catch (_) {
        resolve(EXTERNAL_FRAME_FALLBACK)
      }
    }
    image.onerror = () => resolve(EXTERNAL_FRAME_FALLBACK)
    image.src = frameUrl
  })

  frameMetricsCache.set(frameUrl, promise)
  return promise
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
  const frameAssetUrls = useMemo(() => resolveFrameAssetUrls(resolvedFrameKey), [resolvedFrameKey])
  const [frameCandidateIndex, setFrameCandidateIndex] = useState(0)
  const frameAssetUrl = frameAssetUrls[frameCandidateIndex] || null
  const [externalFrameMeta, setExternalFrameMeta] = useState(EXTERNAL_FRAME_FALLBACK)
  const [avatarBroken, setAvatarBroken] = useState(false)
  const [frameBroken, setFrameBroken] = useState(false)
  const hasFrameAsset = Boolean(frameAssetUrl && !frameBroken)
  const frameWidth = Math.max(3, size * 0.06)
  const frameBleedPercent = hasFrameAsset
    ? Math.max(0, Number((((externalFrameMeta?.frameScale || 1) - 1) * 50).toFixed(2)))
    : 0

  useEffect(() => {
    setAvatarBroken(false)
  }, [resolvedPhotoUrl])

  useEffect(() => {
    setFrameBroken(false)
    setFrameCandidateIndex(0)
  }, [resolvedFrameKey])

  useEffect(() => {
    setFrameBroken(false)
  }, [frameAssetUrl])

  useEffect(() => {
    let isCancelled = false
    if (!frameAssetUrl) {
      setExternalFrameMeta(EXTERNAL_FRAME_FALLBACK)
      return () => {
        isCancelled = true
      }
    }

    setExternalFrameMeta(EXTERNAL_FRAME_FALLBACK)
    loadExternalFrameMetrics(frameAssetUrl)
      .then((meta) => {
        if (!isCancelled) {
          setExternalFrameMeta(meta || EXTERNAL_FRAME_FALLBACK)
        }
      })
      .catch(() => {
        if (!isCancelled) {
          setExternalFrameMeta(EXTERNAL_FRAME_FALLBACK)
        }
      })

    return () => {
      isCancelled = true
    }
  }, [frameAssetUrl])

  return (
    <div className={`relative ${className}`} style={{ width: size, height: size, overflow: 'visible' }}>
      {showGlow && (
        <div className={`absolute -inset-2 rounded-full bg-gradient-to-r ${frameStyle.glow} blur-lg opacity-50`} />
      )}

      <motion.div
        className="relative w-full h-full"
        style={{ overflow: 'visible' }}
        animate={animated ? { rotate: 360 } : {}}
        transition={animated ? {
          duration: 3,
          repeat: Infinity,
          ease: 'linear'
        } : {}}
      >
        {hasFrameAsset ? (
          <>
            <div
              className="absolute rounded-full bg-gradient-to-br from-game-primary to-purple-700 overflow-hidden flex items-center justify-center"
              style={{ inset: `${externalFrameMeta.avatarInsetPercent}%` }}
            >
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
              className="absolute object-contain pointer-events-none"
              style={{
                inset: `-${frameBleedPercent}%`,
                width: `${100 + frameBleedPercent * 2}%`,
                height: `${100 + frameBleedPercent * 2}%`,
                maxWidth: 'none',
                maxHeight: 'none',
              }}
              onError={() => {
                if (frameCandidateIndex < frameAssetUrls.length - 1) {
                  setFrameCandidateIndex((prev) => prev + 1)
                  return
                }
                setFrameBroken(true)
              }}
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
