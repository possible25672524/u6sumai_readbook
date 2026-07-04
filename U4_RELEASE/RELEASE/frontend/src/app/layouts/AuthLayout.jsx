import { Outlet, Navigate } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'

export default function AuthLayout() {
  const { isAuthenticated } = useAuthStore()

  // Already logged in → go to dashboard
  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 px-4 py-8">
      {/* Background decoration */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-80 h-80 rounded-full bg-indigo-600/10 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-purple-600/10 blur-3xl" />
      </div>

      <div className="relative w-full max-w-sm">
        {/* Logo / Brand */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 shadow-lg mb-3">
            <span className="text-2xl">🎓</span>
          </div>
          <h1 className="text-2xl font-bold text-white tracking-tight">AI Study Assistant</h1>
          <p className="text-indigo-300 text-sm mt-1">ผู้ช่วยเตรียมสอบด้วย AI</p>
        </div>

        {/* Card */}
        <div className="bg-white rounded-2xl shadow-2xl p-6">
          <Outlet />
        </div>

        <p className="text-center text-xs text-slate-500 mt-4">
          © {new Date().getFullYear()} AI Study Assistant · ระบบปลอดภัยด้วย Laravel Sanctum
        </p>
      </div>
    </div>
  )
}
