import { useEffect, useState } from 'react'
import { analyticsApi } from '../../../api/analytics'
import { Card, PageHeader, Spinner, Tabs } from '../../../components/ui'
import { useToast } from '../../../hooks'

// SVG Line Chart (no external lib)
function LineChart({ data, color = '#6366f1', height = 120, label = '' }) {
  if (!data?.length) return <p className="text-sm text-slate-400 py-8 text-center">ไม่มีข้อมูล</p>
  const max = Math.max(...data.map((d) => d.value), 1)
  const w = 400; const h = height; const pad = 30
  const points = data.map((d, i) => {
    const x = pad + (i / (data.length - 1)) * (w - pad * 2)
    const y = h - pad - ((d.value / max) * (h - pad * 2))
    return `${x},${y}`
  })
  const pathD = points.reduce((acc, p, i) => acc + (i === 0 ? `M ${p}` : ` L ${p}`), '')
  const areaD = `${pathD} L ${points[points.length - 1].split(',')[0]},${h - pad} L ${pad},${h - pad} Z`
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="w-full" style={{ height }}>
      <defs>
        <linearGradient id={`grad-${label}`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.2" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={areaD} fill={`url(#grad-${label})`} />
      <path d={pathD} fill="none" stroke={color} strokeWidth="2" strokeLinecap="round" />
      {data.map((d, i) => {
        const x = pad + (i / (data.length - 1)) * (w - pad * 2)
        const y = h - pad - ((d.value / max) * (h - pad * 2))
        return (
          <g key={i}>
            <circle cx={x} cy={y} r="3.5" fill={color} />
            <text x={x} y={h - 5} textAnchor="middle" fontSize="9" fill="#94a3b8">{d.label}</text>
          </g>
        )
      })}
    </svg>
  )
}

// Donut chart
function DonutChart({ value, total, color = '#6366f1', label }) {
  const pct = total > 0 ? (value / total) : 0
  const r = 42; const circ = 2 * Math.PI * r
  const offset = circ - pct * circ
  return (
    <div className="flex flex-col items-center">
      <svg width="100" height="100" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r={r} fill="none" stroke="#e2e8f0" strokeWidth="10" />
        <circle cx="50" cy="50" r={r} fill="none" stroke={color} strokeWidth="10"
          strokeDasharray={circ} strokeDashoffset={offset}
          strokeLinecap="round" transform="rotate(-90 50 50)" />
        <text x="50" y="46" textAnchor="middle" fontSize="16" fontWeight="700" fill="#0f172a">
          {Math.round(pct * 100)}%
        </text>
        <text x="50" y="60" textAnchor="middle" fontSize="9" fill="#64748b">{label}</text>
      </svg>
    </div>
  )
}

export default function AnalyticsPage() {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [period, setPeriod] = useState('week')

  useEffect(() => {
    analyticsApi.studyTime({ period })
      .then(({ data: d }) => setData(d))
      .catch(() => setData({}))
      .finally(() => setLoading(false))
  }, [period])

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  const studyTime = data?.study_time_series || []
  const quizPerformance = data?.quiz_performance_series || []
  const subjectBreakdown = data?.subject_breakdown || []
  const totals = data?.totals || {}

  const periodTabs = [
    { value: 'week', label: 'สัปดาห์นี้' },
    { value: 'month', label: 'เดือนนี้' },
    { value: 'all', label: 'ทั้งหมด' },
  ]

  return (
    <div>
      <PageHeader
        title="สถิติการเรียน"
        subtitle="ติดตามความก้าวหน้าและประสิทธิภาพการเรียน"
      />

      <Tabs tabs={periodTabs} active={period} onChange={setPeriod} className="mb-5" />

      {/* Summary row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        {[
          { label: 'เวลาเรียนรวม', value: `${totals.total_study_minutes ?? 0} นาที`, icon: '⏱️' },
          { label: 'ข้อสอบทำแล้ว', value: totals.quizzes_done ?? 0, icon: '✅' },
          { label: 'คะแนนเฉลี่ย', value: totals.avg_score ? `${totals.avg_score}%` : '—', icon: '📊' },
          { label: 'Flash Cards ทบทวน', value: totals.cards_reviewed ?? 0, icon: '🃏' },
        ].map((s) => (
          <Card key={s.label} className="flex items-center gap-3">
            <span className="text-2xl">{s.icon}</span>
            <div>
              <p className="text-xl font-bold text-slate-900">{s.value}</p>
              <p className="text-xs text-slate-500">{s.label}</p>
            </div>
          </Card>
        ))}
      </div>

      <div className="grid lg:grid-cols-2 gap-5 mb-5">
        {/* Study time chart */}
        <Card>
          <h2 className="text-sm font-semibold text-slate-800 mb-3">เวลาเรียน (นาที)</h2>
          <LineChart
            data={studyTime.length ? studyTime : [
              { label: 'จ', value: 0 }, { label: 'อ', value: 0 }, { label: 'พ', value: 0 },
              { label: 'พฤ', value: 0 }, { label: 'ศ', value: 0 }, { label: 'ส', value: 0 }, { label: 'อา', value: 0 },
            ]}
            label="study"
          />
        </Card>

        {/* Quiz performance */}
        <Card>
          <h2 className="text-sm font-semibold text-slate-800 mb-3">คะแนนข้อสอบ (%)</h2>
          <LineChart
            data={quizPerformance.length ? quizPerformance : [
              { label: '1', value: 0 }, { label: '2', value: 0 }, { label: '3', value: 0 },
            ]}
            color="#22c55e"
            label="quiz"
          />
        </Card>
      </div>

      {/* Subject breakdown */}
      {subjectBreakdown.length > 0 && (
        <Card>
          <h2 className="text-sm font-semibold text-slate-800 mb-4">เวลาเรียนตามหัวข้อ</h2>
          <div className="space-y-3">
            {subjectBreakdown.map((s) => {
              const maxMins = Math.max(...subjectBreakdown.map((x) => x.minutes), 1)
              const pct = Math.round((s.minutes / maxMins) * 100)
              return (
                <div key={s.subject}>
                  <div className="flex justify-between text-xs text-slate-600 mb-1">
                    <span className="truncate">{s.subject}</span>
                    <span className="flex-shrink-0 ml-2 font-medium">{s.minutes} นาที</span>
                  </div>
                  <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div className="h-full bg-indigo-500 rounded-full" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              )
            })}
          </div>
        </Card>
      )}

      {/* Mastery donuts */}
      {(totals.flashcard_mastery != null || totals.quiz_pass_rate != null) && (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-5">
          {totals.flashcard_mastery != null && (
            <Card className="flex flex-col items-center py-2">
              <DonutChart value={totals.flashcard_mastery} total={100} label="Flash Card" />
              <p className="text-xs text-slate-500 mt-1">ความเข้าใจ Flash Card</p>
            </Card>
          )}
          {totals.quiz_pass_rate != null && (
            <Card className="flex flex-col items-center py-2">
              <DonutChart value={totals.quiz_pass_rate} total={100} color="#22c55e" label="ผ่านข้อสอบ" />
              <p className="text-xs text-slate-500 mt-1">อัตราผ่านข้อสอบ</p>
            </Card>
          )}
          {totals.plan_completion != null && (
            <Card className="flex flex-col items-center py-2">
              <DonutChart value={totals.plan_completion} total={100} color="#f59e0b" label="แผนการเรียน" />
              <p className="text-xs text-slate-500 mt-1">ความคืบหน้าแผน</p>
            </Card>
          )}
        </div>
      )}
    </div>
  )
}
