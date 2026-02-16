import { useState } from 'react'

export const TicketIcon = ({ size = 20, className = '' }) => {
  const [failed, setFailed] = useState(false)

  if (failed) {
    return (
      <span
        className={`inline-flex items-center justify-center ${className}`}
        style={{ width: `${size}px`, height: `${size}px` }}
      >
        🎫
      </span>
    )
  }

  return (
    <img
      src="/api/images/shop/tickets.png"
      alt="tickets"
      className={`inline-block ${className}`}
      style={{ width: `${size}px`, height: `${size}px` }}
      onError={() => setFailed(true)}
    />
  )
}

export default TicketIcon
