import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useTelegram, showBackButton } from '../hooks/useTelegram'
import api from '../api/client'

function ProfilePage() {
  const { user } = useTelegram()
  const navigate = useNavigate()
  const [profile, setProfile] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [debugInfo, setDebugInfo] = useState('')

  useEffect(() => {
    showBackButton(true)
    
    // Debug info сразу при загрузке
    const initData = window.Telegram?.WebApp?.initData || ''
    const tgUser = window.Telegram?.WebApp?.initDataUnsafe?.user
    setDebugInfo(`initData: ${initData.length} chars, user: ${tgUser?.first_name || 'none'}`)
    
    loadProfile()
  }, [])

  const loadProfile = async () => {
    try {
      setLoading(true)
      setError(null)
      
      const response = await api.getProfile()
      
      if (response.success) {
        setProfile(response.data)
      } else {
        setError(response.error || 'Ошибка API')
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  // Всегда показываем что-то
  return (
    <div style={{ 
      minHeight: '100vh', 
      background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
      padding: '20px',
      color: 'white'
    }}>
      <h1 style={{ fontSize: '24px', marginBottom: '16px', textAlign: 'center' }}>
        📊 Профиль
      </h1>
      
      <div style={{ 
        background: 'rgba(255,255,255,0.1)', 
        padding: '16px', 
        borderRadius: '12px',
        marginBottom: '16px'
      }}>
        <p style={{ fontSize: '12px', opacity: 0.7, marginBottom: '8px' }}>Debug:</p>
        <p style={{ fontSize: '11px', wordBreak: 'break-all' }}>{debugInfo}</p>
      </div>

      {loading && (
        <div style={{ textAlign: 'center', padding: '40px' }}>
          <div style={{ 
            width: '40px', 
            height: '40px', 
            border: '3px solid rgba(255,255,255,0.3)',
            borderTopColor: '#6366f1',
            borderRadius: '50%',
            animation: 'spin 1s linear infinite',
            margin: '0 auto 16px'
          }} />
          <p>Загрузка...</p>
        </div>
      )}

      {error && (
        <div style={{ 
          background: 'rgba(239,68,68,0.2)', 
          padding: '16px', 
          borderRadius: '12px',
          marginBottom: '16px',
          border: '1px solid rgba(239,68,68,0.5)'
        }}>
          <p style={{ color: '#ef4444' }}>Ошибка: {error}</p>
          <button 
            onClick={loadProfile}
            style={{
              marginTop: '12px',
              padding: '8px 16px',
              background: '#6366f1',
              border: 'none',
              borderRadius: '8px',
              color: 'white',
              cursor: 'pointer'
            }}
          >
            Повторить
          </button>
        </div>
      )}

      {profile && (
        <div>
          <div style={{ 
            background: 'rgba(255,255,255,0.1)', 
            padding: '20px', 
            borderRadius: '16px',
            marginBottom: '16px',
            textAlign: 'center'
          }}>
            <div style={{
              width: '80px',
              height: '80px',
              borderRadius: '50%',
              background: 'linear-gradient(135deg, #6366f1, #9333ea)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '32px',
              fontWeight: 'bold',
              margin: '0 auto 12px'
            }}>
              {user?.first_name?.[0] || '?'}
            </div>
            <h2 style={{ fontSize: '20px', marginBottom: '4px' }}>
              {user?.first_name} {user?.last_name || ''}
            </h2>
            {user?.username && (
              <p style={{ opacity: 0.6, fontSize: '14px' }}>@{user.username}</p>
            )}
          </div>

          <div style={{ 
            display: 'grid', 
            gridTemplateColumns: '1fr 1fr', 
            gap: '12px',
            marginBottom: '16px'
          }}>
            <div style={{ 
              background: 'rgba(255,255,255,0.1)', 
              padding: '16px', 
              borderRadius: '12px',
              textAlign: 'center'
            }}>
              <p style={{ fontSize: '28px', fontWeight: 'bold', color: '#6366f1' }}>
                {profile.rating}
              </p>
              <p style={{ fontSize: '12px', opacity: 0.6 }}>Рейтинг</p>
            </div>
            <div style={{ 
              background: 'rgba(255,255,255,0.1)', 
              padding: '16px', 
              borderRadius: '12px',
              textAlign: 'center'
            }}>
              <p style={{ fontSize: '28px', fontWeight: 'bold', color: '#22c55e' }}>
                {profile.stats?.duel_wins || 0}
              </p>
              <p style={{ fontSize: '12px', opacity: 0.6 }}>Победы</p>
            </div>
          </div>

          <div style={{ 
            background: 'rgba(255,255,255,0.1)', 
            padding: '16px', 
            borderRadius: '12px'
          }}>
            <p style={{ fontSize: '14px', marginBottom: '8px' }}>Ранг: <b>{typeof profile.rank === 'object' ? `${profile.rank.emoji || ''} ${profile.rank.name || ''}` : profile.rank}</b></p>
            <p style={{ fontSize: '14px', marginBottom: '8px' }}>Монеты: <b>{profile.coins}</b></p>
            <p style={{ fontSize: '14px' }}>Рекорд П/Л: <b>{profile.true_false_record}</b></p>
          </div>
        </div>
      )}

      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  )
}

export default ProfilePage
