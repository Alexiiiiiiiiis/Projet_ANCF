import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App'
import { getInitialTheme, applyTheme } from './hooks/useTheme'

// Applique le thème avant le premier rendu pour éviter un flash blanc
applyTheme(getInitialTheme())

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>
)
