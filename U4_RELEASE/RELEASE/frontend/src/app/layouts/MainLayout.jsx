
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'
import { useUiStore } from '../../store/uiStore'
import { Toast, Spinner } from '../../components/ui'

const NAV_ITEMS = [
  { to: '/dashboard',  label: 'แดชบอร์ด',      icon: '🏠' },
  { to: '/documents',  label: 'เอกสาร',          icon: '📁' },
  { to: '/summaries',  label: 'สรุปบทเรียน',    icon: '📝' },
  { to: '/flashcards', label: 'Flash Cards',     icon: '🃏' },
  { to: '/quizzes',    label: 'ข้อสอบ',           icon: '❓' },
  { to: '/chatbot',    label: 'ถาม AI',           icon: '💬' },
  { to: '/planner',    label: 'แผนการอ่าน',      icon: '📅' },
  { to: '/analytics',  label: 'สถิติ',            icon: '📊' },
]

const ADMIN_ITEMS = [
  { to: '/admin',              label: 'ภาพรวมระบบ',   icon: '⚙️' },
  { to: '/admin/users',        label: 'จัดการผู้ใช้', icon: '👥' },
  { to: '/admin/documents',    label: 'จัดการเอกสาร', icon: '📄' },
  { to: '/admin/queue',        label: 'คิวประมวลผล',  icon: '🔄' },
  { to: '/admin/logs',         label: 'Activity Logs', icon: '📋' },
]

// Simple breadcrumb from pathname
function useBreadcrumbs() {
  const location = useLocation()
  const LABELS = {
    dashboard: 'แดชบอร์ด',
    documents: 'เอกสาร', upload: 'อัปโหลด', summary: 'สรุป',
    summaries: 'สรุปบทเรียน',
    flashcards: 'Flash Cards', review: 'ทบทวน',
    quizzes: 'ข้อสอบ', generate: 'สร้างข้อสอบ', attempts: 'การทำข้อสอบ', result: 'ผลคะแนน',
    chatbot: 'ถาม AI',
    planner: 'แผนการอ่าน',
    analytics: 'สถิติ',
    admin: 'Admin', users: 'ผู้ใช้', queue: 'คิว',
  }
  const parts = location.pathname.split('/').filter(Boolean)
  return parts.map((p, i) => ({
    label: LABELS[p] || p,
    path: '/' + parts.slice(0, i + 1).join('/'),
    isLast: i === parts.length - 1,
  }))
}

export default function MainLayout() {
  const { user, logout } = useAuthStore()
  const { sidebarOpen, toggleSidebar, toast, clearToast, globalLoading } = useUiStore()
  const breadcrumbs = useBreadcrumbs()
  const isAdmin = user?.role === 'admin'

  return (
    <div className="flex min-h-screen bg-slate-50">
      {/* Sidebar */}
      <aside className={`${sidebarOpen ? 'w-56' : 'w-14'} shrink-0 bg-slate-900 text-slate-100 flex flex-col transition-all duration-200`}>
        {/* Logo */}
        <div className="flex items-center gap-2 px-4 py-4 border-b border-slate-800">
          <span className="text-indigo-400 text-lg">🎓</span>
          {sidebarOpen && (
            <span className="text-sm font-semibold tracking-tight truncate">AI Study</span>
          )}
          <button
            onClick={toggleSidebar}
            className="ml-auto text-slate-500 hover:text-slate-300 text-xs"
            aria-label="toggle sidebar"
          >
            {sidebarOpen ? '‹' : '›'}
          </button>
        </div>

        {/* Nav */}
        <nav className="flex-1 px-1.5 py-2 space-y-0.5 overflow-y-auto">
          {NAV_ITEMS.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors ${
                  isActive ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                }`
              }
            >
              <span className="text-base flex-shrink-0">{item.icon}</span>
              {sidebarOpen && <span className="truncate">{item.label}</span>}
            </NavLink>
          ))}

          {/* Admin section */}
          {isAdmin && (
            <>
              {sidebarOpen && (
                <div className="px-3 pt-4 pb-1">
                  <p className="text-xs font-medium text-slate-500 uppercase tracking-wide">Admin</p>
                </div>
              )}
              {ADMIN_ITEMS.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.to === '/admin'}
                  className={({ isActive }) =>
                    `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors ${
                      isActive ? 'bg-amber-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    }`
                  }
                >
                  <span className="text-base flex-shrink-0">{item.icon}</span>
                  {sidebarOpen && <span className="truncate">{item.label}</span>}
                </NavLink>
              ))}
            </>
          )}
        </nav>

        {/* User footer */}
        <div className="px-2 py-3 border-t border-slate-800">
          <div className={`flex items-center gap-2 px-2 ${!sidebarOpen ? 'justify-center' : ''}`}>
            <div className="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
              {user?.name?.[0]?.toUpperCase() ?? 'U'}
            </div>
            {sidebarOpen && (
              <div className="flex-1 min-w-0">
                <p className="text-xs font-medium text-slate-200 truncate">{user?.name}</p>
                <p className="text-xs text-slate-500 truncate">{user?.role}</p>
              </div>
            )}
          </div>
          {sidebarOpen && (
            <button
              onClick={logout}
              className="mt-2 w-full rounded-lg px-3 py-1.5 text-xs text-slate-400 hover:text-white hover:bg-slate-800 text-left transition-colors"
            >
              ออกจากระบบ
            </button>
          )}
        </div>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top bar */}
        <header className="h-12 flex items-center px-5 bg-white border-b border-slate-200 gap-3">
          {/* Breadcrumbs */}
          <nav className="flex items-center gap-1 text-xs text-slate-400 min-w-0 flex-1">
            {breadcrumbs.map((crumb) => (
              <span key={crumb.path} className="flex items-center gap-1">
                {!crumb.isLast ? (
                  <>
                    <NavLink to={crumb.path} className="hover:text-indigo-600 truncate max-w-24">
                      {crumb.label}
                    </NavLink>
                    <span>/</span>
                  </>
                ) : (
                  <span className="text-slate-700 font-medium truncate">{crumb.label}</span>
                )}
              </span>
            ))}
          </nav>

          {/* Global loading indicator */}
          {globalLoading && <Spinner size="sm" />}

          {/* Role badge */}
          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
            isAdmin ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'
          }`}>
            {user?.role === 'admin' ? 'Admin' : user?.role === 'teacher' ? 'Teacher' : 'Student'}
          </span>
        </header>

        {/* Content */}
        <main className="flex-1 p-5 overflow-auto">
          <Outlet />
        </main>
      </div>

      {/* Toast */}
      <Toast toast={toast} onClose={clearToast} />
    </div>
  )
}
