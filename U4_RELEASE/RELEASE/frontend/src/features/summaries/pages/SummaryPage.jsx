import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { summariesApi } from '../../../api/summaries'
import { documentsApi } from '../../../api/documents'
import { Button, Card, PageHeader, Spinner } from '../../../components/ui'
import { useToast } from '../../../hooks'

const SUMMARY_TYPES = [
  { value: 'short', label: '📋 สั้น' },
  { value: 'detailed', label: '📖 ละเอียด' },
  { value: 'bullet', label: '• Bullet Points' },
  { value: 'exam', label: '🎓 เน้นสอบ' },
  { value: 'mindmap', label: '🗺️ Mind Map' },
  { value: 'table', label: '📊 ตาราง' },
  { value: 'keypoints', label: '🔑 Key Points' },
]

export default function SummaryPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const toast = useToast()

  const [doc, setDoc] = useState(null)
  const [summaries, setSummaries] = useState({}) // { type: content }
  const [activeType, setActiveType] = useState('short')
  const [generating, setGenerating] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.all([
      documentsApi.get(id),
      summariesApi.list(id),
    ]).then(([docRes, sumRes]) => {
      setDoc(docRes.data)
      // index by type
      const map = {}
      const list = Array.isArray(sumRes.data) ? sumRes.data : sumRes.data?.data ?? []
      list.forEach((s) => { map[s.type] = s.content })
      setSummaries(map)
    }).catch(() => {
      toast({ type: 'error', message: 'โหลดข้อมูลไม่สำเร็จ' })
    }).finally(() => setLoading(false))
  }, [id, toast])

  const handleGenerate = async () => {
    if (summaries[activeType]) {
      // already generated — just show
      return
    }
    setGenerating(true)
    try {
      const { data } = await summariesApi.generate(id, { type: activeType })
      setSummaries((prev) => ({ ...prev, [activeType]: data.content }))
      toast({ type: 'success', message: 'สร้างสรุปสำเร็จ!' })
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'สร้างสรุปไม่สำเร็จ' })
    } finally {
      setGenerating(false)
    }
  }

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  const currentContent = summaries[activeType]

  return (
    <div className="max-w-3xl mx-auto">
      <PageHeader
        title="สรุปบทเรียน"
        subtitle={doc?.title || doc?.filename || ''}
        action={
          <Button variant="ghost" onClick={() => navigate(`/documents/${id}`)}>← กลับ</Button>
        }
      />

      {/* Type selector */}
      <div className="flex flex-wrap gap-2 mb-5">
        {SUMMARY_TYPES.map((t) => (
          <button
            key={t.value}
            onClick={() => setActiveType(t.value)}
            className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${
              activeType === t.value
                ? 'bg-indigo-600 text-white border-indigo-600'
                : summaries[t.value]
                  ? 'bg-green-50 text-green-700 border-green-200'
                  : 'bg-white text-slate-600 border-slate-300 hover:border-indigo-300'
            }`}
          >
            {t.label}
            {summaries[t.value] && activeType !== t.value && (
              <span className="ml-1 text-green-500">✓</span>
            )}
          </button>
        ))}
      </div>

      <Card>
        {currentContent ? (
          <div>
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-sm font-semibold text-slate-800">
                {SUMMARY_TYPES.find((t) => t.value === activeType)?.label}
              </h2>
              <Button
                size="sm"
                variant="outline"
                loading={generating}
                onClick={handleGenerate}
              >
                🔄 สร้างใหม่
              </Button>
            </div>
            <div className="prose prose-sm max-w-none text-slate-700 leading-relaxed whitespace-pre-wrap">
              {currentContent}
            </div>
          </div>
        ) : (
          <div className="flex flex-col items-center py-10 text-center">
            <div className="text-4xl mb-3">
              {SUMMARY_TYPES.find((t) => t.value === activeType)?.label.split(' ')[0]}
            </div>
            <p className="text-sm text-slate-600 mb-1 font-medium">
              ยังไม่มีสรุปประเภทนี้
            </p>
            <p className="text-xs text-slate-400 mb-5">
              ใช้ Claude Sonnet เพื่อสร้างสรุป{SUMMARY_TYPES.find((t) => t.value === activeType)?.label}
            </p>
            <Button onClick={handleGenerate} loading={generating}>
              ✨ สร้างสรุปด้วย AI
            </Button>
          </div>
        )}
      </Card>
    </div>
  )
}
