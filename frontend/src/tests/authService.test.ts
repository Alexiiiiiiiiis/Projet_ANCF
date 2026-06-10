import { describe, it, expect, vi, beforeEach } from 'vitest'
import { authService } from '../services/authService'
import { api } from '../services/api'

vi.mock('../services/api', () => ({
  api: {
    post: vi.fn(),
    get: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const mockApi = api as {
  post: ReturnType<typeof vi.fn>
  get: ReturnType<typeof vi.fn>
  put: ReturnType<typeof vi.fn>
  delete: ReturnType<typeof vi.fn>
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('authService.login', () => {
  it('returns token and user on success', async () => {
    const payload = { token: 'abc123', user: { id: 1, email: 'test@test.fr' } }
    mockApi.post.mockResolvedValueOnce({ data: payload })

    const result = await authService.login({ email: 'test@test.fr', password: 'pass1234' })

    expect(mockApi.post).toHaveBeenCalledWith('/auth/login_check', {
      email: 'test@test.fr',
      password: 'pass1234',
    })
    expect(result.token).toBe('abc123')
  })

  it('throws on invalid credentials', async () => {
    mockApi.post.mockRejectedValueOnce(new Error('401'))
    await expect(authService.login({ email: 'x@x.fr', password: 'wrong' })).rejects.toThrow()
  })
})

describe('authService.register', () => {
  it('calls /auth/register with correct payload', async () => {
    const user = { id: 2, email: 'new@test.fr' }
    mockApi.post.mockResolvedValueOnce({ data: user })

    const result = await authService.register({
      email: 'new@test.fr',
      password: 'Password1!',
      firstName: 'Jean',
      lastName: 'Dupont',
    })

    expect(mockApi.post).toHaveBeenCalledWith('/auth/register', expect.objectContaining({ email: 'new@test.fr' }))
    expect(result).toEqual(user)
  })
})

describe('authService.forgotPassword', () => {
  it('sends email and returns message', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { message: 'Email envoyé', debug_token: 'tok123' } })

    const result = await authService.forgotPassword('user@test.fr')

    expect(mockApi.post).toHaveBeenCalledWith('/auth/forgot-password', { email: 'user@test.fr' })
    expect(result.message).toBe('Email envoyé')
    expect(result.debug_token).toBe('tok123')
  })
})

describe('authService.resetPassword', () => {
  it('calls /auth/reset-password with token and newPassword', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { message: 'OK' } })

    await authService.resetPassword('mytoken', 'NewPass123!')

    expect(mockApi.post).toHaveBeenCalledWith('/auth/reset-password', {
      token: 'mytoken',
      newPassword: 'NewPass123!',
    })
  })

  it('throws on invalid token', async () => {
    mockApi.post.mockRejectedValueOnce(new Error('400'))
    await expect(authService.resetPassword('bad', 'pass1234')).rejects.toThrow()
  })
})

describe('authService.changePassword', () => {
  it('calls /auth/change-password', async () => {
    mockApi.put.mockResolvedValueOnce({ data: {} })
    await authService.changePassword('old', 'new12345')
    expect(mockApi.put).toHaveBeenCalledWith('/auth/change-password', {
      currentPassword: 'old',
      newPassword: 'new12345',
    })
  })
})

describe('authService.deleteAccount', () => {
  it('calls DELETE /auth/account', async () => {
    mockApi.delete.mockResolvedValueOnce({ data: {} })
    await authService.deleteAccount('mypassword')
    expect(mockApi.delete).toHaveBeenCalledWith('/auth/account', { data: { password: 'mypassword' } })
  })
})
