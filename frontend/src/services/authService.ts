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
  async login(payload: LoginPayload): Promise<LoginResponse> {
    const { data } = await api.post<LoginResponse>('/auth/login_check', payload)
    return data
  },

  async register(payload: RegisterPayload): Promise<User> {
    const { data } = await api.post<User>('/auth/register', payload)
    return data
  },

  async getProfile(): Promise<User> {
    const { data } = await api.get<User>('/auth/me')
    return data
  },

  async updateProfile(payload: Partial<Pick<User, 'firstName' | 'lastName'>>): Promise<User> {
    const { data } = await api.put<User>('/auth/me', payload)
    return data
  },

  async changePassword(currentPassword: string, newPassword: string): Promise<void> {
    await api.put('/auth/change-password', { currentPassword, newPassword })
  },

  async deleteAccount(password: string): Promise<void> {
    await api.delete('/auth/account', { data: { password } })
  },

  async forgotPassword(email: string): Promise<{ message: string; debug_token?: string }> {
    const { data } = await api.post('/auth/forgot-password', { email })
    return data
  },

  async resetPassword(token: string, newPassword: string): Promise<void> {
    await api.post('/auth/reset-password', { token, newPassword })
  },
}
