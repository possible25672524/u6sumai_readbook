import { useState, useEffect, useRef, useCallback } from 'react'
import { chatbotApi } from '../../../api/chatbot'
import { useChatStore } from '../../../store/featureStores'
import { useAuthStore } from '../../../store/authStore'
import { Button, Spinner, Badge } from '../../../components/ui'
import { useToast } from '../../../hooks'

// Citation bubble
function CitationBadge({ citation, idx }) {
  const [open, setOpen] = useState(false)
  return (
    <span className="relative inline-block">
      <button
        onClick={() => setOpen(!open)}
        className="mx-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold hover:bg-indigo-200 transition-colors"
      >
        {idx + 1}
      </button>
      {open && (
        <div className="absolute bottom-5 left-0 z-10 w-64 rounded-xl bg-white border border-slate-200 shadow-lg p-3 text-xs text-slate-700">
          <p className="font-medium text-slate-900 mb-1 truncate">
            {citation.document_title || 'เอกสาร'}
          </p>
          {citation.page && <p className="text-slate-500">หน้า {citation.page}</p>}
          {citation.excerpt && (
            <p className="mt-1 text-slate-600 line-clamp-3 italic">"{citation.excerpt}"</p>
          )}
          <button onClick={() => setOpen(false)} className="absolute top-2 right-2 text-slate-400 hover:text-slate-600">×</button>
        </div>
      )}
    </span>
  )
}

// Render message with inline citation numbers
function MessageContent({ content, citations = [] }) {
  if (!citations.length) {
    return <p className="text-sm leading-relaxed whitespace-pre-wrap">{content}</p>
  }
  // Replace [1], [2] etc with interactive badges
  const parts = content.split(/\[(\d+)\]/g)
  return (
    <p className="text-sm leading-relaxed whitespace-pre-wrap">
      {parts.map((part, i) => {
        if (i % 2 === 1) {
          const cIdx = parseInt(part) - 1
          const citation = citations[cIdx]
          if (citation) return <CitationBadge key={i} citation={citation} idx={cIdx} />
          return `[${part}]`
        }
        return part
      })}
    </p>
  )
}

// Message bubble
function Message({ msg, streaming, streamText }) {
  const isUser = msg.role === 'user'
  const content = (streaming && msg.isStreaming) ? streamText : msg.content

  return (
    <div className={`flex gap-3 ${isUser ? 'flex-row-reverse' : ''}`}>
      <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 ${
        isUser ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'
      }`}>
        {isUser ? 'คุณ' : 'AI'}
      </div>
      <div className={`max-w-[75%] space-y-1 ${isUser ? 'items-end' : 'items-start'} flex flex-col`}>
        <div className={`rounded-2xl px-4 py-2.5 ${
          isUser ? 'bg-indigo-600 text-white rounded-tr-sm' : 'bg-white border border-slate-200 text-slate-900 rounded-tl-sm'
        }`}>
          {isUser ? (
            <p className="text-sm whitespace-pre-wrap">{content}</p>
          ) : (
            <MessageContent content={content || '...'} citations={msg.citations} />
          )}
          {streaming && msg.isStreaming && (
            <span className="inline-block w-1.5 h-4 bg-indigo-500 ml-0.5 animate-pulse rounded-sm" />
          )}
        </div>
        {/* Citations source list */}
        {msg.citations?.length > 0 && (
          <div className="flex flex-wrap gap-1 px-1">
            {msg.citations.map((c, i) => (
              <Badge key={i} variant="info" className="text-xs">
                [{i + 1}] {c.document_title || 'เอกสาร'}
                {c.page ? ` p.${c.page}` : ''}
              </Badge>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default function ChatbotPage() {
  const toast = useToast()
  const { user } = useAuthStore()
  const {
    sessions, currentSession, messages,
    setSessions, setCurrentSession, setMessages,
    addMessage, updateLastMessage,
    streaming, streamText, setStreaming, setStreamText, appendStreamText, resetStream,
  } = useChatStore()

  const [input, setInput] = useState('')
  const [sessionsLoading, setSessionsLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const messagesEndRef = useRef(null)
  const inputRef = useRef(null)

  const scrollToBottom = useCallback(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [])

  useEffect(() => {
    chatbotApi.listSessions()
      .then(({ data }) => setSessions(Array.isArray(data) ? data : data.data ?? []))
      .catch(() => toast({ type: 'error', message: 'โหลด sessions ไม่สำเร็จ' }))
      .finally(() => setSessionsLoading(false))
  }, [setSessions, toast])

  useEffect(() => { scrollToBottom() }, [messages, streamText, scrollToBottom])

  const createSession = async () => {
    try {
      const { data } = await chatbotApi.createSession()
      setSessions((prev) => [data, ...(Array.isArray(prev) ? prev : [])])
      setCurrentSession(data)
      setMessages([])
    } catch {
      toast({ type: 'error', message: 'สร้าง session ไม่สำเร็จ' })
    }
  }

  // Try streaming via fetch EventSource, fallback to regular POST
  const sendMessage = async () => {
    const text = input.trim()
    if (!text || !currentSession || sending) return

    setInput('')
    setSending(true)

    const userMsg = {
      id: Date.now(),
      role: 'user',
      content: text,
      citations: [],
      created_at: new Date().toISOString(),
    }
    addMessage(userMsg)

    // Placeholder for AI response
    const aiMsgId = Date.now() + 1
    addMessage({ id: aiMsgId, role: 'assistant', content: '', citations: [], isStreaming: true })
    setStreaming(true)
    setStreamText('')

    try {
      // Attempt SSE streaming
      const token = useAuthStore.getState().token
      const baseURL = import.meta.env.VITE_API_BASE_URL || '/api'
      const url = `${baseURL}/chat/sessions/${currentSession.id}/messages`

      let didStream = false

      try {
        const resp = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
          body: JSON.stringify({ message: text, stream: true }),
        })

        if (resp.ok && resp.headers.get('content-type')?.includes('text/event-stream')) {
          didStream = true
          const reader = resp.body.getReader()
          const decoder = new TextDecoder()
          let fullText = ''
          let finalCitations = []

          while (true) {
            const { done, value } = await reader.read()
            if (done) break
            const chunk = decoder.decode(value)
            const lines = chunk.split('\n')
            for (const line of lines) {
              if (line.startsWith('data: ')) {
                try {
                  const json = JSON.parse(line.slice(6))
                  if (json.delta) { fullText += json.delta; appendStreamText(json.delta) }
                  if (json.citations) finalCitations = json.citations
                  if (json.done) {
                    updateLastMessage({ content: fullText, citations: finalCitations, isStreaming: false })
                    resetStream()
                  }
                } catch {}
              }
            }
          }
        }
      } catch {}

      // Fallback: regular JSON POST
      if (!didStream) {
        const { data } = await chatbotApi.sendMessage(currentSession.id, { message: text })
        const content = data.message?.content ?? data.content ?? ''
        const citations = data.message?.citations ?? data.citations ?? []
        let i = 0
        const typeInterval = setInterval(() => {
          i += 3
          setStreamText(content.slice(0, i))
          if (i >= content.length) {
            clearInterval(typeInterval)
            updateLastMessage({ content, citations, isStreaming: false })
            resetStream()
          }
        }, 20)
      }
    } catch (err) {
      updateLastMessage({ content: '⚠️ เกิดข้อผิดพลาด กรุณาลองใหม่', citations: [], isStreaming: false })
      resetStream()
      toast({ type: 'error', message: err.response?.data?.message || 'ส่งข้อความไม่สำเร็จ' })
    } finally {
      setSending(false)
      inputRef.current?.focus()
    }
  }

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage() }
  }

  return (
    <div className="flex h-[calc(100vh-6rem)] gap-4">
      {/* Sessions sidebar */}
      <div className="w-56 flex-shrink-0 flex flex-col">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-semibold text-slate-800">การสนทนา</h2>
          <Button size="sm" variant="ghost" onClick={createSession}>+</Button>
        </div>
        <div className="flex-1 overflow-y-auto space-y-1">
          {sessionsLoading ? (
            <div className="flex justify-center pt-6"><Spinner size="sm" /></div>
          ) : sessions.length === 0 ? (
            <p className="text-xs text-slate-400 text-center pt-4">ยังไม่มีการสนทนา</p>
          ) : (
            sessions.map((s) => (
              <button
                key={s.id}
                onClick={() => { setCurrentSession(s); setMessages(s.messages ?? []) }}
                className={`w-full text-left px-3 py-2 rounded-lg text-xs transition-colors ${
                  currentSession?.id === s.id
                    ? 'bg-indigo-100 text-indigo-800'
                    : 'text-slate-600 hover:bg-slate-100'
                }`}
              >
                <p className="truncate font-medium">
                  {s.title || `Session ${s.id}`}
                </p>
                <p className="text-slate-400 mt-0.5">
                  {s.created_at ? new Date(s.created_at).toLocaleDateString('th-TH') : ''}
                </p>
              </button>
            ))
          )}
        </div>
      </div>

      {/* Chat area */}
      <div className="flex-1 flex flex-col min-w-0">
        {!currentSession ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center">
            <div className="text-5xl mb-4">💬</div>
            <h2 className="text-lg font-semibold text-slate-900 mb-2">ถาม AI จากเอกสารของคุณ</h2>
            <p className="text-sm text-slate-500 mb-5 max-w-sm">
              AI จะตอบคำถามจากเนื้อหาในเอกสารที่คุณอัปโหลดเท่านั้น พร้อมอ้างอิงแหล่งที่มา
            </p>
            <Button onClick={createSession}>เริ่มการสนทนาใหม่</Button>
          </div>
        ) : (
          <>
            {/* Messages */}
            <div className="flex-1 overflow-y-auto space-y-4 pr-1 pb-2">
              {messages.length === 0 && (
                <div className="flex flex-col items-center pt-10 text-center">
                  <div className="text-3xl mb-2">🤖</div>
                  <p className="text-sm text-slate-500">ถามคำถามใดก็ได้เกี่ยวกับเนื้อหาในเอกสารของคุณ</p>
                </div>
              )}
              {messages.map((msg) => (
                <Message
                  key={msg.id}
                  msg={msg}
                  streaming={streaming}
                  streamText={streamText}
                />
              ))}
              <div ref={messagesEndRef} />
            </div>

            {/* Input */}
            <div className="border-t border-slate-200 pt-3">
              <div className="flex gap-2">
                <textarea
                  ref={inputRef}
                  rows={2}
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="พิมพ์คำถามของคุณ... (Enter ส่ง, Shift+Enter ขึ้นบรรทัด)"
                  className="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <Button
                  onClick={sendMessage}
                  disabled={!input.trim() || sending || streaming}
                  loading={sending}
                  className="self-end h-10"
                >
                  ส่ง
                </Button>
              </div>
              <p className="text-xs text-slate-400 mt-1.5 ml-1">
                AI ตอบจากเนื้อหาเอกสารของคุณเท่านั้น · คลิกตัวเลข [1] เพื่อดูแหล่งอ้างอิง
              </p>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
