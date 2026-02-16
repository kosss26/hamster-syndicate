import { useState } from 'react'

export default function ModeDuelIcon({ size = 24, className = '' }) {
  const [failed, setFailed] = useState(false)

  if (failed) {
    return (
      <span
        className={`inline-flex items-center justify-center ${className}`}
        style={{ width: `${size}px`, height: `${size}px` }}
      >
        ⚔️
      </span>
    )
  }

  return (
    <img
      src="/api/images/ui/mode_duel.png"
      alt="duel mode"
      className={`inline-block ${className}`}
      style={{ width: `${size}px`, height: `${size}px` }}
      onError={() => setFailed(true)}
    />
  )
}
