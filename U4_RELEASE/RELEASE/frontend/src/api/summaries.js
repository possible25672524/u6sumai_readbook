import { apiClient } from './client'

export const summariesApi = {
  generate: (documentId, payload) =>
    apiClient.post(`/documents/${documentId}/summaries`, payload), // payload: { type: 'short'|'detailed'|'bullet'|'exam'|'mindmap'|'table'|'keypoints' }
  list: (documentId) => apiClient.get(`/documents/${documentId}/summaries`),
}
