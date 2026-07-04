import { Outlet } from 'react-router-dom'

export default function AuthLayout() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-900 px-4">
      <div className="w-full max-w-sm">
        <h1 className="mb-6 text-center text-2xl font-semibold text-white">
          AI Study Assistant
        </h1>
        <div className="rounded-xl bg-white p-6 shadow-lg">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
