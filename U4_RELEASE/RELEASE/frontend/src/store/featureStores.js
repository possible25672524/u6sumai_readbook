import { create } from 'zustand'

// ─── documentStore ────────────────────────────────────────────────────────
export const useDocumentStore = create((set, get) => ({
  documents: [],
  current: null,
  uploadProgress: 0,      // 0-100
  uploadStatus: null,     // null | 'uploading' | 'done' | 'error'
  processingStatus: {},   // { [docId]: 'pending'|'processing'|'done'|'failed' }

  setDocuments: (docs) => set({ documents: docs }),
  addDocument: (doc) => set((s) => ({ documents: [doc, ...s.documents] })),
  removeDocument: (id) => set((s) => ({ documents: s.documents.filter((d) => d.id !== id) })),
  setCurrent: (docOrUpdater) =>
    set((s) => ({
      current: typeof docOrUpdater === 'function' ? docOrUpdater(s.current) : docOrUpdater,
    })),

  setUploadProgress: (p) => set({ uploadProgress: p }),
  setUploadStatus: (status) => set({ uploadStatus: status }),
  resetUpload: () => set({ uploadProgress: 0, uploadStatus: null }),

  setProcessingStatus: (id, status) =>
    set((s) => ({ processingStatus: { ...s.processingStatus, [id]: status } })),
  getProcessingStatus: (id) => get().processingStatus[id] ?? null,
}))

// ─── chatStore ────────────────────────────────────────────────────────────
export const useChatStore = create((set) => ({
  sessions: [],
  currentSession: null,
  messages: [],        // { id, role:'user'|'assistant', content, citations:[], created_at }
  streaming: false,
  streamText: '',      // partial text while streaming

  setSessions: (sessionsOrUpdater) =>
    set((s) => ({
      sessions: typeof sessionsOrUpdater === 'function' ? sessionsOrUpdater(s.sessions) : sessionsOrUpdater,
    })),
  setCurrentSession: (s) => set({ currentSession: s, messages: [] }),
  setMessages: (msgs) => set({ messages: msgs }),
  addMessage: (msg) => set((s) => ({ messages: [...s.messages, msg] })),
  updateLastMessage: (patch) =>
    set((s) => {
      const msgs = [...s.messages]
      if (msgs.length === 0) return s
      msgs[msgs.length - 1] = { ...msgs[msgs.length - 1], ...patch }
      return { messages: msgs }
    }),
  setStreaming: (v) => set({ streaming: v }),
  setStreamText: (t) => set({ streamText: t }),
  appendStreamText: (chunk) => set((s) => ({ streamText: s.streamText + chunk })),
  resetStream: () => set({ streaming: false, streamText: '' }),
}))

// ─── quizStore ────────────────────────────────────────────────────────────
export const useQuizStore = create((set) => ({
  quizzes: [],
  currentAttempt: null,  // { id, quiz, questions:[], answers:{}, startedAt }
  answers: {},           // { [questionId]: selectedChoiceId }
  timeLeft: null,        // seconds remaining

  setQuizzes: (q) => set({ quizzes: q }),
  startAttempt: (attempt) => set({ currentAttempt: attempt, answers: {} }),
  setAnswer: (questionId, choiceId) =>
    set((s) => ({ answers: { ...s.answers, [questionId]: choiceId } })),
  setTimeLeft: (t) => set({ timeLeft: t }),
  clearAttempt: () => set({ currentAttempt: null, answers: {}, timeLeft: null }),
}))

// ─── flashcardStore ───────────────────────────────────────────────────────
export const useFlashcardStore = create((set) => ({
  sets: [],
  currentSet: null,
  cards: [],
  cardIndex: 0,
  showAnswer: false,

  setSets: (setsOrUpdater) =>
    set((s) => ({
      sets: typeof setsOrUpdater === 'function' ? setsOrUpdater(s.sets) : setsOrUpdater,
    })),
  startReview: (set_, cards) =>
    set({ currentSet: set_, cards, cardIndex: 0, showAnswer: false }),
  nextCard: () =>
    set((s) => ({ cardIndex: Math.min(s.cardIndex + 1, s.cards.length - 1), showAnswer: false })),
  prevCard: () =>
    set((s) => ({ cardIndex: Math.max(s.cardIndex - 1, 0), showAnswer: false })),
  flipCard: () => set((s) => ({ showAnswer: !s.showAnswer })),
}))
