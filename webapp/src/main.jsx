import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

// Глобальный отлов ошибок
window.onerror = function(message, source, lineno, colno, error) {
  const errorDiv = document.createElement('div')
  errorDiv.style.cssText = 'position:fixed;top:0;left:0;right:0;background:red;color:white;padding:20px;z-index:99999;font-size:12px;'
  errorDiv.innerHTML = `<b>JS Error:</b><br>${message}<br><small>${source}:${lineno}</small>`
  document.body.appendChild(errorDiv)
}

window.onunhandledrejection = function(event) {
  const errorDiv = document.createElement('div')
  errorDiv.style.cssText = 'position:fixed;top:0;left:0;right:0;background:orange;color:black;padding:20px;z-index:99999;font-size:12px;'
  errorDiv.innerHTML = `<b>Promise Error:</b><br>${event.reason}`
  document.body.appendChild(errorDiv)
}

// Error Boundary компонент
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false, error: null }
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error }
  }

  componentDidCatch(error, errorInfo) {
    console.error('React Error:', error, errorInfo)
  }

  render() {
    if (this.state.hasError) {
      return (
        <div style={{
          minHeight: '100vh',
          background: '#1a1a2e',
          color: 'white',
          padding: '20px',
          fontFamily: 'monospace'
        }}>
          <h1 style={{ color: '#ef4444' }}>⚠️ Ошибка React</h1>
          <pre style={{ 
            background: 'rgba(255,255,255,0.1)', 
            padding: '16px', 
            borderRadius: '8px',
            overflow: 'auto',
            fontSize: '11px'
          }}>
            {this.state.error?.toString()}
          </pre>
          <button 
            onClick={() => window.location.reload()}
            style={{
              marginTop: '20px',
              padding: '12px 24px',
              background: '#6366f1',
              border: 'none',
              borderRadius: '8px',
              color: 'white',
              cursor: 'pointer'
            }}
          >
            Перезагрузить
          </button>
        </div>
      )
    }

    return this.props.children
  }
}

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ErrorBoundary>
      <App />
    </ErrorBoundary>
  </React.StrictMode>,
)
