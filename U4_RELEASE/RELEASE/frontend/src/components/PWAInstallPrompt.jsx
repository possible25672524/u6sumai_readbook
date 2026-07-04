import { useState, useEffect } from 'react'
import { getPWAInstallPrompt } from '../main'

export default function PWAInstallPrompt() {
  const [installable, setInstallable] = useState(false)
  const [installed, setInstalled] = useState(false)
  const [dismissed, setDismissed] = useState(
    () => localStorage.getItem('pwa-prompt-dismissed') === '1',
  )

  useEffect(() => {
    const handleInstallable = () => setInstallable(true)
    const handleInstalled = () => { setInstallable(false); setInstalled(true) }

    window.addEventListener('pwa-installable', handleInstallable)
    window.addEventListener('pwa-installed', handleInstalled)

    // If prompt was already captured before this component mounted
    if (getPWAInstallPrompt?.()) setInstallable(true)

    return () => {
      window.removeEventListener('pwa-installable', handleInstallable)
      window.removeEventListener('pwa-installed', handleInstalled)
    }
  }, [])

  const handleInstall = async () => {
    const prompt = getPWAInstallPrompt?.()
    if (!prompt) return
    prompt.prompt()
    const { outcome } = await prompt.userChoice
    if (outcome === 'accepted') {
      setInstallable(false)
      setInstalled(true)
    }
  }

  const handleDismiss = () => {
    setDismissed(true)
    localStorage.setItem('pwa-prompt-dismissed', '1')
  }

  if (!installable || dismissed || installed) return null

  return (
    <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 w-full max-w-sm px-4">
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xl p-4 flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-xl flex-shrink-0">
          🎓
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-slate-900">ติดตั้ง AI Study App</p>
          <p className="text-xs text-slate-500 mt-0.5">ใช้งานได้เร็วขึ้น แม้ออฟไลน์</p>
        </div>
        <div className="flex gap-1.5 flex-shrink-0">
          <button
            onClick={handleDismiss}
            className="px-2 py-1.5 text-xs text-slate-500 hover:text-slate-700 rounded-lg hover:bg-slate-100"
          >
            ไม่ตอนนี้
          </button>
          <button
            onClick={handleInstall}
            className="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 font-medium"
          >
            ติดตั้ง
          </button>
        </div>
      </div>
    </div>
  )
}
