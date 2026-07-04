import { apiClient } from './client'

export const authApi = {
  login: (payload) => apiClient.post('/auth/login', payload),
  register: (payload) => apiClient.post('/auth/register', payload),
  logout: () => apiClient.post('/auth/logout'),
  me: () => apiClient.get('/auth/me'),
  forgotPassword: (payload) => apiClient.post('/auth/forgot-password', payload),
  resetPassword: (payload) => apiClient.post('/auth/reset-password', payload),
}
