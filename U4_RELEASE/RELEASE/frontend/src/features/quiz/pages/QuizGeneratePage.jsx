import { useState, useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { quizApi } from '../../../api/quiz'
import { documentsApi } from '../../../api/documents'
import { Button, Card, PageHeader } from '../../../components/ui'
import { useToast } from '../../../hooks'

const QUESTION_TYPES = [
  { value: 'multiple_choice', label: '🔘 Multiple Choice' },
  { value: 'true_false', label: '✓ True / False' },
  { value: 'short_answer', label: '✏️ Short Answer' },
  { value: 'fill_blank', label: '_ Fill in the Blank' },
  { value: 'matching', label: '↔ Matching' },
]

export default function QuizGeneratePage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const toast = useToast()

  const [documents, setDocuments] = useState([])
  const [form, setForm] = useState({
    document_id: searchParams.get('docId') || '',
    question_type: 'multiple_choice',
    question_count: 10,
    difficulty: 'medium',
    title: '',
  })
  const [loading, setLoading] = useState(false)
  const [docsLoading, setDocsLoading] = useState(true)

  useEffect(() => {
    documentsApi.list({ status: 'done' })
      .then(({ data }) => setDocuments(Array.isArray(data) ? data : data.data ?? []))
      .catch(() => toast({ type: 'error', message: 'โหลดรายการเอกสารไม่สำเร็จ' }))
      .finally(() => setDocsLoading(false))
  }, [toast])

  const handleChange = (name, value) =>
    setForm((f) => ({ ...f, [name]: value }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.document_id) { toast({ type: 'error', message: 'กรุณาเลือกเอกสาร' }); return }
    setLoading(true)
    try {
      await quizApi.generate(form.document_id, {
        question_type: form.question_type,
        count: Number(form.question_count),
        difficulty: form.difficulty,
        title: form.title || undefined,
      })
      toast({ type: 'success', message: 'สร้างข้อสอบสำเร็จ!' })
      navigate('/quizzes')
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'สร้างข้อสอบไม่สำเร็จ' })
    } finally {
      setLoading(false)
    }
  }

  const selectedDoc = documents.find((d) => String(d.id) === String(form.document_id))

  return (
    <div className="max-w-xl mx-auto">
      <PageHeader
        title="สร้างข้อสอบ"
        subtitle="AI จะสร้างข้อสอบจากเนื้อหาในเอกสารของคุณ"
        action={<Button variant="ghost" onClick={() => navigate('/quizzes')}>← กลับ</Button>}
      />

      <form onSubmit={handleSubmit} className="space-y-5">
        <Card>
          <h3 className="text-sm font-semibold text-slate-800 mb-3">เลือกเอกสาร</h3>
          {docsLoading ? (
            <div className="text-sm text-slate-400">กำลังโหลดเอกสาร...</div>
          ) : documents.length === 0 ? (
            <div className="text-sm text-slate-500">
              ยังไม่มีเอกสารที่ประมวลผลเสร็จ{' '}
              <button type="button" onClick={() => navigate('/documents/upload')} className="text-indigo-600 hover:underline">
                อัปโหลดเอกสาร
              </button>
            </div>
          ) : (
            <select
              value={form.document_id}
              onChange={(e) => handleChange('document_id', e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">-- เลือกเอกสาร --</option>
              {documents.map((d) => (
                <option key={d.id} value={d.id}>{d.title || d.filename}</option>
              ))}
            </select>
          )}
          {selectedDoc && (
            <p className="mt-2 text-xs text-slate-400">
              {selectedDoc.pages_count ? `${selectedDoc.pages_count} หน้า · ` : ''}
              ประมวลผลแล้ว ✓
            </p>
          )}
        </Card>

        <Card>
          <h3 className="text-sm font-semibold text-slate-800 mb-3">ประเภทข้อสอบ</h3>
          <div className="grid grid-cols-1 gap-2">
            {QUESTION_TYPES.map((t) => (
              <label key={t.value} className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                form.question_type === t.value
                  ? 'border-indigo-400 bg-indigo-50'
                  : 'border-slate-200 hover:border-slate-300'
              }`}>
                <input
                  type="radio"
                  name="question_type"
                  value={t.value}
                  checked={form.question_type === t.value}
                  onChange={() => handleChange('question_type', t.value)}
                  className="accent-indigo-600"
                />
                <span className="text-sm text-slate-800">{t.label}</span>
              </label>
            ))}
          </div>
        </Card>

        <Card>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                จำนวนข้อ: <span className="text-indigo-600 font-semibold">{form.question_count}</span>
              </label>
              <input
                type="range" min={5} max={50} step={5}
                value={form.question_count}
                onChange={(e) => handleChange('question_count', e.target.value)}
                className="w-full accent-indigo-600"
              />
              <div className="flex justify-between text-xs text-slate-400 mt-1">
                <span>5 ข้อ</span><span>50 ข้อ</span>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">ระดับความยาก</label>
              <div className="flex gap-2">
                {['easy', 'medium', 'hard'].map((d) => (
                  <button
                    key={d} type="button"
                    onClick={() => handleChange('difficulty', d)}
                    className={`flex-1 py-1.5 text-sm rounded-lg border transition-colors ${
                      form.difficulty === d
                        ? d === 'easy' ? 'bg-green-100 border-green-400 text-green-700'
                          : d === 'medium' ? 'bg-amber-100 border-amber-400 text-amber-700'
                          : 'bg-red-100 border-red-400 text-red-700'
                        : 'border-slate-200 text-slate-600 hover:border-slate-300'
                    }`}
                  >
                    {d === 'easy' ? 'ง่าย' : d === 'medium' ? 'ปานกลาง' : 'ยาก'}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                ชื่อชุดข้อสอบ (ไม่บังคับ)
              </label>
              <input
                type="text"
                value={form.title}
                onChange={(e) => handleChange('title', e.target.value)}
                placeholder="เช่น ทดสอบบทที่ 1-3"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>
        </Card>

        <Button type="submit" loading={loading} className="w-full" size="lg">
          ✨ สร้างข้อสอบด้วย AI
        </Button>
      </form>
    </div>
  )
}
