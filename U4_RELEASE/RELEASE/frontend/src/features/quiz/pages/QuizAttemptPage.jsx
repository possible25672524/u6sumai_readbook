import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { quizApi } from '../../../api/quiz'
import { useQuizStore } from '../../../store/featureStores'
import { Button, Card, Spinner, Badge } from '../../../components/ui'
import { useToast } from '../../../hooks'

function Timer({ seconds, onExpire }) {
  const [left, setLeft] = useState(seconds)
  useEffect(() => {
    if (!seconds) return
    const t = setInterval(() => {
      setLeft((s) => {
        if (s <= 1) { clearInterval(t); onExpire?.(); return 0 }
        return s - 1
      })
    }, 1000)
    return () => clearInterval(t)
  }, [seconds, onExpire])
  if (!seconds) return null
  const m = Math.floor(left / 60)
  const s = left % 60
  const pct = (left / seconds) * 100
  return (
    <div className="flex items-center gap-2">
      <div className={`text-sm font-mono font-semibold ${left < 60 ? 'text-red-600' : 'text-slate-700'}`}>
        {m}:{String(s).padStart(2, '0')}
      </div>
      <div className="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all ${left < 60 ? 'bg-red-500' : 'bg-indigo-600'}`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  )
}

export default function QuizAttemptPage() {
  const { attemptId } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const { currentAttempt, answers, startAttempt, setAnswer, clearAttempt } = useQuizStore()

  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [currentIdx, setCurrentIdx] = useState(0)
  const [flagged, setFlagged] = useState(new Set())

  useEffect(() => {
    quizApi.getResult(attemptId)
      .then(({ data }) => startAttempt(data))
      .catch(() => toast({ type: 'error', message: 'โหลดข้อสอบไม่สำเร็จ' }))
      .finally(() => setLoading(false))
    return () => clearAttempt()
  }, [attemptId, startAttempt, clearAttempt, toast])

  const questions = currentAttempt?.questions ?? []
  const question = questions[currentIdx]
  const totalQ = questions.length
  const answeredCount = Object.keys(answers).length

  const handleSubmit = async () => {
    if (answeredCount < totalQ) {
      if (!window.confirm(`คุณตอบแล้ว ${answeredCount}/${totalQ} ข้อ ยืนยันส่งคำตอบใช่หรือไม่?`)) return
    }
    setSubmitting(true)
    try {
      await quizApi.submitAttempt(attemptId, { answers })
      navigate(`/quizzes/attempts/${attemptId}/result`)
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'ส่งคำตอบไม่สำเร็จ' })
      setSubmitting(false)
    }
  }

  const toggleFlag = () => setFlagged((f) => {
    const s = new Set(f)
    s.has(question?.id) ? s.delete(question?.id) : s.add(question?.id)
    return s
  })

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>
  if (!currentAttempt || questions.length === 0) {
    return (
      <div className="text-center py-16">
        <p className="text-slate-500">ไม่พบข้อมูลการทำข้อสอบ</p>
        <Button className="mt-4" onClick={() => navigate('/quizzes')}>กลับรายการ</Button>
      </div>
    )
  }

  return (
    <div className="max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h1 className="text-lg font-semibold text-slate-900">
            {currentAttempt.quiz?.title || 'ข้อสอบ'}
          </h1>
          <p className="text-xs text-slate-400 mt-0.5">
            ข้อ {currentIdx + 1} จาก {totalQ} · ตอบแล้ว {answeredCount} ข้อ
          </p>
        </div>
        <Timer seconds={currentAttempt.time_limit_seconds} onExpire={handleSubmit} />
      </div>

      {/* Progress bar */}
      <div className="h-1 bg-slate-200 rounded-full mb-5 overflow-hidden">
        <div
          className="h-full bg-indigo-600 rounded-full transition-all"
          style={{ width: `${((currentIdx + 1) / totalQ) * 100}%` }}
        />
      </div>

      {/* Question */}
      <Card className="mb-4">
        <div className="flex items-start justify-between gap-3 mb-4">
          <div className="flex items-center gap-2 flex-shrink-0">
            <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
              {currentIdx + 1}
            </span>
            {flagged.has(question.id) && <Badge variant="warning">🚩 ทำเครื่องหมาย</Badge>}
          </div>
          <button onClick={toggleFlag} className="text-xs text-slate-400 hover:text-amber-500 flex-shrink-0">
            {flagged.has(question.id) ? '🚩 ยกเลิกเครื่องหมาย' : '🚩 ทำเครื่องหมาย'}
          </button>
        </div>

        <p className="text-base text-slate-900 leading-relaxed mb-5">{question.content}</p>

        {/* Choices */}
        {question.choices ? (
          <div className="space-y-2">
            {question.choices.map((choice) => {
              const selected = answers[question.id] === choice.id
              return (
                <button
                  key={choice.id}
                  onClick={() => setAnswer(question.id, choice.id)}
                  className={`w-full text-left px-4 py-3 rounded-xl border text-sm transition-colors ${
                    selected
                      ? 'border-indigo-500 bg-indigo-50 text-indigo-900 font-medium'
                      : 'border-slate-200 hover:border-indigo-300 hover:bg-slate-50'
                  }`}
                >
                  <span className="mr-2 font-medium text-slate-500">{choice.label ?? choice.key}.</span>
                  {choice.content}
                </button>
              )
            })}
          </div>
        ) : (
          /* Short answer / fill blank */
          <textarea
            rows={3}
            value={answers[question.id] || ''}
            onChange={(e) => setAnswer(question.id, e.target.value)}
            placeholder="พิมพ์คำตอบของคุณที่นี่..."
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        )}
      </Card>

      {/* Navigation */}
      <div className="flex items-center justify-between">
        <Button
          variant="secondary"
          disabled={currentIdx === 0}
          onClick={() => setCurrentIdx((i) => i - 1)}
        >
          ← ก่อนหน้า
        </Button>

        {/* Question dots */}
        <div className="flex gap-1 flex-wrap justify-center max-w-xs">
          {questions.map((q, i) => (
            <button
              key={q.id}
              onClick={() => setCurrentIdx(i)}
              className={`w-6 h-6 rounded-full text-xs transition-colors ${
                i === currentIdx
                  ? 'bg-indigo-600 text-white'
                  : answers[q.id]
                    ? flagged.has(q.id) ? 'bg-amber-400 text-white' : 'bg-green-400 text-white'
                    : 'bg-slate-200 text-slate-500'
              }`}
            >
              {i + 1}
            </button>
          ))}
        </div>

        {currentIdx < totalQ - 1 ? (
          <Button onClick={() => setCurrentIdx((i) => i + 1)}>
            ถัดไป →
          </Button>
        ) : (
          <Button
            variant="primary"
            loading={submitting}
            onClick={handleSubmit}
          >
            ส่งคำตอบ ✓
          </Button>
        )}
      </div>
    </div>
  )
}
