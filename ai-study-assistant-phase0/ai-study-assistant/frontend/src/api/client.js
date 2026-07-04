import axios from 'axios'
import { useAuthStore } from '../store/authStore'

// ทุก request ไปยัง Laravel API ผ่านที่นี่จุดเดียว
// ใน dev: Vite proxy /api -> http://backend:8000 (ดู vite.config.js)
// ใน prod: ตั้ง VITE_API_BASE_URL เป็น URL จริงของ API
export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  withCredentials: true, // จำเป็นสำหรับ Sanctum SPA cookie-based auth
  headers: {
    Accept: 'application/json',
  },
})

// แนบ Bearer token (ถ้าใช้ token-based แทน cookie-based) จาก authStore
apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// จัดการ 401 กลาง ๆ: ถ้า token หมดอายุ ให้ logout และเด้งไปหน้า login
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
    }
    return Promise.reject(error)
  },
)
