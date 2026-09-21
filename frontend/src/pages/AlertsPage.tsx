import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { AlertCard } from '../components/alerts/AlertCard'
import { Spinner } from '../components/ui/Spinner'
import { TRANSPORT_LABELS, type TransportType } from '../types/transport'

const CATEGORIES = [
  { value: '', label: 'Tout' },
  { value: 'INCIDENT', label: 'Incidents' },
  { value: 'TRAVAUX', label: 'Travaux programmés' },
]

const SEVERITIES = [
  { value: '', label: 'Toutes' },
  { value: 'MAJOR', label: 'Majeur' },
  { value: 'MODERATE', label: 'Modéré' },
  { value: 'INFO', label: 'Mineur' },
]

const TYPES: { value: '' | TransportType; label: string }[] = [
  { value: '', label: 'Tous les modes' },
  { value: 'METRO', label: TRANSPORT_LABELS.METRO },
  { value: 'RER', label: TRANSPORT_LABELS.RER },
  { value: 'TRAM', label: TRANSPORT_LABELS.TRAM },
  { value: 'BUS', label: TRANSPORT_LABELS.BUS },
]

const PAGE_SIZE = 20

/** Page des perturbations, avec filtres (catégorie, gravité, mode) et pagination. */
export function AlertsPage() {
  const [severity, setSeverity] = useState('')
  const [category, setCategory] = useState('')
  const [type, setType] = useState<'' | TransportType>('')
  const [page, setPage] = useState(1)

  const { data, isLoading, error } = useQuery({
    queryKey: ['alerts', severity, category, type, page],
    queryFn: () => transportService.getAlerts({
      ...(severity ? { severity } : {}),
      ...(category ? { category } : {}),
      ...(type ? { type } : {}),
      page,
      limit: PAGE_SIZE,
    }),
    refetchInterval: 60_000,
    staleTime: 30_000,
  })

  const alerts = data?.alerts ?? []
  const totalPages = data?.totalPages ?? 1

  /** Change le filtre de catégorie et revient à la page 1. */
  const handleCategoryChange = (value: string) => {
    setCategory(value)
    setPage(1)
  }

  /** Change le filtre de gravité et revient à la page 1. */
  const handleSeverityChange = (value: string) => {
    setSeverity(value)
    setPage(1)
  }

  /** Change le filtre de mode de transport et revient à la page 1. */
  const handleTypeChange = (value: '' | TransportType) => {
    setType(value)
    setPage(1)
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Alertes trafic</h1>
        <p className="text-sm text-gray-500 mt-1">Perturbations en cours sur le réseau Île-de-France</p>
      </div>

      <div className="flex gap-2 overflow-x-auto pb-1">
        {CATEGORIES.map((c) => (
          <button
            key={c.value}
            onClick={() => handleCategoryChange(c.value)}
            className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
              category === c.value
                ? 'bg-purple-700 text-white'
                : 'bg-white text-gray-600 border border-gray-200 hover:border-purple-300'
            }`}
          >
            {c.label}
          </button>
        ))}
      </div>

      <div className="flex gap-2 overflow-x-auto pb-1">
        {TYPES.map((t) => (
          <button
            key={t.value}
            onClick={() => handleTypeChange(t.value)}
            className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
              type === t.value
                ? 'bg-emerald-700 text-white'
                : 'bg-white text-gray-600 border border-gray-200 hover:border-emerald-300'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="flex gap-2 overflow-x-auto pb-1">
        {SEVERITIES.map((s) => (
          <button
            key={s.value}
            onClick={() => handleSeverityChange(s.value)}
            className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
              severity === s.value
                ? 'bg-blue-700 text-white'
                : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'
            }`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : error ? (
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          Impossible de charger les alertes.
        </div>
      ) : alerts.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 py-12 text-gray-400">
          <span className="text-4xl">✅</span>
          <p className="text-sm">Aucune perturbation en cours</p>
        </div>
      ) : (
        <>
          <div className="space-y-3">
            {alerts.map((alert) => (
              <AlertCard key={alert.id} alert={alert} />
            ))}
          </div>

          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-4 pt-2">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                className="rounded-full px-4 py-1.5 text-sm font-medium bg-white text-gray-600 border border-gray-200 disabled:opacity-40 disabled:cursor-not-allowed hover:border-purple-300"
              >
                Précédent
              </button>
              <span className="text-sm text-gray-500">
                Page {page} / {totalPages}
              </span>
              <button
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page >= totalPages}
                className="rounded-full px-4 py-1.5 text-sm font-medium bg-white text-gray-600 border border-gray-200 disabled:opacity-40 disabled:cursor-not-allowed hover:border-purple-300"
              >
                Suivant
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
