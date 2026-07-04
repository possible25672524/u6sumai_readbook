import { apiClient } from './client'

export const analyticsApi = {
  dashboard: () => apiClient.get('/analytics/dashboard'),
  studyTime: (params) => apiClient.get('/analytics/study-time', { params }),
  examPrediction: (documentId) => apiClient.get(`/documents/${documentId}/exam-prediction`),
}
