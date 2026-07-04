/**
 * ProtectedRoute tests
 * Run with: vitest
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import ProtectedRoute from '../../app/ProtectedRoute'
import { useAuthStore } from '../../store/authStore'

// Mock the store
vi.mock('../store/authStore')

const TestChild = () => <div data-testid="protected-content">Protected Content</div>
const LoginPage = () => <div data-testid="login-page">Login</div>
const UnauthorizedPage = () => <div data-testid="unauthorized">Unauthorized</div>

function renderWithRouter(isAuthenticated = false, role = 'student', requiredRoles = null) {
  useAuthStore.mockReturnValue({ isAuthenticated, user: { role } })
  // Also mock getState for direct calls
  useAuthStore.getState = () => ({ isAuthenticated, user: { role } })

  return render(
    <MemoryRouter initialEntries={['/protected']}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/unauthorized" element={<UnauthorizedPage />} />
        <Route element={<ProtectedRoute roles={requiredRoles} />}>
          <Route path="/protected" element={<TestChild />} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders children when authenticated (no role requirement)', () => {
    renderWithRouter(true, 'student')
    expect(screen.getByTestId('protected-content')).toBeTruthy()
  })

  it('redirects to /login when not authenticated', () => {
    renderWithRouter(false, null)
    expect(screen.getByTestId('login-page')).toBeTruthy()
    expect(screen.queryByTestId('protected-content')).toBeNull()
  })

  it('renders children when user has required role', () => {
    renderWithRouter(true, 'admin', ['admin'])
    expect(screen.getByTestId('protected-content')).toBeTruthy()
  })

  it('redirects to /unauthorized when role does not match', () => {
    renderWithRouter(true, 'student', ['admin'])
    expect(screen.getByTestId('unauthorized')).toBeTruthy()
    expect(screen.queryByTestId('protected-content')).toBeNull()
  })

  it('allows teacher role when teacher is in allowed list', () => {
    renderWithRouter(true, 'teacher', ['admin', 'teacher'])
    expect(screen.getByTestId('protected-content')).toBeTruthy()
  })
})
