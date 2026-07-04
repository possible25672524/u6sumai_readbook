import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { quizApi } from '../../../api/quiz'
import { Button, Card, PageHeader, Spinner } from '../../../components/ui'
import { useToast } from '../../../hooks'

function ScoreCircle({ score, total }) {
  const pct = total > 0 ? Math.round((score / total) * 100) : 0
  const color = pct >= 80 ? '#22c55e' : pct >= 60 ? '#f59e0b' : '#ef4444'
  const r = 54
  const circ = 2 * Math.PI * r
  const offset = circ - (pct / 100) * circ
  return (
    <div className="flex flex-col items-center">
      <svg width="128" height="128" viewBox="0 0 128 128">
        <circle cx="64" cy="64" r={r} fill="none" stroke="#e2e8f0" strokeWidth="10" />
        <circle
          cx="64" cy="64" r={r} fill="none"
          stroke={color} strokeWidth="10"
          strokeDasharray={circ} strokeDashoffset={offset}
          strokeLinecap="round"
          transform="rotate(-90 64 64)"
          style={{ transition: 'stroke-dashoffset 1s ease' }}
        />
        <text x="64" y="60" textAnchor="middle" fontSize="22" fontWeight="700" fill="#0f172a">{pct}%</text>
        <text x="64" y="78" textAnchor="middle" fontSize="11" fill="#64748b">{score}/{total}</text>
      </svg>
      <p className="mt-1 text-sm font-medium" style={{ color }}>
        {pct >= 80 ? '🎉 ยอดเยี่ยม!' : pct >= 60 ? '👍 ผ่านแล้ว' : '💪 ยังพอปรับปรุงได้'}
      </p>
    </div>
  )
}

export default function QuizResultPage() {
  const { attemptId } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(true)
  const [showReview, setShowReview] = useState(false)

  useEffect(() => {
    quizApi.getResult(attemptId)
      .then(({ data }) => setResult(data))
      .catch(() => toast({ type: 'error', message: 'โหลดผลคะแนนไม่สำเร็จ' }))
      .finally(() => setLoading(false))
  }, [attemptId, toast])

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>
  if (!result) return null

  const { score = 0, total_questions = 0, questions = [], time_taken_seconds } = result

  return (
    <div className="max-w-2xl mx-auto">
      <PageHeader
        title="ผลการทดสอบ"
        subtitle={result.quiz?.title || ''}
        action={
          <Button variant="ghost" onClick={() => navigate('/quizzes')}>← กลับรายการ</Button>
        }
      />

      {/* Score card */}
      <Card className="flex flex-col sm:flex-row items-center gap-6 mb-5">
        <ScoreCircle score={score} total={total_questions} />
        <div className="flex-1 space-y-2 w-full">
          <div className="grid grid-cols-3 gap-3 text-center">
            {[
              { label: 'ถูก', value: score, color: 'text-green-600' },
              { label: 'ผิด', value: total_questions - score, color: 'text-red-500' },
              { label: 'ทั้งหมด', value: total_questions, color: 'text-slate-700' },
            ].map((s) => (
              <div key={s.label} className="rounded-xl bg-slate-50 p-3">
                <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
                <p className="text-xs text-slate-500 mt-0.5">{s.label}</p>
              </div>
            ))}
          </div>
          {time_taken_seconds && (
            <p className="text-xs text-slate-400 text-center">
              เวลาที่ใช้: {Math.floor(time_taken_seconds / 60)} นาที {time_taken_seconds % 60} วินาที
            </p>
          )}
        </div>
      </Card>

      {/* Actions */}
      <div className="flex gap-3 mb-5">
        <Button className="flex-1" onClick={() => setShowReview(!showReview)}>
          {showReview ? 'ซ่อนเฉลย' : '📋 ดูเฉลยทั้งหมด'}
        </Button>
        <Button variant="secondary" className="flex-1" onClick={() => navigate('/quizzes')}>
          ทำข้อสอบอื่น
        </Button>
      </div>

      {/* Question review */}
      {showReview && (
        <div className="space-y-3">
          {questions.map((q, i) => {
            const userAns = q.user_answer
            const correct = q.is_correct
            return (
              <Card key={q.id} className={`border-l-4 ${correct ? 'border-l-green-400' : 'border-l-red-400'}`}>
                <div className="flex items-start gap-2 mb-2">
                  <span className={`flex-shrink-0 w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center ${
                    correct ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                  }`}>
                    {i + 1}
                  </span>
                  <p className="text-sm text-slate-900">{q.content}</p>
                </div>

                <div className="ml-8 space-y-1 text-xs">
                  <p className={`${correct ? 'text-green-700' : 'text-red-600'}`}>
                    คำตอบของคุณ: {userAns?.content ?? String(userAns ?? '—')}
                  </p>
                  {!correct && (
                    <p className="text-green-700">
                      เฉลย: {q.correct_answer?.content ?? String(q.correct_answer ?? '—')}
                    </p>
                  )}
                  {q.explanation && (
                    <div className="mt-2 rounded-lg bg-blue-50 border border-blue-200 px-3 py-2">
                      <p className="text-blue-800 font-medium mb-0.5">คำอธิบาย</p>
                      <p className="text-blue-700">{q.explanation}</p>
                    </div>
                  )}
                </div>
              </Card>
            )
          })}
        </div>
      )}
    </div>
  )
}
