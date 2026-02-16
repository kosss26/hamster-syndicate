import { useState } from 'react'

export default function ModeTrueFalseIcon({ size = 24, className = '' }) {
  const [failed, setFailed] = useState(false)

  if (failed) {
    return (
      <span
        className={`inline-flex items-center justify-center ${className}`}
        style={{ width: `${size}px`, height: `${size}px` }}
      >
        🧠
      </span>
    )
  }

  return (
    <img
      src="/api/images/ui/mode_truefalse.png"
      alt="true false mode"
      className={`inline-block ${className}`}
      style={{ width: `${size}px`, height: `${size}px` }}
      onError={() => setFailed(true)}
    />
  )
}
