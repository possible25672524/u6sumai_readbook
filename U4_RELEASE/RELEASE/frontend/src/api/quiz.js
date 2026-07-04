import { apiClient } from './client'

export const quizApi = {
  generate: (documentId, payload) => apiClient.post(`/documents/${documentId}/questions/generate`, payload),
  listQuizzes: () => apiClient.get('/quizzes'),
  createQuiz: (payload) => apiClient.post('/quizzes', payload),
  startAttempt: (quizId) => apiClient.post(`/quizzes/${quizId}/attempts`),
  submitAttempt: (attemptId, payload) => apiClient.post(`/quiz-attempts/${attemptId}/submit`, payload),
  getResult: (attemptId) => apiClient.get(`/quiz-attempts/${attemptId}`),
}
