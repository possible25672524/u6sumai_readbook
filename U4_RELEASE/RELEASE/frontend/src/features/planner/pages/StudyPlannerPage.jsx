import { useState, useEffect, useMemo } from 'react'
import { plannerApi } from '../../../api/planner'
import { Button, Card, PageHeader, Spinner, Badge, EmptyState, Modal } from '../../../components/ui'
import { useToast } from '../../../hooks'

// Minimal calendar - show days in current month, highlight study items
function MiniCalendar({ items }) {
  const today = new Date()
  const [year, setYear] = useState(today.getFullYear())
  const [month, setMonth] = useState(today.getMonth())

  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const firstDayOfWeek = new Date(year, month, 1).getDay()

  const itemsByDate = useMemo(() => {
    const map = {}
    items.forEach((item) => {
      const d = new Date(item.scheduled_date)
      const key = `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
      if (!map[key]) map[key] = []
      map[key].push(item)
    })
    return map
  }, [items])

  const monthNames = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']
  const dayNames = ['อา','จ','อ','พ','พฤ','ศ','ส']

  const prev = () => { if (month === 0) { setMonth(11); setYear(y => y-1) } else setMonth(m => m-1) }
  const next = () => { if (month === 11) { setMonth(0); setYear(y => y+1) } else setMonth(m => m+1) }

  return (
    <Card>
      <div className="flex items-center justify-between mb-3">
        <button onClick={prev} className="text-slate-400 hover:text-slate-700 px-2">‹</button>
        <h3 className="text-sm font-semibold text-slate-800">{monthNames[month]} {year}</h3>
        <button onClick={next} className="text-slate-400 hover:text-slate-700 px-2">›</button>
      </div>
      <div className="grid grid-cols-7 gap-0.5">
        {dayNames.map((d) => (
          <div key={d} className="text-center text-xs font-medium text-slate-400 py-1">{d}</div>
        ))}
        {Array.from({ length: firstDayOfWeek }).map((_, i) => <div key={`e${i}`} />)}
        {Array.from({ length: daysInMonth }).map((_, i) => {
          const day = i + 1
          const key = `${year}-${month}-${day}`
          const dayItems = itemsByDate[key] || []
          const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year
          const hasDone = dayItems.some((it) => it.status === 'done')
          const hasPending = dayItems.some((it) => it.status !== 'done')
          return (
            <div
              key={day}
              className={`aspect-square flex flex-col items-center justify-center rounded-lg text-xs transition-colors ${
                isToday ? 'bg-indigo-600 text-white font-bold' : 'hover:bg-slate-50'
              }`}
            >
              <span>{day}</span>
              {dayItems.length > 0 && (
                <span className={`w-1.5 h-1.5 rounded-full mt-0.5 ${
                  hasDone && !hasPending ? 'bg-green-400' : hasPending ? 'bg-amber-400' : 'bg-slate-300'
                }`} />
              )}
            </div>
          )
        })}
      </div>
    </Card>
  )
}

export default function StudyPlannerPage() {
  const toast = useToast()
  const [plan, setPlan] = useState(null)
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [form, setForm] = useState({ exam_date: '', topics: '' })
  const [creating, setCreating] = useState(false)
  const [toggling, setToggling] = useState(null)

  useEffect(() => {
    plannerApi.getPlan()
      .then(({ data }) => {
        setPlan(data)
        setItems(data?.items ?? [])
      })
      .catch((err) => {
        if (err.response?.status !== 404) toast({ type: 'error', message: 'โหลดแผนการเรียนไม่สำเร็จ' })
      })
      .finally(() => setLoading(false))
  }, [toast])

  const handleCreate = async () => {
    if (!form.exam_date) { toast({ type: 'error', message: 'กรุณาระบุวันสอบ' }); return }
    setCreating(true)
    try {
      const { data } = await plannerApi.createPlan({
        exam_date: form.exam_date,
        topics: form.topics.split('\n').map((t) => t.trim()).filter(Boolean),
      })
      setPlan(data)
      setItems(data?.items ?? [])
      setShowCreateModal(false)
      toast({ type: 'success', message: 'สร้างแผนการเรียนแล้ว!' })
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'สร้างแผนไม่สำเร็จ' })
    } finally {
      setCreating(false)
    }
  }

  const handleMarkDone = async (item) => {
    if (item.status === 'done') return
    setToggling(item.id)
    try {
      await plannerApi.markItemDone(item.id)
      setItems((prev) => prev.map((it) => it.id === item.id ? { ...it, status: 'done' } : it))
      toast({ type: 'success', message: 'ทำเครื่องหมายเสร็จแล้ว!' })
    } catch {
      toast({ type: 'error', message: 'อัปเดตสถานะไม่สำเร็จ' })
    } finally {
      setToggling(null)
    }
  }

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  const doneCount = items.filter((i) => i.status === 'done').length
  const totalCount = items.length
  const pct = totalCount > 0 ? Math.round((doneCount / totalCount) * 100) : 0

  // Group by date
  const grouped = items.reduce((acc, item) => {
    const date = item.scheduled_date || 'ไม่ระบุวัน'
    if (!acc[date]) acc[date] = []
    acc[date].push(item)
    return acc
  }, {})

  return (
    <div>
      <PageHeader
        title="แผนการอ่านหนังสือ"
        subtitle={plan ? `วันสอบ: ${plan.exam_date ? new Date(plan.exam_date).toLocaleDateString('th-TH') : '—'}` : undefined}
        action={
          <Button onClick={() => setShowCreateModal(true)}>
            {plan ? '🔄 สร้างแผนใหม่' : '+ สร้างแผนการเรียน'}
          </Button>
        }
      />

      {!plan ? (
        <EmptyState
          icon="📅"
          title="ยังไม่มีแผนการเรียน"
          description="ให้ AI ช่วยวางแผนการอ่านหนังสือก่อนสอบ"
          action={<Button onClick={() => setShowCreateModal(true)}>สร้างแผนการเรียน</Button>}
        />
      ) : (
        <div className="grid lg:grid-cols-3 gap-5">
          {/* Left: calendar + progress */}
          <div className="space-y-4">
            <MiniCalendar items={items} />
            <Card>
              <h3 className="text-sm font-semibold text-slate-800 mb-3">ความคืบหน้า</h3>
              <div className="mb-2 flex justify-between text-xs text-slate-500">
                <span>{doneCount}/{totalCount} หัวข้อ</span>
                <span>{pct}%</span>
              </div>
              <div className="h-3 bg-slate-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-indigo-600 rounded-full transition-all"
                  style={{ width: `${pct}%` }}
                />
              </div>
            </Card>
          </div>

          {/* Right: task list */}
          <div className="lg:col-span-2 space-y-4">
            {Object.entries(grouped).map(([date, dateItems]) => (
              <Card key={date} padding={false}>
                <div className="px-4 py-3 border-b border-slate-100">
                  <h3 className="text-sm font-semibold text-slate-800">
                    {date === 'ไม่ระบุวัน' ? date : new Date(date).toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long' })}
                  </h3>
                </div>
                <ul className="divide-y divide-slate-100">
                  {dateItems.map((item) => (
                    <li key={item.id} className="flex items-center gap-3 px-4 py-3">
                      <button
                        onClick={() => handleMarkDone(item)}
                        disabled={item.status === 'done' || toggling === item.id}
                        className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors ${
                          item.status === 'done'
                            ? 'bg-green-500 border-green-500'
                            : 'border-slate-300 hover:border-indigo-500'
                        }`}
                      >
                        {item.status === 'done' && <span className="text-white text-xs">✓</span>}
                        {toggling === item.id && <Spinner size="sm" />}
                      </button>
                      <div className="flex-1 min-w-0">
                        <p className={`text-sm ${item.status === 'done' ? 'line-through text-slate-400' : 'text-slate-900'}`}>
                          {item.topic || item.title || 'หัวข้อ'}
                        </p>
                        {item.document_title && (
                          <p className="text-xs text-slate-400 truncate">จาก: {item.document_title}</p>
                        )}
                      </div>
                      {item.estimated_minutes && (
                        <span className="text-xs text-slate-400 flex-shrink-0">{item.estimated_minutes} นาที</span>
                      )}
                      <Badge variant={item.status === 'done' ? 'success' : 'default'}>
                        {item.status === 'done' ? 'เสร็จแล้ว' : 'รอทำ'}
                      </Badge>
                    </li>
                  ))}
                </ul>
              </Card>
            ))}
          </div>
        </div>
      )}

      <Modal open={showCreateModal} onClose={() => setShowCreateModal(false)} title="สร้างแผนการเรียน">
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">วันสอบ</label>
            <input
              type="date"
              value={form.exam_date}
              onChange={(e) => setForm((f) => ({ ...f, exam_date: e.target.value }))}
              min={new Date().toISOString().split('T')[0]}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">
              หัวข้อที่ต้องเรียน (แต่ละบรรทัด)
            </label>
            <textarea
              rows={5}
              value={form.topics}
              onChange={(e) => setForm((f) => ({ ...f, topics: e.target.value }))}
              placeholder={"บทที่ 1: ระบบนิเวศ\nบทที่ 2: พันธุกรรม\nบทที่ 3: วิวัฒนาการ"}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowCreateModal(false)}>ยกเลิก</Button>
            <Button loading={creating} onClick={handleCreate}>สร้างแผน</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
