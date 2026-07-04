import { Navigate, Outlet } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'

// ใช้ครอบ route ที่ต้อง login ก่อนเข้า
// roles: ['admin'] หรือ ['admin','teacher'] เพื่อจำกัดสิทธิ์เฉพาะ role ที่กำหนด
export default function ProtectedRoute({ roles }) {
  const { isAuthenticated, user } = useAuthStore()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (roles && !roles.includes(user?.role)) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}
