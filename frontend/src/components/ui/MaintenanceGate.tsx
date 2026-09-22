import { useLocation } from 'react-router-dom'
import { useAppConfig } from '../../hooks/useAppConfig'
import { useAuth } from '../../context/AuthContext'

/**
 * Remplace l'application par un écran d'attente quand le mode maintenance est actif.
 *
 * Deux exceptions, sans lesquelles la fonctionnalité s'enfermerait elle-même : la page de
 * connexion et l'espace d'administration restent accessibles, puisque c'est par là qu'un
 * administrateur ressort du mode maintenance. Les administrateurs connectés, eux, continuent de
 * naviguer normalement — c'est la seule façon de vérifier l'application avant de rouvrir.
 *
 * Le backend applique la même règle de son côté (MaintenanceListener) : cet écran est du confort
 * d'affichage, pas un contrôle d'accès.
 */
export function MaintenanceGate({ children }: { children: React.ReactNode }) {
  const { maintenanceMode } = useAppConfig()
  const { isAdmin } = useAuth()
  const { pathname } = useLocation()

  const routeTechnique = pathname.startsWith('/connexion') || pathname.startsWith('/admin')

  if (!maintenanceMode || isAdmin || routeTechnique) {
    return <>{children}</>
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 dark:bg-slate-900">
      <div className="max-w-md text-center">
        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
          <svg
            className="h-8 w-8 text-amber-600 dark:text-amber-400"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.8}
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"
            />
          </svg>
        </div>

        <h1 className="mb-3 text-2xl font-semibold text-slate-900 dark:text-slate-100">
          Maintenance en cours
        </h1>
        <p className="text-slate-600 dark:text-slate-400">
          Transport ANCF est momentanément indisponible, le temps d'une opération technique.
          Cette page se rouvrira d'elle-même dès le rétablissement du service.
        </p>
      </div>
    </div>
  )
}
