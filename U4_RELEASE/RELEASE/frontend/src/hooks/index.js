import { useState, useEffect, useRef, useCallback } from 'react'
import { useUiStore } from '../store/uiStore'

// ─── useToast ─────────────────────────────────────────────────────────────
export function useToast() {
  const showToast = useUiStore((s) => s.showToast)
  const clearToast = useUiStore((s) => s.clearToast)

  const toast = useCallback(
    ({ type = 'info', message, duration = 3500 }) => {
      showToast({ type, message })
      setTimeout(clearToast, duration)
    },
    [showToast, clearToast],
  )

  return toast
}

// ─── useApi ───────────────────────────────────────────────────────────────
// generic hook for one-shot API calls
export function useApi(apiFn, { immediate = false, params } = {}) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(immediate)
  const [error, setError] = useState(null)

  const execute = useCallback(
    async (...args) => {
      setLoading(true)
      setError(null)
      try {
        const res = await apiFn(...args)
        setData(res.data)
        return res.data
      } catch (err) {
        setError(err.response?.data?.message || 'เกิดข้อผิดพลาด')
        throw err
      } finally {
        setLoading(false)
      }
    },
    [apiFn],
  )

  useEffect(() => {
    if (immediate) execute(params)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [immediate])

  return { data, loading, error, execute, setData }
}

// ─── usePolling ────────────────────────────────────────────────────────────
// polls apiFn every `interval` ms until stopCondition(data) is true
export function usePolling(apiFn, { interval = 3000, stopCondition, enabled = true } = {}) {
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const timerRef = useRef(null)
  const stoppedRef = useRef(false)

  const poll = useCallback(async () => {
    if (stoppedRef.current) return
    try {
      const res = await apiFn()
      setData(res.data)
      if (stopCondition && stopCondition(res.data)) {
        stoppedRef.current = true
        return
      }
    } catch (err) {
      setError(err.response?.data?.message || 'เกิดข้อผิดพลาด')
    }
    if (!stoppedRef.current) {
      timerRef.current = setTimeout(poll, interval)
    }
  }, [apiFn, interval, stopCondition])

  useEffect(() => {
    if (!enabled) return
    stoppedRef.current = false
    poll()
    return () => {
      stoppedRef.current = true
      clearTimeout(timerRef.current)
    }
  }, [poll, enabled])

  return { data, error }
}

// ─── useDebounce ──────────────────────────────────────────────────────────
export function useDebounce(value, delay = 400) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}

// ─── useLocalStorage ──────────────────────────────────────────────────────
export function useLocalStorage(key, initial) {
  const [value, setValue] = useState(() => {
    try {
      const stored = localStorage.getItem(key)
      return stored ? JSON.parse(stored) : initial
    } catch {
      return initial
    }
  })
  const set = useCallback(
    (v) => {
      setValue(v)
      try { localStorage.setItem(key, JSON.stringify(v)) } catch {}
    },
    [key],
  )
  return [value, set]
}
