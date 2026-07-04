import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { flashcardsApi } from '../../../api/flashcards'
import { documentsApi } from '../../../api/documents'
import { useFlashcardStore } from '../../../store/featureStores'
import { Button, Card, PageHeader, EmptyState, Spinner, Badge, Modal } from '../../../components/ui'
import { useToast } from '../../../hooks'

export default function FlashcardSetListPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const { sets, setSets } = useFlashcardStore()
  const [loading, setLoading] = useState(true)
  const [showGenModal, setShowGenModal] = useState(false)
  const [documents, setDocuments] = useState([])
  const [selectedDoc, setSelectedDoc] = useState('')
  const [generating, setGenerating] = useState(false)

  useEffect(() => {
    flashcardsApi.listSets()
      .then(({ data }) => setSets(Array.isArray(data) ? data : data.data ?? []))
      .catch(() => toast({ type: 'error', message: 'โหลดรายการ Flash Cards ไม่สำเร็จ' }))
      .finally(() => setLoading(false))
  }, [setSets, toast])

  const openGenModal = async () => {
    setShowGenModal(true)
    try {
      const { data } = await documentsApi.list({ status: 'done' })
      setDocuments(Array.isArray(data) ? data : data.data ?? [])
    } catch {
      toast({ type: 'error', message: 'โหลดรายการเอกสารไม่สำเร็จ' })
    }
  }

  const handleGenerate = async () => {
    if (!selectedDoc) { toast({ type: 'error', message: 'กรุณาเลือกเอกสาร' }); return }
    setGenerating(true)
    try {
      const { data } = await flashcardsApi.generate(selectedDoc)
      setSets((prev) => [data, ...(Array.isArray(prev) ? prev : [])])
      toast({ type: 'success', message: 'สร้าง Flash Cards สำเร็จ!' })
      setShowGenModal(false)
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'สร้างไม่สำเร็จ' })
    } finally {
      setGenerating(false)
    }
  }

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  return (
    <div>
      <PageHeader
        title="Flash Cards"
        subtitle="ทบทวนด้วยวิธี Spaced Repetition"
        action={<Button onClick={openGenModal}>+ สร้าง Flash Cards</Button>}
      />

      {sets.length === 0 ? (
        <EmptyState
          icon="🃏"
          title="ยังไม่มี Flash Cards"
          description="สร้าง Flash Cards จากเอกสารของคุณโดยใช้ AI"
          action={<Button onClick={openGenModal}>สร้าง Flash Cards</Button>}
        />
      ) : (
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {sets.map((set) => (
            <Card key={set.id} className="cursor-pointer hover:border-indigo-300 transition-colors" onClick={() => navigate(`/flashcards/${set.id}/review`)}>
              <div className="flex items-start gap-3 mb-3">
                <div className="text-2xl">🃏</div>
                <div className="flex-1 min-w-0">
                  <h3 className="text-sm font-medium text-slate-900 truncate">
                    {set.title || set.document?.title || 'Flash Card Set'}
                  </h3>
                  <p className="text-xs text-slate-400 mt-0.5">
                    {set.document?.title ?? '—'}
                  </p>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <Badge variant="purple">{set.card_count ?? '?'} ใบ</Badge>
                <div className="flex gap-1 text-xs text-slate-400">
                  {set.due_count > 0 && (
                    <Badge variant="warning">{set.due_count} ครบกำหนด</Badge>
                  )}
                </div>
              </div>
              <Button className="w-full mt-3" size="sm" onClick={(e) => { e.stopPropagation(); navigate(`/flashcards/${set.id}/review`) }}>
                เริ่มทบทวน
              </Button>
            </Card>
          ))}
        </div>
      )}

      <Modal open={showGenModal} onClose={() => setShowGenModal(false)} title="สร้าง Flash Cards">
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">เลือกเอกสาร</label>
            <select
              value={selectedDoc}
              onChange={(e) => setSelectedDoc(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">-- เลือกเอกสาร --</option>
              {documents.map((d) => (
                <option key={d.id} value={d.id}>{d.title || d.filename}</option>
              ))}
            </select>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowGenModal(false)}>ยกเลิก</Button>
            <Button loading={generating} onClick={handleGenerate}>สร้าง Flash Cards</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
