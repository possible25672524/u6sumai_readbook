import { useEffect } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import { authApi } from '../api/auth'
import { Spinner } from '../components/ui'

const PUBLIC_PATHS = ['/login', '/register', '/forgot-password', '/reset-password']

export default function AuthProvider({ children }) {
  const { token, isAuthenticated, initializing, setSession, logout, setInitializing } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    const isPublic = PUBLIC_PATHS.some((p) => location.pathname.startsWith(p))

    if (!token) {
      setInitializing(false)
      if (!isPublic) navigate('/login', { replace: true })
      return
    }

    // Verify token is still valid by fetching /auth/me
    authApi.me()
      .then(({ data }) => {
        setSession(data, token)
      })
      .catch(() => {
        logout()
        if (!isPublic) navigate('/login', { replace: true })
      })
  // Only run once on mount
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  if (initializing) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <div className="flex flex-col items-center gap-3">
          <Spinner size="lg" />
          <p className="text-sm text-slate-500">กำลังโหลด...</p>
        </div>
      </div>
    )
  }

  return children
}
