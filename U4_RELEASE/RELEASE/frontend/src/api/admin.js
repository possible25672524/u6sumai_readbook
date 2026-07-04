import { apiClient } from './client'

export const adminApi = {
  // Users
  listUsers:    (params) => apiClient.get('/admin/users', { params }),
  getUser:      (id)     => apiClient.get(`/admin/users/${id}`),
  updateUser:   (id, payload) => apiClient.put(`/admin/users/${id}`, payload),
  deleteUser:   (id)     => apiClient.delete(`/admin/users/${id}`),

  // Documents
  listDocuments: (params) => apiClient.get('/admin/documents', { params }),
  deleteDocument: (id)   => apiClient.delete(`/admin/documents/${id}`),

  // Processing queue
  listJobs:      (params) => apiClient.get('/admin/jobs', { params }),
  retryJob:      (id)     => apiClient.post(`/admin/jobs/${id}/retry`),

  // Logs
  listLogs:      (params) => apiClient.get('/admin/activity-logs', { params }),

  // Stats
  stats:         () => apiClient.get('/admin/stats'),
}
