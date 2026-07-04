import { create } from 'zustand'

export const useUiStore = create((set) => ({
  sidebarOpen: true,
  toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
  setSidebarOpen: (v) => set({ sidebarOpen: v }),

  toast: null,  // { type: 'success'|'error'|'info', message: string }
  showToast: (toast) => set({ toast }),
  clearToast: () => set({ toast: null }),

  globalLoading: false,
  setGlobalLoading: (v) => set({ globalLoading: v }),
}))
