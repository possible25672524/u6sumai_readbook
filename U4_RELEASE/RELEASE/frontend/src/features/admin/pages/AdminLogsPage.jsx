import { useEffect, useState, useCallback } from 'react'
import { adminApi } from '../../../api/admin'
import { Card, PageHeader, Spinner, Badge } from '../../../components/ui'
import { useToast, useDebounce } from '../../../hooks'

export default function AdminLogsPage() {
  const toast = useToast()
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounce(search, 400)

  const fetchLogs = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await adminApi.listLogs({ search: debouncedSearch || undefined })
      setLogs(Array.isArray(data) ? data : data.data ?? [])
    } catch {
      toast({ type: 'error', message: 'โหลด Activity Logs ไม่สำเร็จ' })
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, toast])

  useEffect(() => { fetchLogs() }, [fetchLogs])

  const ACTION_COLORS = {
    create: 'success', update: 'info', delete: 'danger',
    login: 'purple', logout: 'default', upload: 'info',
  }

  return (
    <div>
      <PageHeader title="Activity Logs" subtitle="บันทึกกิจกรรมในระบบ" />

      <div className="mb-5 max-w-sm relative">
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="ค้นหาผู้ใช้หรือกิจกรรม..."
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
                {['เวลา', 'ผู้ใช้', 'กิจกรรม', 'รายละเอียด', 'IP'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {logs.map((log) => (
                <tr key={log.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                    {log.created_at ? new Date(log.created_at).toLocaleString('th-TH') : '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-700">{log.user?.name ?? '—'}</td>
                  <td className="px-4 py-3">
                    <Badge variant={ACTION_COLORS[log.action] || 'default'}>{log.action}</Badge>
                  </td>
                  <td className="px-4 py-3 text-slate-600 max-w-xs truncate">{log.description || '—'}</td>
                  <td className="px-4 py-3 text-xs text-slate-400 font-mono">{log.ip_address || '—'}</td>
                </tr>
              ))}
              {logs.length === 0 && (
                <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400">ไม่พบ Activity Logs</td></tr>
              )}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
