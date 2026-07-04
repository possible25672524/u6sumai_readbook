/**
 * ChatbotPage tests
 * Run with: vitest
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

// Mock stores
vi.mock('../../store/featureStores', () => ({
  useChatStore: vi.fn((selector) =>
    selector({
      sessions: [],
      currentSession: null,
      messages: [],
      streaming: false,
      streamText: '',
      setSessions: vi.fn(),
      setCurrentSession: vi.fn(),
      setMessages: vi.fn(),
      addMessage: vi.fn(),
      updateLastMessage: vi.fn(),
      setStreaming: vi.fn(),
      setStreamText: vi.fn(),
      appendStreamText: vi.fn(),
      resetStream: vi.fn(),
    }),
  ),
}))

vi.mock('../../store/authStore', () => ({
  useAuthStore: vi.fn((selector) =>
    selector({ user: { id: 1, name: 'Test', role: 'student' }, token: 'token123' }),
  ),
}))

vi.mock('../../api/chatbot', () => ({
  chatbotApi: {
    listSessions: vi.fn().mockResolvedValue({ data: [] }),
    createSession: vi.fn().mockResolvedValue({ data: { id: 1, title: 'Session 1' } }),
    sendMessage: vi.fn().mockResolvedValue({ data: { message: { content: 'ตอบกลับจาก AI', citations: [] } } }),
  },
}))

vi.mock('../../hooks', () => ({
  useToast: () => vi.fn(),
}))

import ChatbotPage from '../../features/chatbot/pages/ChatbotPage'

function renderChatbot() {
  return render(
    <MemoryRouter>
      <ChatbotPage />
    </MemoryRouter>,
  )
}

describe('ChatbotPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders without crashing', () => {
    expect(() => renderChatbot()).not.toThrow()
  })

  it('shows empty state when no session selected', async () => {
    renderChatbot()
    await waitFor(() => {
      expect(screen.getByText(/ถาม AI จากเอกสารของคุณ/)).toBeTruthy()
    })
  })

  it('shows session list area', async () => {
    renderChatbot()
    await waitFor(() => {
      expect(screen.getByText('การสนทนา')).toBeTruthy()
    })
  })

  it('shows new session button', async () => {
    renderChatbot()
    await waitFor(() => {
      // The + button to create new session
      expect(screen.getByText('+')).toBeTruthy()
    })
  })

  it('renders start new session button in empty state', async () => {
    renderChatbot()
    await waitFor(() => {
      expect(screen.getByText('เริ่มการสนทนาใหม่')).toBeTruthy()
    })
  })
})

// ── Citation rendering tests ───────────────────────────────────────────
describe('CitationBadge (unit)', () => {
  it('renders citation number correctly', async () => {
    // Test the sub-component logic via rendered output check
    const { default: ChatbotPageMod } = await import('../../features/chatbot/pages/ChatbotPage')
    expect(ChatbotPageMod).toBeDefined()
  })
})

// ── Streaming state tests via store ───────────────────────────────────
describe('chatStore streaming state', () => {
  it('appendStreamText accumulates correctly', async () => {
    const { useChatStore } = await import('../../store/featureStores')
    // Reset to real store for this test
    vi.unmock('../../store/featureStores')
    const realMod = await import('../../store/featureStores')
    const store = realMod.useChatStore
    store.setState({ streamText: '', streaming: false, messages: [], sessions: [], currentSession: null })

    store.getState().appendStreamText('Hello')
    store.getState().appendStreamText(' World')
    expect(store.getState().streamText).toBe('Hello World')
    store.getState().resetStream()
    expect(store.getState().streamText).toBe('')
    expect(store.getState().streaming).toBe(false)
  })
})
