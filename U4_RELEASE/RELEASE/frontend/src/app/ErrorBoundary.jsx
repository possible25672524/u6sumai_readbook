import { Component } from 'react'

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false, error: null }
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error }
  }

  componentDidCatch(error, info) {
    // In production, send to monitoring service
    console.error('[ErrorBoundary]', error, info)
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
          <div className="max-w-md w-full text-center">
            <div className="text-6xl mb-4">💥</div>
            <h1 className="text-xl font-semibold text-slate-900 mb-2">เกิดข้อผิดพลาดในแอปพลิเคชัน</h1>
            <p className="text-sm text-slate-500 mb-6">
              {this.state.error?.message || 'เกิดข้อผิดพลาดที่ไม่คาดคิด'}
            </p>
            <div className="flex justify-center gap-3">
              <button
                onClick={() => this.setState({ hasError: false, error: null })}
                className="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500"
              >
                ลองใหม่
              </button>
              <button
                onClick={() => window.location.replace('/')}
                className="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50"
              >
                กลับหน้าแรก
              </button>
            </div>
            {import.meta.env.DEV && (
              <details className="mt-6 text-left">
                <summary className="text-xs text-slate-400 cursor-pointer">Stack trace (dev only)</summary>
                <pre className="mt-2 text-xs text-red-600 overflow-auto bg-red-50 p-3 rounded-lg max-h-60">
                  {this.state.error?.stack}
                </pre>
              </details>
            )}
          </div>
        </div>
      )
    }
    return this.props.children
  }
}
