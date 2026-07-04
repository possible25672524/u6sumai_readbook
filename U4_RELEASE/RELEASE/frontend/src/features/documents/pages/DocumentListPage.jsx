import { useState, useEffect, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { documentsApi } from '../../../api/documents'
import { useDocumentStore } from '../../../store/featureStores'
import {
  Button, Card, PageHeader, EmptyState, StatusBadge, Spinner,
} from '../../../components/ui'
import { useToast, useDebounce } from '../../../hooks'

const FILE_ICONS = {
  'application/pdf': '📄',
  'image/': '🖼️',
  'audio/': '🎵',
  'video/': '🎬',
}
function fileIcon(mimeType = '') {
  for (const [prefix, icon] of Object.entries(FILE_ICONS)) {
    if (mimeType.startsWith(prefix)) return icon
  }
  return '📁'
}

function formatSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export default function DocumentListPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const { documents, setDocuments, removeDocument } = useDocumentStore()

  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [deleting, setDeleting] = useState(null)
  const debouncedSearch = useDebounce(search, 400)

  const fetchDocs = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await documentsApi.list({ search: debouncedSearch || undefined })
      setDocuments(Array.isArray(data) ? data : data.data ?? [])
    } catch {
      toast({ type: 'error', message: 'โหลดรายการเอกสารไม่สำเร็จ' })
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, setDocuments, toast])

  useEffect(() => { fetchDocs() }, [fetchDocs])

  const handleDelete = async (doc) => {
    if (!window.confirm(`ลบเอกสาร "${doc.title || doc.filename}" ใช่หรือไม่?`)) return
    setDeleting(doc.id)
    try {
      await documentsApi.delete(doc.id)
      removeDocument(doc.id)
      toast({ type: 'success', message: 'ลบเอกสารแล้ว' })
    } catch {
      toast({ type: 'error', message: 'ลบไม่สำเร็จ' })
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div>
      <PageHeader
        title="เอกสารของฉัน"
        subtitle={`${documents.length} เอกสาร`}
        action={
          <Button onClick={() => navigate('/documents/upload')}>
            + อัปโหลดเอกสาร
          </Button>
        }
      />

      {/* Search */}
      <div className="mb-5">
        <div className="relative max-w-md">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="ค้นหาเอกสาร..."
            className="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : documents.length === 0 ? (
        <EmptyState
          icon="📂"
          title={search ? 'ไม่พบเอกสารที่ค้นหา' : 'ยังไม่มีเอกสาร'}
          description={search ? 'ลองเปลี่ยนคำค้นหา' : 'เริ่มต้นด้วยการอัปโหลดเอกสารแรกของคุณ'}
          action={
            !search && (
              <Button onClick={() => navigate('/documents/upload')}>
                อัปโหลดเอกสาร
              </Button>
            )
          }
        />
      ) : (
        <div className="grid gap-3">
          {documents.map((doc) => (
            <Card key={doc.id} padding={false}>
              <div className="flex items-center gap-4 p-4">
                <div className="text-3xl flex-shrink-0">
                  {fileIcon(doc.mime_type)}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <Link
                      to={`/documents/${doc.id}`}
                      className="text-sm font-medium text-slate-900 hover:text-indigo-600 truncate"
                    >
                      {doc.title || doc.filename || 'ไม่มีชื่อ'}
                    </Link>
                    <StatusBadge status={doc.processing_status || 'pending'} />
                  </div>
                  <div className="flex items-center gap-3 text-xs text-slate-400">
                    <span>{formatSize(doc.size_bytes)}</span>
                    <span>·</span>
                    <span>{doc.created_at ? new Date(doc.created_at).toLocaleDateString('th-TH') : ''}</span>
                    {doc.pages_count && (
                      <>
                        <span>·</span>
                        <span>{doc.pages_count} หน้า</span>
                      </>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                  {doc.processing_status === 'done' && (
                    <>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => navigate(`/documents/${doc.id}/summary`)}
                      >
                        สรุป
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => navigate(`/quizzes/generate?docId=${doc.id}`)}
                      >
                        ข้อสอบ
                      </Button>
                    </>
                  )}
                  <Button
                    size="sm"
                    variant="ghost"
                    loading={deleting === doc.id}
                    onClick={() => handleDelete(doc)}
                    className="text-red-500 hover:text-red-700 hover:bg-red-50"
                  >
                    ลบ
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
