import { createContext, useContext, useEffect, useMemo, useState, type PropsWithChildren } from 'react'
import { api, type Principal } from './api'

type AuthContextValue = {
  principal: Principal | null
  loading: boolean
  setupAvailable: boolean
  login: (email: string, password: string) => Promise<Principal>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: PropsWithChildren) {
  const [principal, setPrincipal] = useState<Principal | null>(null)
  const [loading, setLoading] = useState(true)
  const [setupAvailable, setSetupAvailable] = useState(false)

  const refresh = async () => {
    const setup = await api<{ available: boolean }>('/setup/status').catch(() => ({ available: false }))
    setSetupAvailable(setup.available)
    const current = await api<Principal>('/auth/me').catch(() => null)
    setPrincipal(current)
    setLoading(false)
  }

  useEffect(() => {
    queueMicrotask(() => void refresh())
  }, [])

  const value = useMemo<AuthContextValue>(() => ({
    principal,
    loading,
    setupAvailable,
    refresh,
    login: async (email, password) => {
      const current = await api<Principal>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) })
      setPrincipal(current)
      return current
    },
    logout: async () => {
      await api<void>('/auth/logout', { method: 'POST' })
      setPrincipal(null)
    },
  }), [loading, principal, setupAvailable])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside AuthProvider')
  return context
}
