import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { AlertCard } from '../components/alerts/AlertCard'
import { Spinner } from '../components/ui/Spinner'

const CATEGORIES = [
  { value: '', label: 'Tout' },
  { value: 'INCIDENT', label: 'Incidents' },
  { value: 'TRAVAUX', label: 'Travaux programmés' },
]

const SEVERITIES = [
  { value: '', label: 'Toutes' },
  { value: 'MAJOR', label: 'Majeur' },
  { value: 'MODERATE', label: 'Modéré' },
  { value: 'INFO', label: 'Info' },
]

export function AlertsPage() {
  const [severity, setSeverity] = useState('')
  const [category, setCategory] = useState('')

  const { data: alerts = [], isLoading, error } = useQuery({
    queryKey: ['alerts', severity, category],
    queryFn: () => transportService.getAlerts({
      ...(severity ? { severity } : {}),
      ...(category ? { category } : {}),
    }),
    refetchInterval: 60_000,
    staleTime: 30_000,
  })

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
            onClick={() => setCategory(c.value)}
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
        {SEVERITIES.map((s) => (
          <button
            key={s.value}
            onClick={() => setSeverity(s.value)}
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
        <div className="space-y-3">
          {alerts.map((alert) => (
            <AlertCard key={alert.id} alert={alert} />
          ))}
        </div>
      )}
    </div>
  )
}
