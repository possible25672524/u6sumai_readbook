import { apiClient } from './client'

export const documentsApi = {
  list: (params) => apiClient.get('/documents', { params }),
  get: (id) => apiClient.get(`/documents/${id}`),
  upload: (formData, onUploadProgress) =>
    apiClient.post('/documents', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress,
    }),
  importFromUrl: (payload) => apiClient.post('/documents/import-url', payload), // Google Drive / YouTube
  delete: (id) => apiClient.delete(`/documents/${id}`),
  processingStatus: (id) => apiClient.get(`/documents/${id}/status`),
}
