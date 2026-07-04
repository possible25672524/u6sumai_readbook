import { Link, useNavigate } from 'react-router-dom'
import { Button } from '../../../components/ui'

export function NotFoundPage() {
  const navigate = useNavigate()
  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
      <div className="max-w-sm w-full text-center">
        <div className="text-8xl font-bold text-slate-200 mb-2">404</div>
        <h1 className="text-xl font-semibold text-slate-900 mb-2">ไม่พบหน้าที่ต้องการ</h1>
        <p className="text-sm text-slate-500 mb-6">
          หน้าที่คุณค้นหาไม่มีอยู่หรือถูกย้ายไปแล้ว
        </p>
        <div className="flex justify-center gap-3">
          <Button onClick={() => navigate(-1)} variant="secondary">← ย้อนกลับ</Button>
          <Button as={Link} to="/dashboard">กลับหน้าหลัก</Button>
        </div>
      </div>
    </div>
  )
}

export function UnauthorizedPage() {
  const navigate = useNavigate()
  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
      <div className="max-w-sm w-full text-center">
        <div className="text-6xl mb-4">🔒</div>
        <h1 className="text-xl font-semibold text-slate-900 mb-2">ไม่มีสิทธิ์เข้าถึง</h1>
        <p className="text-sm text-slate-500 mb-6">
          คุณไม่มีสิทธิ์เข้าถึงหน้านี้ กรุณาติดต่อผู้ดูแลระบบ
        </p>
        <Button onClick={() => navigate('/dashboard')}>กลับหน้าหลัก</Button>
      </div>
    </div>
  )
}
