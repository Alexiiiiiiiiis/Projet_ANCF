import axios from 'axios'

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api'

export const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
})

// Ajoute le token JWT à chaque requête
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('ancf_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Gère les 401 pour toute l'application — mais seulement pour une session expirée/invalide, pas pour un
// login qui échoue simplement (login_check renvoie aussi 401 sur un mauvais mot de passe :
// rediriger dans ce cas empêcherait LoginPage d'afficher son message d'erreur inline).
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // 503 émis par MaintenanceListener : prévenir l'application pour qu'elle recharge la
    // configuration et bascule sur l'écran d'attente, au lieu d'afficher une erreur par widget.
    if (error.response?.status === 503 && error.response?.data?.maintenance) {
      window.dispatchEvent(new Event('ancf:maintenance'))
    }

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
