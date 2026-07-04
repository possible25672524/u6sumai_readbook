import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './app/App.jsx'
import './index.css'

// Register service worker (VitePWA handles this via registerType: 'autoUpdate')
// The SW is registered automatically by vite-plugin-pwa — no manual registration needed.
// This file handles the install prompt capture only.

// Capture beforeinstallprompt event for custom install prompt
let deferredPrompt = null
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault()
  deferredPrompt = e
  // Dispatch custom event so React components can react
  window.dispatchEvent(new CustomEvent('pwa-installable', { detail: e }))
})

window.addEventListener('appinstalled', () => {
  deferredPrompt = null
  window.dispatchEvent(new CustomEvent('pwa-installed'))
})

// Export for use in React components
export function getPWAInstallPrompt() { return deferredPrompt }

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </StrictMode>,
)
