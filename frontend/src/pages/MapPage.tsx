import { useState, lazy, Suspense } from 'react'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { useGeolocation } from '../hooks/useGeolocation'
import { useSchedules } from '../hooks/useSchedules'
import { ScheduleRow } from '../components/schedules/ScheduleRow'
import { Spinner } from '../components/ui/Spinner'
import type { Stop } from '../types/transport'

const TransportMap = lazy(() =>
  import('../components/map/TransportMap').then((m) => ({ default: m.TransportMap }))
)

export function MapPage() {
  const { lat, lon } = useGeolocation()
  const [selectedStop, setSelectedStop] = useState<Stop | null>(null)

  // Le GPS renvoie une position légèrement différente à chaque mise à jour (watchPosition) :
  // arrondir à ~111m près évite de relancer une requête réseau à chaque micro-mouvement.
  const roundedLat = lat !== null ? Math.round(lat * 1000) / 1000 : null
  const roundedLon = lon !== null ? Math.round(lon * 1000) / 1000 : null

  const { data: stops = [], isLoading } = useQuery({
    queryKey: ['nearby-map', roundedLat, roundedLon],
    queryFn: () => transportService.getNearbyStops({ lat: lat!, lon: lon!, radius: 1000 }),
    enabled: !!(lat && lon),
    staleTime: 120_000,
  })

  const { data: schedules } = useSchedules(selectedStop?.id ?? null)

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Carte des arrêts</h1>

      {!lat && !lon && (
        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
          Activez la géolocalisation pour voir les arrêts autour de vous.
        </div>
      )}

      <div style={{ height: '55vh' }} className="rounded-xl overflow-hidden shadow">
        <Suspense fallback={<div className="flex h-full items-center justify-center"><Spinner size="lg" /></div>}>
          {isLoading ? (
            <div className="flex h-full items-center justify-center bg-gray-100">
              <Spinner size="lg" />
            </div>
          ) : (
            <TransportMap
              stops={stops}
              userLat={lat}
              userLon={lon}
              onStopClick={setSelectedStop}
            />
          )}
        </Suspense>
      </div>

      {selectedStop && (
        <div className="rounded-xl bg-white p-4 shadow-sm">
          <h2 className="mb-3 font-semibold text-gray-800">{selectedStop.name}</h2>
          {schedules?.departures && schedules.departures.length > 0 ? (
            <div className="space-y-2">
              {schedules.departures.slice(0, 5).map((dep, i) => (
                <ScheduleRow key={`${dep.lineCode}-${dep.direction}-${dep.waitMinutes}-${i}`} departure={dep} />
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-400">Chargement des départs...</p>
          )}
        </div>
      )}
    </div>
  )
}
