import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { useSchedules } from '../hooks/useSchedules'
import { ScheduleRow } from '../components/schedules/ScheduleRow'
import { Spinner } from '../components/ui/Spinner'
import { LineBadge } from '../components/lines/LineBadge'
import { TrafficPill } from '../components/lines/TrafficPill'
import { useLineStatuses } from '../hooks/useLineStatuses'
import { stopScheduleUrl } from '../utils/stopParams'
import { TRANSPORT_COLORS, TRANSPORT_ICONS, TRANSPORT_LABELS } from '../types/transport'
import { useState } from 'react'

/** Page des favoris : arrêts avec leurs prochains départs, lignes avec leur état de trafic. */
export function FavoritesPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [expandedId, setExpandedId] = useState<string | null>(null)
  const [removeError, setRemoveError] = useState<string | null>(null)

  const { data: favorites = [], isLoading } = useQuery({
    queryKey: ['favorites'],
    queryFn: transportService.getFavorites,
  })

  const removeMutation = useMutation({
    mutationFn: transportService.removeFavorite,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] })
      setRemoveError(null)
    },
    onError: () => setRemoveError('Impossible de retirer ce favori. Réessayez.'),
  })

  const { data: schedules } = useSchedules(expandedId)

  const statuts = useLineStatuses(
    favorites.filter((f) => 'LINE' === f.kind).map((f) => f.stopId)
  )

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Mes favoris</h1>
        <p className="text-sm text-gray-500 mt-1">Vos arrêts et vos lignes enregistrés</p>
      </div>

      {removeError && (
        <div className="flex items-center justify-between rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{removeError}</span>
          <button onClick={() => setRemoveError(null)} className="ml-3 text-red-400 hover:text-red-600" aria-label="Fermer">✕</button>
        </div>
      )}

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : favorites.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 py-12 text-gray-400">
          <span className="text-4xl">⭐</span>
          <p className="text-sm">Touchez l'étoile sur une ligne ou un arrêt pour l'ajouter ici</p>
        </div>
      ) : (
        <div className="space-y-2">
          {favorites.map((fav) => {
            // Une ligne n'a pas de « prochains passages » : elle ouvre sa fiche, la ou un
            // arrêt se deplie sur ses departs.
            const estLigne = 'LINE' === fav.kind

            return (
            <div key={fav.id} className="rounded-xl bg-white shadow-sm overflow-hidden">
              <div
                className="flex cursor-pointer items-center gap-4 p-4"
                onClick={() =>
                  estLigne
                    ? navigate(`/horaires/ligne/${encodeURIComponent(fav.stopId)}`)
                    : setExpandedId(expandedId === fav.stopId ? null : fav.stopId)
                }
              >
                {estLigne ? (
                  <LineBadge code={fav.lineCode} transportType={fav.transportType} size="lg" />
                ) : (
                  <div
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg"
                    style={{ backgroundColor: TRANSPORT_COLORS[fav.transportType] }}
                    title={TRANSPORT_LABELS[fav.transportType]}
                  >
                    {TRANSPORT_ICONS[fav.transportType]}
                  </div>
                )}
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-gray-900 truncate">{fav.stopName}</p>
                  {estLigne ? (
                    <TrafficPill status={statuts.get(fav.stopId)} />
                  ) : (
                    <p className="text-xs text-gray-500">{fav.lineCode}</p>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-gray-400">
                    {estLigne ? '›' : expandedId === fav.stopId ? '▲' : '▼'}
                  </span>
                  <button
                    onClick={(e) => { e.stopPropagation(); removeMutation.mutate(fav.id) }}
                    className="text-red-400 hover:text-red-600 text-lg transition-colors"
                    aria-label="Supprimer"
                  >
                    ×
                  </button>
                </div>
              </div>

              {expandedId === fav.stopId && (
                <div className="border-t px-4 pb-4 pt-2 space-y-2">
                  {schedules?.departures && schedules.departures.length > 0 ? (
                    <>
                      {schedules.departures.slice(0, 4).map((dep, i) => (
                        <ScheduleRow key={`${dep.lineCode}-${dep.direction}-${dep.waitMinutes}-${i}`} departure={dep} />
                      ))}
                      <button
                        onClick={() => navigate(stopScheduleUrl({
                          id: fav.stopId,
                          name: fav.stopName,
                          lat: 0,
                          lon: 0,
                          transportType: fav.transportType,
                          lines: fav.lineCode ? [fav.lineCode] : [],
                        }))}
                        className="w-full pt-1 text-sm font-medium text-blue-700 hover:underline"
                      >
                        Tous les horaires
                      </button>
                    </>
                  ) : (
                    <div className="flex justify-center py-3">
                      <Spinner size="sm" />
                    </div>
                  )}
                </div>
              )}
            </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
