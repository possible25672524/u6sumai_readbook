import { useEffect, useState, useCallback } from 'react'
import { adminApi } from '../../../api/admin'
import { Button, Card, PageHeader, Spinner, StatusBadge } from '../../../components/ui'
import { useToast, useDebounce } from '../../../hooks'

export default function AdminDocumentsPage() {
  const toast = useToast()
  const [documents, setDocuments] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [deleting, setDeleting] = useState(null)
  const debouncedSearch = useDebounce(search, 400)

  const fetchDocs = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await adminApi.listDocuments({ search: debouncedSearch || undefined })
      setDocuments(Array.isArray(data) ? data : data.data ?? [])
    } catch {
      toast({ type: 'error', message: 'โหลดรายการเอกสารไม่สำเร็จ' })
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, toast])

  useEffect(() => { fetchDocs() }, [fetchDocs])

  const handleDelete = async (doc) => {
    if (!window.confirm(`ลบเอกสาร "${doc.title || doc.filename}" ใช่หรือไม่?`)) return
    setDeleting(doc.id)
    try {
      await adminApi.deleteDocument(doc.id)
      setDocuments((prev) => prev.filter((d) => d.id !== doc.id))
      toast({ type: 'success', message: 'ลบเอกสารแล้ว' })
    } catch {
      toast({ type: 'error', message: 'ลบไม่สำเร็จ' })
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div>
      <PageHeader title="จัดการเอกสาร" subtitle={`${documents.length} เอกสาร`} />

      <div className="mb-5 max-w-sm relative">
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="ค้นหาเอกสาร..."
          className="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
      </div>

      {loading ? (
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      ) : (
        <Card padding={false}>
          <table className="w-full text-sm">
            <thead className="bg-slate-50 border-b border-slate-200">
              <tr>
                {['เอกสาร', 'เจ้าของ', 'สถานะ', 'ขนาด', 'วันที่', 'จัดการ'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {documents.map((doc) => (
                <tr key={doc.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 max-w-xs">
                    <p className="font-medium text-slate-900 truncate">{doc.title || doc.filename}</p>
                    <p className="text-xs text-slate-400 truncate">{doc.filename}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{doc.user?.name ?? '—'}</td>
                  <td className="px-4 py-3"><StatusBadge status={doc.processing_status || 'pending'} /></td>
                  <td className="px-4 py-3 text-slate-400 text-xs">
                    {doc.size_bytes ? `${(doc.size_bytes / (1024 * 1024)).toFixed(1)} MB` : '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-400 text-xs">
                    {doc.created_at ? new Date(doc.created_at).toLocaleDateString('th-TH') : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <Button
                      size="sm" variant="ghost"
                      loading={deleting === doc.id}
                      onClick={() => handleDelete(doc)}
                      className="text-red-500 hover:text-red-700 hover:bg-red-50"
                    >
                      ลบ
                    </Button>
                  </td>
                </tr>
              ))}
              {documents.length === 0 && (
                <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400">ไม่พบเอกสาร</td></tr>
              )}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
