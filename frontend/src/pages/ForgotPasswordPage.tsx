import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { authService } from '../services/authService'
import { Spinner } from '../components/ui/Spinner'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [debugToken, setDebugToken] = useState<string | null>(null)
  const [error, setError] = useState('')

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const res = await authService.forgotPassword(email)
      setSuccess(true)
      if (res.debug_token) setDebugToken(res.debug_token)
    } catch {
      setError('Une erreur est survenue. Veuillez réessayer.')
    } finally {
      setLoading(false)
    }
  }

  if (success) {
    return (
      <div className="mx-auto max-w-md">
        <div className="rounded-2xl bg-white p-8 shadow-sm text-center">
          <div className="mb-4 text-4xl">✅</div>
          <h1 className="mb-2 text-xl font-bold text-gray-900">Email envoyé</h1>
          <p className="text-sm text-gray-500 mb-6">
            Si cet email existe dans notre système, vous recevrez un lien de réinitialisation.
          </p>
          {debugToken && (
            <div className="mb-4 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-left text-xs text-yellow-800">
              <p className="font-semibold mb-1">Mode développement — token :</p>
              <Link
                to={`/reinitialiser-mot-de-passe?token=${debugToken}`}
                className="break-all font-mono text-blue-700 underline"
              >
                {debugToken}
              </Link>
            </div>
          )}
          <Link to="/connexion" className="text-sm font-semibold text-blue-700 hover:underline">
            Retour à la connexion
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-md">
      <div className="rounded-2xl bg-white p-8 shadow-sm">
        <h1 className="mb-1 text-2xl font-bold text-gray-900">Mot de passe oublié</h1>
        <p className="mb-6 text-sm text-gray-500">
          Saisissez votre email pour recevoir un lien de réinitialisation.
        </p>

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
              className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              placeholder="vous@exemple.fr"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="flex items-center justify-center gap-2 rounded-lg bg-blue-700 py-3 font-semibold text-white hover:bg-blue-800 disabled:opacity-60 transition-colors"
          >
            {loading && <Spinner size="sm" />}
            Envoyer le lien
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-gray-500">
          <Link to="/connexion" className="font-semibold text-blue-700 hover:underline">
            Retour à la connexion
          </Link>
        </p>
      </div>
    </div>
  )
}
