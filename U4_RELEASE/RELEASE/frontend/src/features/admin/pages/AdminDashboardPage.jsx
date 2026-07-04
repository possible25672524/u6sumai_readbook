import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { adminApi } from '../../../api/admin'
import { Card, PageHeader, Spinner, Badge } from '../../../components/ui'
import { useToast } from '../../../hooks'

export default function AdminDashboardPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    adminApi.stats()
      .then(({ data }) => setStats(data))
      .catch(() => toast({ type: 'error', message: 'โหลดสถิติระบบไม่สำเร็จ' }))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  const s = stats || {}

  return (
    <div>
      <PageHeader title="ภาพรวมระบบ" subtitle="Admin Dashboard" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {[
          { icon: '👥', label: 'ผู้ใช้ทั้งหมด', value: s.total_users ?? 0, to: '/admin/users' },
          { icon: '📄', label: 'เอกสารทั้งหมด', value: s.total_documents ?? 0, to: '/admin/documents' },
          { icon: '🔄', label: 'Jobs รอดำเนินการ', value: s.pending_jobs ?? 0, to: '/admin/queue' },
          { icon: '❌', label: 'Jobs ล้มเหลว', value: s.failed_jobs ?? 0, to: '/admin/queue' },
        ].map((c) => (
          <Card
            key={c.label}
            className="cursor-pointer hover:border-indigo-300 transition-colors"
            onClick={() => navigate(c.to)}
          >
            <div className="flex items-center gap-3">
              <span className="text-2xl">{c.icon}</span>
              <div>
                <p className="text-2xl font-bold text-slate-900">{c.value}</p>
                <p className="text-xs text-slate-500">{c.label}</p>
              </div>
            </div>
          </Card>
        ))}
      </div>

      {/* Quick links */}
      <div className="grid sm:grid-cols-3 gap-4">
        {[
          { to: '/admin/users', icon: '👥', label: 'จัดการผู้ใช้', desc: 'เพิ่ม / แก้ไข / ลบ / เปลี่ยน role' },
          { to: '/admin/documents', icon: '📄', label: 'จัดการเอกสาร', desc: 'ดูเอกสารทั้งหมดในระบบ' },
          { to: '/admin/queue', icon: '🔄', label: 'คิวประมวลผล', desc: 'ติดตาม OCR / Embedding jobs' },
        ].map((l) => (
          <Card
            key={l.to}
            className="cursor-pointer hover:border-indigo-300 transition-colors"
            onClick={() => navigate(l.to)}
          >
            <div className="text-3xl mb-2">{l.icon}</div>
            <h3 className="text-sm font-semibold text-slate-900 mb-1">{l.label}</h3>
            <p className="text-xs text-slate-500">{l.desc}</p>
          </Card>
        ))}
      </div>
    </div>
  )
}
