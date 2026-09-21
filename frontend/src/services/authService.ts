import { api } from './api'
import type { User } from '../types/transport'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  email: string
  password: string
  firstName: string
  lastName: string
}

export interface LoginResponse {
  token: string
  user: User
}

export const authService = {
  /** Envoie l'email et le mot de passe, renvoie le token JWT. */
  async login(payload: LoginPayload): Promise<LoginResponse> {
    const { data } = await api.post<LoginResponse>('/auth/login_check', payload)
    return data
  },

  /** Crée un nouveau compte. */
  async register(payload: RegisterPayload): Promise<User> {
    const { data } = await api.post<User>('/auth/register', payload)
    return data
  },

  /** Récupère le profil de l'utilisateur connecté. */
  async getProfile(): Promise<User> {
    const { data } = await api.get<User>('/auth/me')
    return data
  },

  /** Modifie le prénom et le nom. */
  async updateProfile(payload: Partial<Pick<User, 'firstName' | 'lastName'>>): Promise<User> {
    const { data } = await api.put<User>('/auth/me', payload)
    return data
  },

  /** Change le mot de passe (l'ancien est demandé). */
  async changePassword(currentPassword: string, newPassword: string): Promise<void> {
    await api.put('/auth/change-password', { currentPassword, newPassword })
  },

  /** Supprime le compte après confirmation par mot de passe. */
  async deleteAccount(password: string): Promise<void> {
    await api.delete('/auth/account', { data: { password } })
  },

  /** Demande l'envoi d'un lien de réinitialisation par email. */
  async forgotPassword(email: string): Promise<{ message: string; debug_token?: string }> {
    const { data } = await api.post('/auth/forgot-password', { email })
    return data
  },

  /** Définit un nouveau mot de passe avec le token reçu par email. */
  async resetPassword(token: string, newPassword: string): Promise<void> {
    await api.post('/auth/reset-password', { token, newPassword })
  },
}
