import { useNavigate } from 'react-router-dom'
import { peutRevenirEnArriere } from '../../utils/navigation'

interface BackButtonProps {
  label?: string
  /** Destination quand il n'y a pas de page précédente dans l'application. */
  fallback?: string
  className?: string
}

/** Bouton retour vers la page précédente, ou vers `fallback` s'il n'y en a pas. */
export function BackButton({ label = 'Retour', fallback = '/', className = '' }: BackButtonProps) {
  const navigate = useNavigate()

  /** Revient en arrière dans l'application si possible, sinon va sur la page de repli. */
  const handleClick = () => {
    if (peutRevenirEnArriere()) navigate(-1)
    else navigate(fallback, { replace: true })
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      className={`-ml-2 -mt-2 mb-2 inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-blue-700 transition-colors ${className}`}
    >
      <span aria-hidden="true">←</span>
      {label}
    </button>
  )
}
