import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useTheme } from '../../hooks/useTheme'

export function Navbar() {
  const { user, isAdmin, logout } = useAuth()
  const { theme, toggleTheme } = useTheme()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/connexion')
  }

  return (
    <header className="sticky top-0 z-50 bg-white shadow-sm">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <Link to="/" className="flex items-center gap-2 font-bold text-blue-700 text-lg">
          <span className="text-2xl">🚇</span>
          <span>Transport ANCF</span>
        </Link>

        <nav className="hidden items-center gap-6 text-sm font-medium text-gray-600 md:flex">
          <Link to="/" className="hover:text-blue-700 transition-colors">Accueil</Link>
          <Link to="/horaires" className="hover:text-blue-700 transition-colors">Horaires</Link>
          <Link to="/carte" className="hover:text-blue-700 transition-colors">Carte</Link>
          {user && (
            <Link to="/itineraire" className="hover:text-blue-700 transition-colors">Itinéraire</Link>
          )}
          <Link to="/alertes" className="hover:text-blue-700 transition-colors">Alertes</Link>
          {user && (
            <Link to="/favoris" className="hover:text-blue-700 transition-colors">Favoris</Link>
          )}
          {isAdmin && (
            <Link to="/admin" className="hover:text-blue-700 transition-colors text-orange-600">Admin</Link>
          )}
        </nav>

        <div className="flex items-center gap-3">
          {isAdmin && (
            <Link
              to="/admin"
              className="rounded-lg bg-orange-100 px-2.5 py-1.5 text-sm text-orange-700 hover:bg-orange-200 transition-colors md:hidden"
              aria-label="Dashboard admin"
              title="Dashboard admin"
            >
              🛡️
            </Link>
          )}
          <button
            onClick={toggleTheme}
            className="rounded-lg bg-gray-100 px-2.5 py-1.5 text-sm hover:bg-gray-200 transition-colors"
            aria-label={theme === 'dark' ? 'Passer au thème clair' : 'Passer au thème sombre'}
            title={theme === 'dark' ? 'Thème clair' : 'Thème sombre'}
          >
            {theme === 'dark' ? '☀️' : '🌙'}
          </button>
          {user ? (
            <>
              <Link
                to="/profil"
                className="hidden text-sm font-medium text-gray-700 hover:text-blue-700 md:block"
              >
                {user.firstName}
              </Link>
              <button
                onClick={handleLogout}
                className="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 transition-colors"
              >
                Déconnexion
              </button>
            </>
          ) : (
            <>
              <Link
                to="/connexion"
                className="text-sm font-medium text-gray-700 hover:text-blue-700"
              >
                Connexion
              </Link>
              <Link
                to="/inscription"
                className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 transition-colors"
              >
                S'inscrire
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  )
}
