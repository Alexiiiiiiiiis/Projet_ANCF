import { NavLink } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'

const NavItem = ({ to, icon, label }: { to: string; icon: string; label: string }) => (
  <NavLink
    to={to}
    className={({ isActive }) =>
      `flex flex-col items-center gap-0.5 px-3 py-2 text-xs font-medium transition-colors ${
        isActive ? 'text-blue-700' : 'text-gray-500 hover:text-blue-600'
      }`
    }
  >
    <span className="text-xl">{icon}</span>
    <span>{label}</span>
  </NavLink>
)

export function BottomNav() {
  const { user } = useAuth()

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 flex justify-around border-t bg-white pb-safe md:hidden">
      <NavItem to="/" icon="🏠" label="Accueil" />
      <NavItem to="/carte" icon="🗺️" label="Carte" />
      {user && <NavItem to="/itineraire" icon="🧭" label="Itinéraire" />}
      <NavItem to="/alertes" icon="⚠️" label="Alertes" />
      {user ? (
        <NavItem to="/favoris" icon="⭐" label="Favoris" />
      ) : (
        <NavItem to="/connexion" icon="👤" label="Connexion" />
      )}
    </nav>
  )
}
