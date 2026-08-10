import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { SearchBar } from '../components/stops/SearchBar'
import { Spinner } from '../components/ui/Spinner'
import { transportService } from '../services/transportService'
import type { Stop } from '../types/transport'
import { TRANSPORT_COLORS, TRANSPORT_LABELS } from '../types/transport'

function formatTime(iso: string | null): string {
  if (!iso) return '--:--'
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

export function JourneyPage() {
  const [from, setFrom] = useState<Stop | null>(null)
  const [to, setTo] = useState<Stop | null>(null)
  const [searchKey, setSearchKey] = useState(0)

  const { data, isFetching, error } = useQuery({
    queryKey: ['journeys', from?.id, to?.id, searchKey],
    queryFn: () => transportService.searchJourneys(from!.id, to!.id, from!.name, to!.name),
    enabled: !!from && !!to,
    staleTime: 60_000,
  })

  const errorMessage = (error as { response?: { data?: { error?: string } } } | null)?.response?.data?.error
    ?? (error ? 'Impossible de calculer un itinéraire.' : null)

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Itinéraire</h1>
        <p className="text-sm text-gray-500 mt-1">Où allons-nous ?</p>
      </div>

      <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm space-y-3">
        <div>
          <label className="mb-1 block text-xs font-medium text-gray-500">Départ</label>
          <SearchBar placeholder="D'où partez-vous ?" onSelect={setFrom} />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-gray-500">Arrivée</label>
          <SearchBar placeholder="Où allez-vous ?" onSelect={setTo} />
        </div>

        {from && to && from.id === to.id && (
          <p className="text-sm text-red-600">Le départ et l'arrivée doivent être différents.</p>
        )}

        {from && to && from.id !== to.id && (
          <button
            onClick={() => setSearchKey((k) => k + 1)}
            className="w-full rounded-xl bg-blue-700 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 transition-colors"
          >
            Rechercher
          </button>
        )}
      </div>

      {isFetching ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : errorMessage ? (
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{errorMessage}</div>
      ) : data && data.journeys.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 py-12 text-gray-400">
          <span className="text-4xl">🤷</span>
          <p className="text-sm">Aucun itinéraire trouvé</p>
        </div>
      ) : data ? (
        <div className="space-y-4">
          {data.journeys.map((journey, i) => (
            <div key={i} className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
              <div className="mb-3 flex items-center justify-between">
                <div>
                  <span className="text-lg font-bold text-gray-900">{journey.durationMinutes} min</span>
                  <span className="ml-2 text-sm text-gray-500">
                    {formatTime(journey.departureTime)} → {formatTime(journey.arrivalTime)}
                  </span>
                </div>
                <span className="text-xs text-gray-400">
                  {journey.transfers} correspondance{journey.transfers !== 1 ? 's' : ''}
                </span>
              </div>

              <div className="space-y-0.5">
                {/* Arrêt de départ */}
                <div className="flex items-center gap-2 py-1">
                  <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-800" />
                  <span className="text-sm font-medium text-gray-800">
                    {journey.sections[0]?.from ?? 'Départ'}
                  </span>
                  <span className="ml-auto text-xs text-gray-400">
                    {formatTime(journey.sections[0]?.departureTime ?? journey.departureTime)}
                  </span>
                </div>

                {journey.sections.map((section, j) => (
                  <div key={j}>
                    {/* Trajet vers l'arrêt suivant */}
                    <div className="flex items-center gap-2 py-1 pl-1">
                      <span className="w-2.5 shrink-0 text-center text-gray-300">│</span>
                      {section.mode === 'WALK' ? (
                        <span className="flex items-center gap-1 text-xs text-gray-500">
                          🚶 à pied · {section.durationMinutes} min
                        </span>
                      ) : (
                        <>
                          <span
                            className="flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold text-white"
                            style={{ backgroundColor: TRANSPORT_COLORS[section.mode] }}
                          >
                            {section.lineCode ?? TRANSPORT_LABELS[section.mode]}
                          </span>
                          <span className="text-xs text-gray-500">
                            {section.direction ? `dir. ${section.direction} · ` : ''}
                            {section.durationMinutes} min
                          </span>
                        </>
                      )}
                    </div>

                    {/* Arrêt atteint */}
                    <div className="flex items-center gap-2 py-1">
                      <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-800" />
                      <span className="text-sm font-medium text-gray-800">
                        {section.to ?? (j === journey.sections.length - 1 ? 'Arrivée' : '—')}
                      </span>
                      <span className="ml-auto text-xs text-gray-400">{formatTime(section.arrivalTime)}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  )
}
