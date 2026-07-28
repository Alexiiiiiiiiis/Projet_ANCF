import axios from 'axios'

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api'

export const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
})

// Attach JWT token on every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('ancf_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 globally — mais seulement pour une session expirée/invalide, pas pour un
// login qui échoue simplement (login_check renvoie aussi 401 sur un mauvais mot de passe :
// rediriger dans ce cas empêcherait LoginPage d'afficher son message d'erreur inline).
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const isLoginAttempt = error.config?.url?.includes('/auth/login_check')
    if (error.response?.status === 401 && !isLoginAttempt) {
      localStorage.removeItem('ancf_token')
      localStorage.removeItem('ancf_user')
      window.location.href = '/connexion'
    }
    return Promise.reject(error)
  }
)

export default api
