/**
 * Feature stores tests
 * Run with: vitest
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { useDocumentStore, useChatStore, useQuizStore, useFlashcardStore } from '../../store/featureStores'

// ── documentStore ─────────────────────────────────────────────────────────
describe('documentStore', () => {
  beforeEach(() => {
    useDocumentStore.setState({
      documents: [], current: null,
      uploadProgress: 0, uploadStatus: null, processingStatus: {},
    })
  })

  it('setDocuments replaces list', () => {
    const docs = [{ id: 1, title: 'Doc A' }, { id: 2, title: 'Doc B' }]
    useDocumentStore.getState().setDocuments(docs)
    expect(useDocumentStore.getState().documents).toHaveLength(2)
  })

  it('addDocument prepends to list', () => {
    useDocumentStore.getState().setDocuments([{ id: 1, title: 'Existing' }])
    useDocumentStore.getState().addDocument({ id: 2, title: 'New' })
    const docs = useDocumentStore.getState().documents
    expect(docs[0].id).toBe(2)
    expect(docs).toHaveLength(2)
  })

  it('removeDocument filters by id', () => {
    useDocumentStore.getState().setDocuments([
      { id: 1, title: 'A' }, { id: 2, title: 'B' },
    ])
    useDocumentStore.getState().removeDocument(1)
    const docs = useDocumentStore.getState().documents
    expect(docs).toHaveLength(1)
    expect(docs[0].id).toBe(2)
  })

  it('setUploadProgress updates progress', () => {
    useDocumentStore.getState().setUploadProgress(75)
    expect(useDocumentStore.getState().uploadProgress).toBe(75)
  })

  it('resetUpload clears progress and status', () => {
    useDocumentStore.getState().setUploadProgress(100)
    useDocumentStore.getState().setUploadStatus('done')
    useDocumentStore.getState().resetUpload()
    const s = useDocumentStore.getState()
    expect(s.uploadProgress).toBe(0)
    expect(s.uploadStatus).toBeNull()
  })

  it('setProcessingStatus stores per-document status', () => {
    useDocumentStore.getState().setProcessingStatus(42, 'processing')
    expect(useDocumentStore.getState().processingStatus[42]).toBe('processing')
  })

  it('getProcessingStatus returns null for unknown id', () => {
    expect(useDocumentStore.getState().getProcessingStatus(999)).toBeNull()
  })
})

// ── chatStore ─────────────────────────────────────────────────────────────
describe('chatStore', () => {
  beforeEach(() => {
    useChatStore.setState({
      sessions: [], currentSession: null, messages: [],
      streaming: false, streamText: '',
    })
  })

  it('addMessage appends to list', () => {
    useChatStore.getState().addMessage({ id: 1, role: 'user', content: 'Hello' })
    useChatStore.getState().addMessage({ id: 2, role: 'assistant', content: 'Hi' })
    expect(useChatStore.getState().messages).toHaveLength(2)
  })

  it('appendStreamText accumulates chunks', () => {
    useChatStore.getState().appendStreamText('Hello')
    useChatStore.getState().appendStreamText(' World')
    expect(useChatStore.getState().streamText).toBe('Hello World')
  })

  it('resetStream clears streaming state', () => {
    useChatStore.getState().setStreaming(true)
    useChatStore.getState().setStreamText('partial...')
    useChatStore.getState().resetStream()
    const s = useChatStore.getState()
    expect(s.streaming).toBe(false)
    expect(s.streamText).toBe('')
  })

  it('updateLastMessage patches last message', () => {
    useChatStore.getState().addMessage({ id: 1, role: 'assistant', content: '', isStreaming: true })
    useChatStore.getState().updateLastMessage({ content: 'Done!', isStreaming: false })
    const msgs = useChatStore.getState().messages
    expect(msgs[0].content).toBe('Done!')
    expect(msgs[0].isStreaming).toBe(false)
  })
})

// ── quizStore ─────────────────────────────────────────────────────────────
describe('quizStore', () => {
  beforeEach(() => {
    useQuizStore.setState({ quizzes: [], currentAttempt: null, answers: {}, timeLeft: null })
  })

  it('startAttempt sets attempt and clears answers', () => {
    useQuizStore.getState().setAnswer(1, 'A')
    const attempt = { id: 10, quiz: { id: 5 }, questions: [] }
    useQuizStore.getState().startAttempt(attempt)
    const s = useQuizStore.getState()
    expect(s.currentAttempt).toEqual(attempt)
    expect(s.answers).toEqual({})
  })

  it('setAnswer records choice', () => {
    useQuizStore.getState().setAnswer(1, 42)
    expect(useQuizStore.getState().answers[1]).toBe(42)
  })

  it('clearAttempt resets everything', () => {
    useQuizStore.getState().startAttempt({ id: 1, questions: [] })
    useQuizStore.getState().setAnswer(1, 'X')
    useQuizStore.getState().clearAttempt()
    const s = useQuizStore.getState()
    expect(s.currentAttempt).toBeNull()
    expect(s.answers).toEqual({})
    expect(s.timeLeft).toBeNull()
  })
})
