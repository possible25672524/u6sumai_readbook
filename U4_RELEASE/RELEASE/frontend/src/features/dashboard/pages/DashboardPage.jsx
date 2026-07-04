import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { analyticsApi } from '../../../api/analytics'
import { useAuthStore } from '../../../store/authStore'
import { Card, PageHeader, Spinner, Badge, Button } from '../../../components/ui'
import { useToast } from '../../../hooks'

function StatCard({ icon, label, value, sub, color = 'indigo' }) {
  const colors = {
    indigo: 'bg-indigo-50 text-indigo-600',
    green: 'bg-green-50 text-green-600',
    amber: 'bg-amber-50 text-amber-600',
    blue: 'bg-blue-50 text-blue-600',
  }
  return (
    <Card className="flex items-center gap-4">
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-2xl ${colors[color]}`}>
        {icon}
      </div>
      <div>
        <p className="text-2xl font-bold text-slate-900">{value ?? '—'}</p>
        <p className="text-sm text-slate-500">{label}</p>
        {sub && <p className="text-xs text-slate-400 mt-0.5">{sub}</p>}
      </div>
    </Card>
  )
}

// Lightweight bar chart using plain divs
function SimpleBarChart({ data }) {
  if (!data?.length) return <p className="text-sm text-slate-400 py-6 text-center">ไม่มีข้อมูล</p>
  const max = Math.max(...data.map((d) => d.value), 1)
  return (
    <div className="flex items-end gap-2 h-32">
      {data.map((d, i) => (
        <div key={i} className="flex-1 flex flex-col items-center gap-1">
          <div
            className="w-full bg-indigo-500 rounded-t-md transition-all duration-700"
            style={{ height: `${(d.value / max) * 100}%`, minHeight: d.value > 0 ? 4 : 0 }}
          />
          <p className="text-xs text-slate-400 truncate w-full text-center">{d.label}</p>
        </div>
      ))}
    </div>
  )
}

export default function DashboardPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const { user } = useAuthStore()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    analyticsApi.dashboard()
      .then(({ data: d }) => setData(d))
      .catch(() => {
        // graceful — dashboard still renders with placeholders
        setData({})
      })
      .finally(() => setLoading(false))
  }, [])

  const greeting = () => {
    const h = new Date().getHours()
    if (h < 12) return 'อรุณสวัสดิ์'
    if (h < 17) return 'สวัสดีตอนบ่าย'
    return 'สวัสดีตอนเย็น'
  }

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  const stats = data?.stats || {}
  const weeklyStudy = data?.weekly_study_minutes || []
  const recentDocs = data?.recent_documents || []
  const upcomingItems = data?.upcoming_plan_items || []
  const recentQuizzes = data?.recent_quiz_attempts || []

  return (
    <div>
      <PageHeader
        title={`${greeting()}, ${user?.name?.split(' ')[0] ?? 'ผู้เรียน'} 👋`}
        subtitle={`วันนี้ ${new Date().toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`}
      />

      {/* Stat cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard icon="📁" label="เอกสารทั้งหมด" value={stats.total_documents ?? 0} color="blue" />
        <StatCard icon="⏱️" label="นาทีที่เรียนวันนี้" value={stats.study_minutes_today ?? 0} sub="นาที" color="indigo" />
        <StatCard icon="✅" label="ข้อสอบทำแล้ว" value={stats.quizzes_completed ?? 0} color="green" />
        <StatCard icon="🃏" label="Flash Cards ครบกำหนด" value={stats.flashcards_due ?? 0} color="amber" />
      </div>

      <div className="grid lg:grid-cols-3 gap-5">
        {/* Weekly activity chart */}
        <Card className="lg:col-span-2">
          <h2 className="text-sm font-semibold text-slate-800 mb-4">เวลาเรียนรายสัปดาห์ (นาที)</h2>
          <SimpleBarChart data={weeklyStudy.length ? weeklyStudy : [
            { label: 'จ', value: 0 }, { label: 'อ', value: 0 }, { label: 'พ', value: 0 },
            { label: 'พฤ', value: 0 }, { label: 'ศ', value: 0 }, { label: 'ส', value: 0 }, { label: 'อา', value: 0 },
          ]} />
        </Card>

        {/* Upcoming plan */}
        <Card>
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-semibold text-slate-800">แผนวันนี้</h2>
            <Button size="sm" variant="ghost" onClick={() => navigate('/planner')}>ดูทั้งหมด</Button>
          </div>
          {upcomingItems.length === 0 ? (
            <div className="text-center py-6">
              <p className="text-sm text-slate-400">ยังไม่มีแผนวันนี้</p>
              <Button size="sm" variant="ghost" className="mt-2" onClick={() => navigate('/planner')}>
                สร้างแผนการเรียน
              </Button>
            </div>
          ) : (
            <ul className="space-y-2">
              {upcomingItems.slice(0, 5).map((item) => (
                <li key={item.id} className="flex items-center gap-2 text-sm">
                  <span className={`w-4 h-4 rounded-full border-2 flex-shrink-0 ${
                    item.status === 'done' ? 'bg-green-500 border-green-500' : 'border-slate-300'
                  }`} />
                  <span className={item.status === 'done' ? 'line-through text-slate-400' : 'text-slate-700'}>
                    {item.topic || item.title}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>

        {/* Recent documents */}
        <Card>
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-semibold text-slate-800">เอกสารล่าสุด</h2>
            <Button size="sm" variant="ghost" onClick={() => navigate('/documents')}>ดูทั้งหมด</Button>
          </div>
          {recentDocs.length === 0 ? (
            <div className="text-center py-6">
              <p className="text-sm text-slate-400">ยังไม่มีเอกสาร</p>
              <Button size="sm" className="mt-2" onClick={() => navigate('/documents/upload')}>
                อัปโหลดเอกสาร
              </Button>
            </div>
          ) : (
            <ul className="space-y-2">
              {recentDocs.slice(0, 5).map((doc) => (
                <li
                  key={doc.id}
                  className="flex items-center gap-2 text-sm cursor-pointer hover:text-indigo-600"
                  onClick={() => navigate(`/documents/${doc.id}`)}
                >
                  <span>📄</span>
                  <span className="truncate flex-1">{doc.title || doc.filename}</span>
                  <Badge variant={doc.processing_status === 'done' ? 'success' : 'default'} className="flex-shrink-0">
                    {doc.processing_status === 'done' ? '✓' : '...'}
                  </Badge>
                </li>
              ))}
            </ul>
          )}
        </Card>

        {/* Recent quiz results */}
        <Card className="lg:col-span-2">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-semibold text-slate-800">ผลข้อสอบล่าสุด</h2>
            <Button size="sm" variant="ghost" onClick={() => navigate('/quizzes')}>ดูทั้งหมด</Button>
          </div>
          {recentQuizzes.length === 0 ? (
            <div className="text-center py-8">
              <p className="text-sm text-slate-400">ยังไม่มีประวัติการทำข้อสอบ</p>
              <Button size="sm" className="mt-2" onClick={() => navigate('/quizzes')}>
                เริ่มทำข้อสอบ
              </Button>
            </div>
          ) : (
            <div className="space-y-2">
              {recentQuizzes.slice(0, 4).map((attempt) => {
                const pct = attempt.total_questions > 0
                  ? Math.round((attempt.score / attempt.total_questions) * 100) : 0
                return (
                  <div key={attempt.id} className="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50">
                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold ${
                      pct >= 80 ? 'bg-green-100 text-green-700' : pct >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'
                    }`}>
                      {pct}%
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-slate-900 truncate">
                        {attempt.quiz?.title || `ข้อสอบ #${attempt.id}`}
                      </p>
                      <p className="text-xs text-slate-400">
                        {attempt.score}/{attempt.total_questions} ข้อ ·{' '}
                        {attempt.created_at ? new Date(attempt.created_at).toLocaleDateString('th-TH') : ''}
                      </p>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </Card>
      </div>

      {/* Quick actions */}
      <div className="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { icon: '📤', label: 'อัปโหลดเอกสาร', to: '/documents/upload' },
          { icon: '💬', label: 'ถาม AI', to: '/chatbot' },
          { icon: '❓', label: 'สร้างข้อสอบ', to: '/quizzes/generate' },
          { icon: '🃏', label: 'ทบทวน Flash Cards', to: '/flashcards' },
        ].map((a) => (
          <button
            key={a.label}
            onClick={() => navigate(a.to)}
            className="flex flex-col items-center gap-2 p-4 rounded-xl bg-white border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 transition-colors text-center"
          >
            <span className="text-2xl">{a.icon}</span>
            <span className="text-xs font-medium text-slate-700">{a.label}</span>
          </button>
        ))}
      </div>
    </div>
  )
}
