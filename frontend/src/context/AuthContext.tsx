import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react'
import { authService } from '../services/authService'
import type { User } from '../types/transport'

interface AuthContextValue {
  user: User | null
  isLoading: boolean
  isAdmin: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  refreshUser: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  const logout = useCallback(() => {
    localStorage.removeItem('ancf_token')
    localStorage.removeItem('ancf_user')
    setUser(null)
  }, [])

  const refreshUser = useCallback(async () => {
    const token = localStorage.getItem('ancf_token')
    if (!token) {
      setIsLoading(false)
      return
    }
    try {
      const profile = await authService.getProfile()
      setUser(profile)
      localStorage.setItem('ancf_user', JSON.stringify(profile))
    } catch {
      logout()
    } finally {
      setIsLoading(false)
    }
  }, [logout])

  useEffect(() => {
    refreshUser()
  }, [refreshUser])

  const login = useCallback(async (email: string, password: string) => {
    const { token } = await authService.login({ email, password })
    localStorage.setItem('ancf_token', token)
    const profile = await authService.getProfile()
    localStorage.setItem('ancf_user', JSON.stringify(profile))
    setUser(profile)
  }, [])

  const isAdmin = user?.roles?.includes('ROLE_ADMIN') ?? false

  return (
    <AuthContext.Provider value={{ user, isLoading, isAdmin, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
