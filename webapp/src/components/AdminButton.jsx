import { useEffect, useState } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { motion } from 'framer-motion'
import api from '../api/client'

function AdminButton() {
  const [isAdmin, setIsAdmin] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    const checkAdmin = async () => {
      try {
        const response = await api.isAdmin()
        if (response.success) {
          setIsAdmin(response.data.is_admin)
        }
      } catch (err) {
        // Silently fail if not admin or error
      }
    }
    checkAdmin()
  }, [])

  if (!isAdmin) return null

  // Don't show on admin page itself
  if (location.pathname === '/admin') return null

  return (
    <motion.button
      initial={{ opacity: 0, scale: 0.8 }}
      animate={{ opacity: 1, scale: 1 }}
      whileHover={{ scale: 1.1 }}
      whileTap={{ scale: 0.9 }}
      onClick={() => navigate('/admin')}
      className="fixed top-24 right-4 z-[9999] w-10 h-10 bg-red-500/20 border border-red-500/50 rounded-full flex items-center justify-center text-red-400 backdrop-blur-md shadow-lg text-lg safe-top-margin pointer-events-auto"
    >
      🛠
    </motion.button>
  )
}

export default AdminButton
