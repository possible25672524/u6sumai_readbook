import { apiClient } from './client'

export const chatbotApi = {
  listSessions: () => apiClient.get('/chat/sessions'),
  createSession: () => apiClient.post('/chat/sessions'),
  sendMessage: (sessionId, payload) => apiClient.post(`/chat/sessions/${sessionId}/messages`, payload), // RAG chat
  quickAnswer: (payload) => apiClient.post('/chat/quick-answer', payload), // โหมดตอบด่วน ไม่ผูก session
}
