import { Link } from 'react-router-dom'

export function Footer() {
  return (
    <footer className="mt-auto border-t bg-white pb-20 md:pb-6">
      <div className="mx-auto flex w-full max-w-7xl flex-col items-center gap-2 px-4 py-6 text-xs text-gray-500 sm:flex-row sm:justify-between">
        <p>© {new Date().getFullYear()} Transport ANCF</p>
        <div className="flex gap-4">
          <Link to="/mentions-legales" className="hover:text-blue-700 hover:underline">
            Mentions légales
          </Link>
          <Link to="/politique-confidentialite" className="hover:text-blue-700 hover:underline">
            Politique de confidentialité
          </Link>
        </div>
      </div>
    </footer>
  )
}
