/**
 * DashboardPage tests
 * Run with: vitest
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import DashboardPage from '../../features/dashboard/pages/DashboardPage'

// Mock API
vi.mock('../../api/analytics', () => ({
  analyticsApi: {
    dashboard: vi.fn(),
  },
}))

// Mock authStore
vi.mock('../../store/authStore', () => ({
  useAuthStore: vi.fn((selector) =>
    selector({ user: { id: 1, name: 'กรกวิน ทดสอบ', role: 'student' } }),
  ),
}))

import { analyticsApi } from '../../api/analytics'

const MOCK_DASHBOARD = {
  stats: {
    total_documents: 5,
    study_minutes_today: 45,
    quizzes_completed: 12,
    flashcards_due: 8,
  },
  weekly_study_minutes: [
    { label: 'จ', value: 30 }, { label: 'อ', value: 60 },
    { label: 'พ', value: 0 }, { label: 'พฤ', value: 90 },
    { label: 'ศ', value: 20 }, { label: 'ส', value: 45 }, { label: 'อา', value: 0 },
  ],
  recent_documents: [
    { id: 1, title: 'เอกสารชีววิทยา', processing_status: 'done' },
  ],
  upcoming_plan_items: [],
  recent_quiz_attempts: [],
}

function renderDashboard() {
  return render(
    <MemoryRouter>
      <DashboardPage />
    </MemoryRouter>,
  )
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows loading spinner initially', () => {
    analyticsApi.dashboard.mockReturnValue(new Promise(() => {})) // never resolves
    renderDashboard()
    expect(screen.getByRole('status', { hidden: true }) || document.querySelector('svg.animate-spin')).toBeTruthy()
  })

  it('renders greeting and user name after load', async () => {
    analyticsApi.dashboard.mockResolvedValue({ data: MOCK_DASHBOARD })
    renderDashboard()
    await waitFor(() => {
      // Greeting should contain part of user name
      expect(screen.getByText(/กรกวิน/)).toBeTruthy()
    })
  })

  it('displays stat cards with correct values', async () => {
    analyticsApi.dashboard.mockResolvedValue({ data: MOCK_DASHBOARD })
    renderDashboard()
    await waitFor(() => {
      expect(screen.getByText('5')).toBeTruthy()   // total_documents
      expect(screen.getByText('45')).toBeTruthy()  // study_minutes_today
      expect(screen.getByText('12')).toBeTruthy()  // quizzes_completed
      expect(screen.getByText('8')).toBeTruthy()   // flashcards_due
    })
  })

  it('shows recent document in list', async () => {
    analyticsApi.dashboard.mockResolvedValue({ data: MOCK_DASHBOARD })
    renderDashboard()
    await waitFor(() => {
      expect(screen.getByText('เอกสารชีววิทยา')).toBeTruthy()
    })
  })

  it('handles API error gracefully (no crash)', async () => {
    analyticsApi.dashboard.mockRejectedValue(new Error('Network error'))
    expect(() => renderDashboard()).not.toThrow()
    await waitFor(() => {
      // Empty state fallback text
      expect(screen.getByText(/ยังไม่มีเอกสาร/)).toBeTruthy()
    })
  })

  it('renders quick action buttons', async () => {
    analyticsApi.dashboard.mockResolvedValue({ data: MOCK_DASHBOARD })
    renderDashboard()
    await waitFor(() => {
      expect(screen.getByText('อัปโหลดเอกสาร')).toBeTruthy()
      expect(screen.getByText('ถาม AI')).toBeTruthy()
      expect(screen.getByText('สร้างข้อสอบ')).toBeTruthy()
      expect(screen.getByText('ทบทวน Flash Cards')).toBeTruthy()
    })
  })
})
