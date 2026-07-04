import { useEffect, useState, useCallback } from 'react'
import { adminApi } from '../../../api/admin'
import { Button, Card, PageHeader, Spinner, Badge, Modal } from '../../../components/ui'
import { useToast, useDebounce } from '../../../hooks'

const ROLES = ['student', 'teacher', 'admin']
const ROLE_COLORS = { admin: 'danger', teacher: 'warning', student: 'info' }

export default function AdminUsersPage() {
  const toast = useToast()
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [editUser, setEditUser] = useState(null)
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(null)
  const debouncedSearch = useDebounce(search, 400)

  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await adminApi.listUsers({ search: debouncedSearch || undefined })
      setUsers(Array.isArray(data) ? data : data.data ?? [])
    } catch {
      toast({ type: 'error', message: 'โหลดรายการผู้ใช้ไม่สำเร็จ' })
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, toast])

  useEffect(() => { fetchUsers() }, [fetchUsers])

  const handleSave = async () => {
    if (!editUser) return
    setSaving(true)
    try {
      const { data } = await adminApi.updateUser(editUser.id, { role: editUser.role, name: editUser.name })
      setUsers((prev) => prev.map((u) => u.id === data.id ? data : u))
      toast({ type: 'success', message: 'อัปเดตผู้ใช้แล้ว' })
      setEditUser(null)
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'อัปเดตไม่สำเร็จ' })
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (user) => {
    if (!window.confirm(`ลบผู้ใช้ "${user.name}" ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้`)) return
    setDeleting(user.id)
    try {
      await adminApi.deleteUser(user.id)
      setUsers((prev) => prev.filter((u) => u.id !== user.id))
      toast({ type: 'success', message: 'ลบผู้ใช้แล้ว' })
    } catch {
      toast({ type: 'error', message: 'ลบไม่สำเร็จ' })
    } finally {
      setDeleting(null)
    }
  }

  return (
    <div>
      <PageHeader
        title="จัดการผู้ใช้"
        subtitle={`${users.length} คน`}
      />

      {/* Search */}
      <div className="mb-5 max-w-sm relative">
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="ค้นหาชื่อ หรืออีเมล..."
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
                {['ชื่อ', 'อีเมล', 'Role', 'วันที่สมัคร', 'จัดการ'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {users.map((user) => (
                <tr key={user.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <div className="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">
                        {user.name?.[0]?.toUpperCase()}
                      </div>
                      <span className="font-medium text-slate-900">{user.name}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{user.email}</td>
                  <td className="px-4 py-3">
                    <Badge variant={ROLE_COLORS[user.role] || 'default'}>{user.role}</Badge>
                  </td>
                  <td className="px-4 py-3 text-slate-400">
                    {user.created_at ? new Date(user.created_at).toLocaleDateString('th-TH') : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <Button size="sm" variant="ghost" onClick={() => setEditUser({ ...user })}>
                        แก้ไข
                      </Button>
                      <Button
                        size="sm" variant="ghost"
                        loading={deleting === user.id}
                        onClick={() => handleDelete(user)}
                        className="text-red-500 hover:text-red-700 hover:bg-red-50"
                      >
                        ลบ
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {users.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-10 text-center text-slate-400">ไม่พบผู้ใช้</td>
                </tr>
              )}
            </tbody>
          </table>
        </Card>
      )}

      {/* Edit modal */}
      <Modal open={!!editUser} onClose={() => setEditUser(null)} title="แก้ไขผู้ใช้">
        {editUser && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">ชื่อ</label>
              <input
                type="text"
                value={editUser.name}
                onChange={(e) => setEditUser((u) => ({ ...u, name: e.target.value }))}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">อีเมล (ไม่แก้ไขได้)</label>
              <input disabled value={editUser.email}
                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400" />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Role</label>
              <div className="flex gap-2">
                {ROLES.map((r) => (
                  <button
                    key={r} type="button"
                    onClick={() => setEditUser((u) => ({ ...u, role: r }))}
                    className={`flex-1 py-2 text-sm rounded-lg border transition-colors ${
                      editUser.role === r ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium' : 'border-slate-200 text-slate-600'
                    }`}
                  >
                    {r}
                  </button>
                ))}
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="secondary" onClick={() => setEditUser(null)}>ยกเลิก</Button>
              <Button loading={saving} onClick={handleSave}>บันทึก</Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
