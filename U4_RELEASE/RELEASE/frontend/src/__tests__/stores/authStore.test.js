/**
 * authStore tests
 * Run with: vitest
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'

// Reset module between tests to get a clean store
vi.mock('zustand/middleware', () => ({
  persist: (fn) => fn,
}))

describe('authStore', () => {
  let useAuthStore

  beforeEach(async () => {
    vi.resetModules()
    const mod = await import('../store/authStore')
    useAuthStore = mod.useAuthStore
    // Reset to initial state
    useAuthStore.setState({ user: null, token: null, isAuthenticated: false, initializing: true })
  })

  it('should have correct initial state', () => {
    const state = useAuthStore.getState()
    expect(state.user).toBeNull()
    expect(state.token).toBeNull()
    expect(state.isAuthenticated).toBe(false)
    expect(state.initializing).toBe(true)
  })

  it('setSession should authenticate user', () => {
    const user = { id: 1, name: 'Test User', email: 'test@example.com', role: 'student' }
    const token = 'test-token-123'
    useAuthStore.getState().setSession(user, token)
    const state = useAuthStore.getState()
    expect(state.user).toEqual(user)
    expect(state.token).toBe(token)
    expect(state.isAuthenticated).toBe(true)
    expect(state.initializing).toBe(false)
  })

  it('logout should clear session', () => {
    useAuthStore.getState().setSession({ id: 1, name: 'Test', role: 'student' }, 'token')
    useAuthStore.getState().logout()
    const state = useAuthStore.getState()
    expect(state.user).toBeNull()
    expect(state.token).toBeNull()
    expect(state.isAuthenticated).toBe(false)
  })

  it('hasRole should return true for matching role', () => {
    useAuthStore.getState().setSession({ id: 1, name: 'Admin', role: 'admin' }, 'token')
    expect(useAuthStore.getState().hasRole('admin')).toBe(true)
    expect(useAuthStore.getState().hasRole('admin', 'teacher')).toBe(true)
  })

  it('hasRole should return false for non-matching role', () => {
    useAuthStore.getState().setSession({ id: 1, name: 'Student', role: 'student' }, 'token')
    expect(useAuthStore.getState().hasRole('admin')).toBe(false)
    expect(useAuthStore.getState().hasRole('teacher')).toBe(false)
  })

  it('hasRole should return false when not authenticated', () => {
    expect(useAuthStore.getState().hasRole('admin')).toBe(false)
  })

  it('isAdmin should return true for admin role', () => {
    useAuthStore.getState().setSession({ id: 1, name: 'Admin', role: 'admin' }, 'token')
    expect(useAuthStore.getState().isAdmin()).toBe(true)
  })

  it('updateUser should update user without losing token', () => {
    useAuthStore.getState().setSession({ id: 1, name: 'Old Name', role: 'student' }, 'my-token')
    useAuthStore.getState().updateUser({ id: 1, name: 'New Name', role: 'student' })
    const state = useAuthStore.getState()
    expect(state.user.name).toBe('New Name')
    expect(state.token).toBe('my-token')
  })
})
