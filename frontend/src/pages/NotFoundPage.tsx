import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center text-center">
      <p className="text-6xl font-bold text-blue-700">404</p>
      <h1 className="mt-4 text-2xl font-bold text-gray-900">Page introuvable</h1>
      <p className="mt-2 text-gray-500">
        La page que vous recherchez n'existe pas ou a ete deplacee.
      </p>
      <Link
        to="/"
        className="mt-6 rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800 transition-colors"
      >
        Retour a l'accueil
      </Link>
    </div>
  )
}
