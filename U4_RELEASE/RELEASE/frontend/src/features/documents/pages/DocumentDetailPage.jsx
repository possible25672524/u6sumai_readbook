import { useEffect, useCallback, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { documentsApi } from '../../../api/documents'
import { useDocumentStore } from '../../../store/featureStores'
import { Button, Card, PageHeader, StatusSpinner, EmptyState } from '../../../components/ui'
import { usePolling, useToast } from '../../../hooks'

function ProcessingProgress({ status }) {
  const steps = [
    { key: 'extracting', label: 'ดึงข้อความ (OCR/Whisper)' },
    { key: 'embedding', label: 'สร้าง Embeddings' },
    { key: 'indexing', label: 'บันทึก Vector Store' },
    { key: 'done', label: 'พร้อมใช้งาน' },
  ]
  const order = ['pending', 'extracting', 'embedding', 'indexing', 'done']
  const currentIdx = order.indexOf(status)

  return (
    <div className="space-y-3">
      {steps.map((step, i) => {
        const stepIdx = i + 1
        const done = currentIdx > stepIdx
        const active = currentIdx === stepIdx
        return (
          <div key={step.key} className="flex items-center gap-3">
            <div className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium flex-shrink-0 ${
              done ? 'bg-green-500 text-white' : active ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-400'
            }`}>
              {done ? '✓' : active ? <span className="animate-pulse">●</span> : stepIdx}
            </div>
            <span className={`text-sm ${active ? 'text-slate-900 font-medium' : done ? 'text-slate-600' : 'text-slate-400'}`}>
              {step.label}
            </span>
            {active && <Spinner size="sm" className="ml-1" />}
          </div>
        )
      })}
    </div>
  )
}

export default function DocumentDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const { current, setCurrent, setProcessingStatus } = useDocumentStore()
  const [loadError, setLoadError] = useState(null)

  // initial fetch
  useEffect(() => {
    documentsApi.get(id)
      .then(({ data }) => setCurrent(data))
      .catch(() => setLoadError('โหลดข้อมูลเอกสารไม่สำเร็จ'))
  }, [id, setCurrent])

  // Poll status until done or failed
  const pollFn = useCallback(() => documentsApi.processingStatus(id), [id])
  const { data: statusData } = usePolling(pollFn, {
    interval: 3000,
    enabled: current?.processing_status === 'pending' || current?.processing_status === 'processing' || current?.processing_status === 'extracting' || current?.processing_status === 'embedding',
    stopCondition: (d) => d?.status === 'done' || d?.status === 'failed',
  })

  useEffect(() => {
    if (statusData?.status) {
      setProcessingStatus(id, statusData.status)
      setCurrent((prev) => prev ? { ...prev, processing_status: statusData.status } : prev)
    }
  }, [statusData, id, setProcessingStatus, setCurrent])

  if (loadError) {
    return (
      <EmptyState icon="❌" title="โหลดไม่สำเร็จ" description={loadError}
        action={<Button onClick={() => navigate('/documents')}>กลับรายการเอกสาร</Button>} />
    )
  }

  if (!current || current.id !== id) {
    return <div className="flex justify-center py-16"><Spinner size="lg" /></div>
  }

  const doc = current
  const isDone = doc.processing_status === 'done'
  const isFailed = doc.processing_status === 'failed'
  const isProcessing = !isDone && !isFailed

  return (
    <div className="max-w-3xl mx-auto">
      <PageHeader
        title={doc.title || doc.filename || 'เอกสาร'}
        subtitle={
          <span className="flex items-center gap-2">
            <StatusBadge status={doc.processing_status || 'pending'} />
            <span className="text-slate-400">
              {doc.created_at ? new Date(doc.created_at).toLocaleDateString('th-TH') : ''}
            </span>
          </span>
        }
        action={
          <Button variant="ghost" onClick={() => navigate('/documents')}>
            ← เอกสารทั้งหมด
          </Button>
        }
      />

      {/* Processing state */}
      {isProcessing && (
        <Card className="mb-5">
          <h2 className="text-sm font-semibold text-slate-800 mb-4">กำลังประมวลผล...</h2>
          <ProcessingProgress status={doc.processing_status || 'pending'} />
          <p className="mt-4 text-xs text-slate-400">
            หน้านี้จะอัปเดตอัตโนมัติ ไม่ต้องรีเฟรช
          </p>
        </Card>
      )}

      {isFailed && (
        <Card className="mb-5 border-red-200 bg-red-50">
          <div className="flex items-start gap-3">
            <span className="text-2xl">❌</span>
            <div>
              <p className="text-sm font-semibold text-red-800">การประมวลผลล้มเหลว</p>
              <p className="text-sm text-red-600 mt-1">
                {doc.error_message || 'เกิดข้อผิดพลาดในการประมวลผลเอกสาร กรุณาลองลบและอัปโหลดใหม่'}
              </p>
            </div>
          </div>
        </Card>
      )}

      {/* Quick actions (only when done) */}
      {isDone && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
          {[
            { icon: '📝', label: 'สรุปบทเรียน', to: `/documents/${id}/summary` },
            { icon: '🃏', label: 'Flash Cards', action: 'flashcard' },
            { icon: '❓', label: 'สร้างข้อสอบ', to: `/quizzes/generate?docId=${id}` },
            { icon: '💬', label: 'ถาม AI', to: `/chatbot` },
          ].map((item) => (
            <button
              key={item.label}
              onClick={() => item.to ? navigate(item.to) : toast({ type: 'info', message: 'กำลังสร้าง Flash Cards...' })}
              className="flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 hover:border-indigo-300 transition-colors"
            >
              <span className="text-2xl">{item.icon}</span>
              <span className="text-xs font-medium text-slate-700">{item.label}</span>
            </button>
          ))}
        </div>
      )}

      {/* Document meta */}
      <Card>
        <h2 className="text-sm font-semibold text-slate-800 mb-3">ข้อมูลเอกสาร</h2>
        <dl className="space-y-2">
          {[
            ['ชื่อไฟล์', doc.filename],
            ['ประเภท', doc.mime_type],
            ['ขนาด', doc.size_bytes ? `${(doc.size_bytes / (1024 * 1024)).toFixed(2)} MB` : '—'],
            ['จำนวนหน้า / chunks', doc.pages_count ? `${doc.pages_count} หน้า` : '—'],
            ['วันที่อัปโหลด', doc.created_at ? new Date(doc.created_at).toLocaleString('th-TH') : '—'],
          ].filter(([, v]) => v).map(([k, v]) => (
            <div key={k} className="flex justify-between text-sm">
              <dt className="text-slate-500">{k}</dt>
              <dd className="text-slate-900 text-right">{v}</dd>
            </div>
          ))}
        </dl>
      </Card>

      {/* Preview excerpt */}
      {doc.excerpt && (
        <Card className="mt-4">
          <h2 className="text-sm font-semibold text-slate-800 mb-2">ตัวอย่างเนื้อหา</h2>
          <p className="text-sm text-slate-600 leading-relaxed whitespace-pre-line line-clamp-10">
            {doc.excerpt}
          </p>
        </Card>
      )}
    </div>
  )
}
