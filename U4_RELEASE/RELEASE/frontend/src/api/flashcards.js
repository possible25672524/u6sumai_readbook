import { apiClient } from './client'

export const flashcardsApi = {
  generate: (documentId) => apiClient.post(`/documents/${documentId}/flashcards`),
  listSets: () => apiClient.get('/flashcard-sets'),
  getSet: (setId) => apiClient.get(`/flashcard-sets/${setId}`),
  review: (cardId, payload) => apiClient.post(`/flashcards/${cardId}/review`, payload), // payload: { result: 'again'|'good'|'easy' }
}
