import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { quizApi } from '../../../api/quiz'
import { useQuizStore } from '../../../store/featureStores'
import { Button, Card, PageHeader, EmptyState, Spinner, Badge } from '../../../components/ui'
import { useToast } from '../../../hooks'

export default function QuizListPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const { quizzes, setQuizzes } = useQuizStore()
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    quizApi.listQuizzes()
      .then(({ data }) => setQuizzes(Array.isArray(data) ? data : data.data ?? []))
      .catch(() => toast({ type: 'error', message: 'โหลดรายการข้อสอบไม่สำเร็จ' }))
      .finally(() => setLoading(false))
  }, [setQuizzes, toast])

  const handleStart = async (quiz) => {
    try {
      const { data } = await quizApi.startAttempt(quiz.id)
      navigate(`/quizzes/attempts/${data.id}`)
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'เริ่มข้อสอบไม่สำเร็จ' })
    }
  }

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  return (
    <div>
      <PageHeader
        title="ข้อสอบทั้งหมด"
        subtitle={`${quizzes.length} ชุดข้อสอบ`}
        action={
          <Button onClick={() => navigate('/quizzes/generate')}>
            + สร้างข้อสอบใหม่
          </Button>
        }
      />

      {quizzes.length === 0 ? (
        <EmptyState
          icon="❓"
          title="ยังไม่มีข้อสอบ"
          description="สร้างข้อสอบจาก AI โดยเลือกเอกสารที่ต้องการ"
          action={<Button onClick={() => navigate('/quizzes/generate')}>สร้างข้อสอบ</Button>}
        />
      ) : (
        <div className="grid gap-3">
          {quizzes.map((quiz) => (
            <Card key={quiz.id}>
              <div className="flex items-center gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="text-sm font-medium text-slate-900 truncate">
                      {quiz.title || `ข้อสอบ #${quiz.id}`}
                    </h3>
                    <Badge variant="purple">{quiz.question_count ?? '?'} ข้อ</Badge>
                    {quiz.question_type && (
                      <Badge variant="default">{quiz.question_type}</Badge>
                    )}
                  </div>
                  <p className="text-xs text-slate-400">
                    จาก: {quiz.document?.title ?? quiz.document?.filename ?? '—'} ·{' '}
                    ทำแล้ว {quiz.attempt_count ?? 0} ครั้ง
                  </p>
                </div>
                <div className="flex gap-2 flex-shrink-0">
                  <Button size="sm" variant="secondary" onClick={() => handleStart(quiz)}>
                    ▶ ทำข้อสอบ
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
