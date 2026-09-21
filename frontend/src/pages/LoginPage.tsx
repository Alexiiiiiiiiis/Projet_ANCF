import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Spinner } from '../components/ui/Spinner'
import { BackButton } from '../components/ui/BackButton'

/** Page de connexion. */
export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const from = (location.state as { from?: { pathname: string } })?.from?.pathname ?? '/'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  /** Connecte l'utilisateur puis le renvoie vers la page qu'il voulait ouvrir. */
  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(email, password)
      navigate(from, { replace: true })
    } catch {
      setError('Email ou mot de passe incorrect.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen min-h-dvh items-start justify-center px-4 py-6 sm:items-center sm:py-12">
      <div className="w-full max-w-md">
        <div className="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
          <BackButton />
          <h1 className="mb-1 text-2xl font-bold text-gray-900">Connexion</h1>
          <p className="mb-6 text-sm text-gray-500">Accédez à votre espace Transport ANCF</p>

          {error && (
            <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
          )}

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Email</label>
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="vous@exemple.fr"
              />
            </div>
            <div>
              <div className="mb-1 flex items-center justify-between">
                <label className="text-sm font-medium text-gray-700">Mot de passe</label>
                <Link to="/mot-de-passe-oublie" className="text-xs text-blue-700 hover:underline">
                  Mot de passe oublié ?
                </Link>
              </div>
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base outline-none sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="••••••••"
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className="flex items-center justify-center gap-2 rounded-lg bg-blue-700 py-3 font-semibold text-white hover:bg-blue-800 disabled:opacity-60 transition-colors"
            >
              {loading && <Spinner size="sm" />}
              Se connecter
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-gray-500">
            Pas encore de compte ?{' '}
            <Link to="/inscription" className="font-semibold text-blue-700 hover:underline">
              S'inscrire
            </Link>
          </p>
        </div>

        <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-4 text-xs text-blue-700">
          <p className="font-semibold mb-1">Comptes de démo :</p>
          <p>Admin : admin@ancf.fr / Admin1234!</p>
          <p>User : user@ancf.fr / User1234!</p>
        </div>
      </div>
    </div>
  )
}
