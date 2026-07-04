import { apiClient } from './client'

export const plannerApi = {
  getPlan: () => apiClient.get('/study-plan'),
  createPlan: (payload) => apiClient.post('/study-plan', payload), // payload: { exam_date, topics }
  markItemDone: (itemId) => apiClient.patch(`/study-plan/items/${itemId}`, { status: 'done' }),
}
