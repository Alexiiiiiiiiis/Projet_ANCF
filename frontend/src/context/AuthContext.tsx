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

/** Fournit l'utilisateur connecté et les fonctions de connexion/déconnexion à toute l'application. */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  /** Supprime le token et l'utilisateur du navigateur (déconnexion). */
  const logout = useCallback(() => {
    localStorage.removeItem('ancf_token')
    localStorage.removeItem('ancf_user')
    setUser(null)
  }, [])

  /** Recharge le profil depuis l'API si un token existe, sinon déconnecte. */
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

  /** Connecte l'utilisateur : récupère le token JWT puis son profil. */
  const login = useCallback(async (email: string, password: string) => {
    const { token } = await authService.login({ email, password })
    localStorage.setItem('ancf_token', token)
    try {
      const profile = await authService.getProfile()
      localStorage.setItem('ancf_user', JSON.stringify(profile))
      setUser(profile)
    } catch (err) {
      // Le login a réussi mais la récupération du profil a échoué (coupure réseau...) :
      // ne pas laisser un token dans le localStorage sans utilisateur associé, sous peine
      // de connecter silencieusement l'utilisateur au prochain rechargement de page.
      localStorage.removeItem('ancf_token')
      localStorage.removeItem('ancf_user')
      throw err
    }
  }, [])

  const isAdmin = user?.roles?.includes('ROLE_ADMIN') ?? false

  return (
    <AuthContext.Provider value={{ user, isLoading, isAdmin, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  )
}

/** Hook pour lire l'utilisateur connecté et les fonctions d'authentification. */
// eslint-disable-next-line react-refresh/only-export-components -- hook colocalisé avec son Provider, pattern standard
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
