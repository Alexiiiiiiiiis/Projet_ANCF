import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { authService } from '../services/authService'
import { useAuth } from '../context/AuthContext'
import { Spinner } from '../components/ui/Spinner'
import { BackButton } from '../components/ui/BackButton'

/** Page d'inscription. */
export function RegisterPage() {
  const { login } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState({ firstName: '', lastName: '', email: '', password: '' })
  const [confirmPassword, setConfirmPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  /** Met à jour un champ du formulaire à chaque frappe. */
  const set = (field: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [field]: e.target.value }))

  /** Vérifie le mot de passe, crée le compte puis connecte l'utilisateur. */
  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    if (form.password.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères.')
      return
    }
    if (form.password !== confirmPassword) {
      setError('Les deux mots de passe ne correspondent pas.')
      return
    }
    setError('')
    setLoading(true)
    try {
      await authService.register(form)
      await login(form.email, form.password)
      navigate('/')
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response?.status
      if (status === 409) setError('Cet email est déjà utilisé.')
      else setError('Une erreur est survenue. Réessayez.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen min-h-dvh items-start justify-center px-4 py-6 sm:items-center sm:py-12">
      <div className="w-full max-w-md">
        <div className="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
          <BackButton />
          <h1 className="mb-1 text-2xl font-bold text-gray-900">Créer un compte</h1>
          <p className="mb-6 text-sm text-gray-500">Rejoignez Transport ANCF</p>

          {error && (
            <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
          )}

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Prénom</label>
                <input
                  type="text"
                  required
                  value={form.firstName}
                  onChange={set('firstName')}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Nom</label>
                <input
                  type="text"
                  required
                  value={form.lastName}
                  onChange={set('lastName')}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Email</label>
              <input
                type="email"
                required
                value={form.email}
                onChange={set('email')}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Mot de passe</label>
              <input
                type="password"
                required
                minLength={8}
                value={form.password}
                onChange={set('password')}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="8 caractères minimum"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Confirmer le mot de passe</label>
              <input
                type="password"
                required
                minLength={8}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className="flex items-center justify-center gap-2 rounded-lg bg-blue-700 py-3 font-semibold text-white hover:bg-blue-800 disabled:opacity-60 transition-colors"
            >
              {loading && <Spinner size="sm" />}
              Créer mon compte
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-gray-500">
            Déjà un compte ?{' '}
            <Link to="/connexion" className="font-semibold text-blue-700 hover:underline">
              Se connecter
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
