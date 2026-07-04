import { NavLink, Outlet } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'

const NAV_ITEMS = [
  { to: '/dashboard', label: 'แดชบอร์ด' },
  { to: '/documents', label: 'เอกสารของฉัน' },
  { to: '/flashcards', label: 'Flash Cards' },
  { to: '/quizzes', label: 'ข้อสอบ' },
  { to: '/chatbot', label: 'ถาม AI' },
  { to: '/planner', label: 'แผนการอ่าน' },
  { to: '/analytics', label: 'สถิติการเรียน' },
]

export default function MainLayout() {
  const { user, logout } = useAuthStore()

  return (
    <div className="flex min-h-screen bg-slate-50">
      <aside className="w-60 shrink-0 bg-slate-900 text-slate-100 flex flex-col">
        <div className="px-5 py-4 text-lg font-semibold tracking-tight">
          AI Study Assistant
        </div>
        <nav className="flex-1 px-2 space-y-1">
          {NAV_ITEMS.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `block rounded-md px-3 py-2 text-sm transition-colors ${
                  isActive ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
          {user?.role === 'admin' && (
            <NavLink
              to="/admin"
              className={({ isActive }) =>
                `block rounded-md px-3 py-2 text-sm transition-colors ${
                  isActive ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800'
                }`
              }
            >
              จัดการระบบ (Admin)
            </NavLink>
          )}
        </nav>
        <div className="px-4 py-4 border-t border-slate-800 text-sm">
          <div className="mb-2 truncate">{user?.name ?? 'ผู้ใช้'}</div>
          <button
            onClick={logout}
            className="w-full rounded-md bg-slate-800 px-3 py-1.5 text-left text-slate-300 hover:bg-slate-700"
          >
            ออกจากระบบ
          </button>
        </div>
      </aside>

      <main className="flex-1 p-6">
        <Outlet />
      </main>
    </div>
  )
}
