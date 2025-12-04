import { useState, useEffect } from 'react'
import { useTelegram, showBackButton } from '../hooks/useTelegram'
import api from '../api/client'

function LeaderboardPage() {
  const { user } = useTelegram()
  const [activeTab, setActiveTab] = useState('duel')
  const [leaderboard, setLeaderboard] = useState({ duel: [], truefalse: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    showBackButton(true)
    loadLeaderboard('duel')
    loadLeaderboard('truefalse')
  }, [])

  const loadLeaderboard = async (type) => {
    try {
      const response = await api.getLeaderboard(type)
      if (response.success) {
        setLeaderboard(prev => ({
          ...prev,
          [type]: response.data.players || []
        }))
      } else {
        setError(response.error)
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const data = activeTab === 'duel' ? leaderboard.duel : leaderboard.truefalse

  return (
    <div style={{ 
      minHeight: '100vh', 
      background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
      padding: '20px',
      color: 'white'
    }}>
      <h1 style={{ fontSize: '24px', marginBottom: '8px', textAlign: 'center' }}>
        🏆 Рейтинг
      </h1>
      <p style={{ textAlign: 'center', opacity: 0.6, marginBottom: '20px', fontSize: '14px' }}>
        Лучшие игроки
      </p>

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '20px' }}>
        <button
          onClick={() => setActiveTab('duel')}
          style={{
            flex: 1,
            padding: '12px',
            borderRadius: '12px',
            border: 'none',
            background: activeTab === 'duel' ? '#6366f1' : 'rgba(255,255,255,0.1)',
            color: 'white',
            fontWeight: '500',
            cursor: 'pointer'
          }}
        >
          ⚔️ Дуэли
        </button>
        <button
          onClick={() => setActiveTab('truefalse')}
          style={{
            flex: 1,
            padding: '12px',
            borderRadius: '12px',
            border: 'none',
            background: activeTab === 'truefalse' ? '#6366f1' : 'rgba(255,255,255,0.1)',
            color: 'white',
            fontWeight: '500',
            cursor: 'pointer'
          }}
        >
          🧠 П/Л
        </button>
      </div>

      {loading && (
        <div style={{ textAlign: 'center', padding: '40px' }}>
          <p>Загрузка...</p>
        </div>
      )}

      {error && (
        <div style={{ 
          background: 'rgba(239,68,68,0.2)', 
          padding: '16px', 
          borderRadius: '12px',
          marginBottom: '16px'
        }}>
          <p style={{ color: '#ef4444' }}>Ошибка: {error}</p>
        </div>
      )}

      {!loading && data.length === 0 && (
        <div style={{ textAlign: 'center', padding: '40px', opacity: 0.6 }}>
          <p>Пока нет данных</p>
        </div>
      )}

      {/* Leaderboard List */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
        {data.map((player, index) => (
          <div
            key={player.username || index}
            style={{
              background: player.username === user?.username 
                ? 'rgba(99,102,241,0.3)' 
                : 'rgba(255,255,255,0.1)',
              padding: '12px 16px',
              borderRadius: '12px',
              display: 'flex',
              alignItems: 'center',
              gap: '12px',
              border: player.username === user?.username ? '2px solid #6366f1' : 'none'
            }}
          >
            {/* Position */}
            <div style={{
              width: '36px',
              height: '36px',
              borderRadius: '8px',
              background: player.position === 1 ? 'linear-gradient(135deg, #fbbf24, #f59e0b)' :
                         player.position === 2 ? 'linear-gradient(135deg, #9ca3af, #6b7280)' :
                         player.position === 3 ? 'linear-gradient(135deg, #cd7f32, #b8860b)' :
                         'rgba(255,255,255,0.1)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontWeight: 'bold',
              fontSize: '14px',
              color: player.position <= 3 ? '#000' : '#fff'
            }}>
              {player.position === 1 ? '🥇' : 
               player.position === 2 ? '🥈' : 
               player.position === 3 ? '🥉' : player.position}
            </div>

            {/* Name */}
            <div style={{ flex: 1, minWidth: 0 }}>
              <p style={{ 
                fontWeight: '500', 
                whiteSpace: 'nowrap', 
                overflow: 'hidden', 
                textOverflow: 'ellipsis' 
              }}>
                {player.name}
                {player.username === user?.username && (
                  <span style={{ color: '#6366f1', marginLeft: '8px', fontSize: '12px' }}>• Ты</span>
                )}
              </p>
              <p style={{ fontSize: '12px', opacity: 0.6 }}>@{player.username}</p>
            </div>

            {/* Score */}
            <div style={{ textAlign: 'right' }}>
              <p style={{ 
                fontWeight: 'bold', 
                color: activeTab === 'duel' ? '#6366f1' : '#a855f7' 
              }}>
                {activeTab === 'duel' ? player.rating : player.record}
              </p>
              <p style={{ fontSize: '11px', opacity: 0.6 }}>
                {activeTab === 'duel' ? (typeof player.rank === 'object' ? player.rank.name : player.rank?.split?.(' ')[0] || '') : 'серия'}
              </p>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

export default LeaderboardPage
