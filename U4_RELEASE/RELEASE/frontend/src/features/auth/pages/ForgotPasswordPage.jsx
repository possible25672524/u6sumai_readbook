import { useState } from 'react'
import { Link } from 'react-router-dom'
import { authApi } from '../../../api/auth'
import { Button } from '../../../components/ui'

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!email.trim()) { setError('กรุณาระบุอีเมล'); return }
    setLoading(true)
    setError(null)
    try {
      await authApi.forgotPassword({ email })
      setSent(true)
    } catch (err) {
      setError(err.response?.data?.message || 'ไม่พบอีเมลนี้ในระบบ')
    } finally {
      setLoading(false)
    }
  }

  if (sent) {
    return (
      <div className="text-center space-y-4">
        <div className="text-4xl">📧</div>
        <h2 className="text-base font-semibold text-slate-900">ตรวจสอบอีเมลของคุณ</h2>
        <p className="text-sm text-slate-500">
          เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยัง <strong>{email}</strong> แล้ว
          กรุณาตรวจสอบกล่องจดหมายและกล่อง Spam
        </p>
        <Link to="/login" className="block text-sm text-indigo-600 hover:underline">
          กลับหน้าเข้าสู่ระบบ
        </Link>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <h2 className="text-base font-semibold text-slate-900 mb-1">ลืมรหัสผ่าน?</h2>
        <p className="text-sm text-slate-500">
          ระบุอีเมลที่ลงทะเบียนไว้ เราจะส่งลิงก์รีเซ็ตรหัสผ่านให้
        </p>
      </div>

      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
          {error}
        </div>
      )}

      <div>
        <label className="block text-sm font-medium text-slate-700 mb-1">อีเมล</label>
        <input
          type="email"
          value={email}
          onChange={(e) => { setEmail(e.target.value); setError(null) }}
          placeholder="example@email.com"
          required
          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      <Button type="submit" loading={loading} className="w-full">
        ส่งลิงก์รีเซ็ตรหัสผ่าน
      </Button>

      <p className="text-center text-sm text-slate-500">
        <Link to="/login" className="text-indigo-600 hover:underline">
          ← กลับหน้าเข้าสู่ระบบ
        </Link>
      </p>
    </form>
  )
}
