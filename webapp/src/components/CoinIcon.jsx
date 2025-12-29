export const CoinIcon = ({ size = 20, className = '' }) => {
  return (
    <img 
      src="/api/images/shop/coins.png" 
      alt="coins" 
      className={`inline-block ${className}`}
      style={{ width: `${size}px`, height: `${size}px` }}
    />
  )
}

export default CoinIcon

