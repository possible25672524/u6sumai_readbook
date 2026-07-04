import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { authApi } from '../../../api/auth'
import { useAuthStore } from '../../../store/authStore'
import { Button } from '../../../components/ui'

export default function RegisterPage() {
  const navigate = useNavigate()
  const setSession = useAuthStore((s) => s.setSession)
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((f) => ({ ...f, [name]: value }))
    setErrors((e) => ({ ...e, [name]: null }))
  }

  const validate = () => {
    const errs = {}
    if (!form.name.trim()) errs.name = 'กรุณาระบุชื่อ'
    if (!form.email.trim()) errs.email = 'กรุณาระบุอีเมล'
    if (form.password.length < 8) errs.password = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'
    if (form.password !== form.password_confirmation) errs.password_confirmation = 'รหัสผ่านไม่ตรงกัน'
    return errs
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }
    setLoading(true)
    try {
      const { data } = await authApi.register(form)
      setSession(data.user, data.token)
      navigate('/dashboard')
    } catch (err) {
      const serverErrors = err.response?.data?.errors || {}
      setErrors(serverErrors)
      if (!Object.keys(serverErrors).length) {
        setErrors({ _general: err.response?.data?.message || 'สมัครสมาชิกไม่สำเร็จ' })
      }
    } finally {
      setLoading(false)
    }
  }

  const field = (name, label, type = 'text', placeholder = '') => (
    <div>
      <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
      <input
        type={type}
        name={name}
        value={form[name]}
        onChange={handleChange}
        placeholder={placeholder}
        required
        className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
          errors[name] ? 'border-red-400 bg-red-50' : 'border-slate-300'
        }`}
      />
      {errors[name] && <p className="mt-1 text-xs text-red-600">{errors[name]}</p>}
    </div>
  )

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="mb-2">
        <p className="text-center text-slate-500 text-sm">สร้างบัญชีใหม่</p>
      </div>

      {errors._general && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
          {errors._general}
        </div>
      )}

      {field('name', 'ชื่อ-นามสกุล', 'text', 'กรุณาระบุชื่อของคุณ')}
      {field('email', 'อีเมล', 'email', 'example@email.com')}
      {field('password', 'รหัสผ่าน', 'password', 'อย่างน้อย 8 ตัวอักษร')}
      {field('password_confirmation', 'ยืนยันรหัสผ่าน', 'password', 'ใส่รหัสผ่านอีกครั้ง')}

      <Button type="submit" loading={loading} className="w-full mt-2">
        สมัครสมาชิก
      </Button>

      <p className="text-center text-sm text-slate-500">
        มีบัญชีอยู่แล้ว?{' '}
        <Link to="/login" className="text-indigo-600 font-medium hover:underline">
          เข้าสู่ระบบ
        </Link>
      </p>
    </form>
  )
}
