import { useEffect, useState, useCallback } from 'react'
import { adminApi } from '../../../api/admin'
import { Button, Card, PageHeader, Spinner, StatusBadge, Badge } from '../../../components/ui'
import { useToast } from '../../../hooks'

const STATUS_FILTER = ['all', 'pending', 'processing', 'done', 'failed']

export default function QueueMonitorPage() {
  const toast = useToast()
  const [jobs, setJobs] = useState([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState('all')
  const [retrying, setRetrying] = useState(null)
  const [autoRefresh, setAutoRefresh] = useState(true)

  const fetchJobs = useCallback(async () => {
    try {
      const { data } = await adminApi.listJobs({
        status: filter === 'all' ? undefined : filter,
      })
      setJobs(Array.isArray(data) ? data : data.data ?? [])
    } catch {
      toast({ type: 'error', message: 'โหลดข้อมูล Jobs ไม่สำเร็จ' })
    } finally {
      setLoading(false)
    }
  }, [filter, toast])

  useEffect(() => {
    setLoading(true)
    fetchJobs()
  }, [fetchJobs])

  // Auto-refresh every 5s when enabled
  useEffect(() => {
    if (!autoRefresh) return
    const t = setInterval(fetchJobs, 5000)
    return () => clearInterval(t)
  }, [autoRefresh, fetchJobs])

  const handleRetry = async (job) => {
    setRetrying(job.id)
    try {
      await adminApi.retryJob(job.id)
      toast({ type: 'success', message: 'ส่ง Job ใหม่แล้ว' })
      fetchJobs()
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'Retry ไม่สำเร็จ' })
    } finally {
      setRetrying(null)
    }
  }

  const JOB_TYPE_LABEL = {
    ocr: 'Tesseract OCR',
    transcribe: 'Whisper Transcribe',
    embed: 'OpenAI Embedding',
    summarize: 'Claude Summarize',
    generate_questions: 'Claude QA Gen',
    index_chroma: 'ChromaDB Index',
  }

  const counts = jobs.reduce((acc, j) => {
    acc[j.status] = (acc[j.status] || 0) + 1
    return acc
  }, {})

  return (
    <div>
      <PageHeader
        title="คิวประมวลผล"
        subtitle="ติดตาม OCR / Whisper / Embedding / Claude Jobs"
        action={
          <div className="flex items-center gap-2">
            <button
              onClick={() => setAutoRefresh((v) => !v)}
              className={`px-3 py-1.5 text-xs rounded-lg border transition-colors ${
                autoRefresh
                  ? 'bg-green-100 border-green-300 text-green-700'
                  : 'bg-slate-100 border-slate-300 text-slate-600'
              }`}
            >
              {autoRefresh ? '🔄 Auto-refresh: ON' : '⏸ Auto-refresh: OFF'}
            </button>
            <Button size="sm" variant="secondary" onClick={fetchJobs}>
              Refresh
            </Button>
          </div>
        }
      />

      {/* Status summary */}
      <div className="grid grid-cols-4 gap-3 mb-5">
        {[
          { key: 'pending', label: 'รอดำเนินการ', icon: '⏳', color: 'bg-slate-100 text-slate-700' },
          { key: 'processing', label: 'กำลังทำ', icon: '⚙️', color: 'bg-blue-100 text-blue-700' },
          { key: 'done', label: 'สำเร็จ', icon: '✅', color: 'bg-green-100 text-green-700' },
          { key: 'failed', label: 'ล้มเหลว', icon: '❌', color: 'bg-red-100 text-red-700' },
        ].map((s) => (
          <Card
            key={s.key}
            className={`cursor-pointer transition-all ${filter === s.key ? 'ring-2 ring-indigo-500' : ''}`}
            onClick={() => setFilter(filter === s.key ? 'all' : s.key)}
          >
            <div className="flex items-center gap-2">
              <span className={`text-xl w-9 h-9 rounded-lg flex items-center justify-center ${s.color}`}>
                {s.icon}
              </span>
              <div>
                <p className="text-xl font-bold text-slate-900">{counts[s.key] ?? 0}</p>
                <p className="text-xs text-slate-500">{s.label}</p>
              </div>
            </div>
          </Card>
        ))}
      </div>

      {/* Filter tabs */}
      <div className="flex gap-2 mb-4">
        {STATUS_FILTER.map((s) => (
          <button
            key={s}
            onClick={() => setFilter(s)}
            className={`px-3 py-1.5 text-xs rounded-lg font-medium transition-colors ${
              filter === s
                ? 'bg-indigo-600 text-white'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            {s === 'all' ? 'ทั้งหมด' : s}
            {s !== 'all' && counts[s] > 0 && (
              <span className="ml-1 opacity-70">({counts[s]})</span>
            )}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      ) : jobs.length === 0 ? (
        <Card>
          <p className="text-center text-slate-400 py-10">ไม่มี Jobs ในขณะนี้</p>
        </Card>
      ) : (
        <Card padding={false}>
          <table className="w-full text-sm">
            <thead className="bg-slate-50 border-b border-slate-200">
              <tr>
                {['Job ID', 'ประเภท', 'เอกสาร', 'สถานะ', 'เริ่ม / จบ', 'ข้อผิดพลาด', 'จัดการ'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {jobs.map((job) => (
                <tr key={job.id} className={`hover:bg-slate-50 ${job.status === 'failed' ? 'bg-red-50' : ''}`}>
                  <td className="px-4 py-3 font-mono text-xs text-slate-500">#{job.id}</td>
                  <td className="px-4 py-3">
                    <Badge variant="purple">{JOB_TYPE_LABEL[job.type] || job.type}</Badge>
                  </td>
                  <td className="px-4 py-3 max-w-xs">
                    <p className="text-slate-900 truncate">{job.document?.title || job.document?.filename || '—'}</p>
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={job.status} />
                    {job.status === 'processing' && (
                      <div className="mt-1 w-16 h-1 bg-slate-200 rounded-full overflow-hidden">
                        <div className="h-full bg-blue-500 rounded-full animate-pulse w-2/3" />
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-400">
                    <div>{job.started_at ? new Date(job.started_at).toLocaleTimeString('th-TH') : '—'}</div>
                    <div>{job.finished_at ? new Date(job.finished_at).toLocaleTimeString('th-TH') : ''}</div>
                  </td>
                  <td className="px-4 py-3 max-w-xs">
                    {job.error_message ? (
                      <p className="text-xs text-red-600 truncate" title={job.error_message}>
                        {job.error_message}
                      </p>
                    ) : '—'}
                  </td>
                  <td className="px-4 py-3">
                    {job.status === 'failed' && (
                      <Button
                        size="sm" variant="secondary"
                        loading={retrying === job.id}
                        onClick={() => handleRetry(job)}
                      >
                        Retry
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}

      {autoRefresh && (
        <p className="text-xs text-slate-400 text-center mt-3">
          🔄 รีเฟรชอัตโนมัติทุก 5 วินาที
        </p>
      )}
    </div>
  )
}
