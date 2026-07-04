import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export const useAuthStore = create(
  persist(
    (set, get) => ({
      user: null,   // { id, name, email, role: 'admin'|'teacher'|'student' }
      token: null,
      isAuthenticated: false,
      initializing: true,  // true while we verify stored token on app boot

      setSession: (user, token) =>
        set({ user, token, isAuthenticated: true, initializing: false }),

      updateUser: (user) => set({ user }),

      logout: () => {
        set({ user: null, token: null, isAuthenticated: false, initializing: false })
        // also clear persisted storage
        try { localStorage.removeItem('study-ai-auth') } catch {}
      },

      setInitializing: (v) => set({ initializing: v }),

      // role helpers
      hasRole: (...roles) => {
        const { user } = get()
        return user ? roles.includes(user.role) : false
      },
      isAdmin: () => get().user?.role === 'admin',
      isTeacher: () => ['admin', 'teacher'].includes(get().user?.role),
    }),
    {
      name: 'study-ai-auth',
      partialize: (state) => ({ user: state.user, token: state.token, isAuthenticated: state.isAuthenticated }),
    },
  ),
)
