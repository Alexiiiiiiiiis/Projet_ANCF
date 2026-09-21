import { useState, useMemo, useEffect, lazy, Suspense } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { stopFromParams, stopScheduleUrl, stopToParams } from '../utils/stopParams'
import { useGeolocation } from '../hooks/useGeolocation'
import { useSchedules } from '../hooks/useSchedules'
import { SearchBar } from '../components/stops/SearchBar'
import { ScheduleRow } from '../components/schedules/ScheduleRow'
import { Spinner } from '../components/ui/Spinner'
import type { MapFocus } from '../components/map/TransportMap'
import type { Stop } from '../types/transport'

const TransportMap = lazy(() =>
  import('../components/map/TransportMap').then((m) => ({ default: m.TransportMap }))
)

/** Page carte : arrêts autour de l'utilisateur ou de l'arrêt recherché, avec leurs départs. */
export function MapPage() {
  const { lat, lon } = useGeolocation()
  const [searchParams, setSearchParams] = useSearchParams()
  const [selectedStop, setSelectedStop] = useState<Stop | null>(null)
  const [searchedStop, setSearchedStop] = useState<Stop | null>(null)
  // Rechoisir le même arrêt doit recentrer la carte : un compteur distingue chaque sélection
  const [focusSeq, setFocusSeq] = useState(0)

  // /carte?arret=…&lat=…&lon=… : la page Horaires (et un lien partagé) ouvrent la carte
  // directement sur un arrêt, sans passer par la recherche ni par le GPS.
  const stopFromUrl = stopFromParams(searchParams)
  const stopIdFromUrl = stopFromUrl?.lat && stopFromUrl?.lon ? stopFromUrl.id : null

  useEffect(() => {
    if (!stopFromUrl || !stopIdFromUrl || stopIdFromUrl === searchedStop?.id) return
    setSearchedStop(stopFromUrl)
    setSelectedStop(stopFromUrl)
    setFocusSeq((n) => n + 1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [stopIdFromUrl])

  // La carte est centrée sur l'arrêt recherché quand il y en a un, sinon sur la position GPS :
  // on peut ainsi explorer une station à l'autre bout du réseau sans y être.
  const centerLat = searchedStop ? searchedStop.lat : lat
  const centerLon = searchedStop ? searchedStop.lon : lon

  // Le GPS renvoie une position légèrement différente à chaque mise à jour (watchPosition) :
  // arrondir à ~111m près évite de relancer une requête réseau à chaque micro-mouvement.
  const roundedLat = centerLat !== null ? Math.round(centerLat * 1000) / 1000 : null
  const roundedLon = centerLon !== null ? Math.round(centerLon * 1000) / 1000 : null

  const { data: stops = [], isLoading } = useQuery({
    queryKey: ['nearby-map', roundedLat, roundedLon],
    queryFn: () => transportService.getNearbyStops({ lat: centerLat!, lon: centerLon!, radius: 1000 }),
    enabled: centerLat !== null && centerLon !== null,
    staleTime: 120_000,
  })

  const { data: schedules } = useSchedules(selectedStop?.id ?? null)

  // L'arrêt cherché n'est pas forcément dans les arrêts alentour renvoyés par l'API
  // (limite de résultats, arrêt d'un mode absent du voisinage) : on l'ajoute nous-mêmes.
  const markers = useMemo(() => {
    if (!searchedStop) return stops
    return stops.some((s) => s.id === searchedStop.id) ? stops : [searchedStop, ...stops]
  }, [stops, searchedStop])

  const focus: MapFocus | null = searchedStop
    ? { lat: searchedStop.lat, lon: searchedStop.lon, zoom: 16, key: `${searchedStop.id}#${focusSeq}` }
    : lat !== null && lon !== null
      ? { lat, lon, key: `${roundedLat},${roundedLon}` }
      : null

  /** Centre la carte sur l'arrêt choisi et met l'URL à jour. */
  const handleSearchSelect = (stop: Stop) => {
    setSearchedStop(stop)
    setSelectedStop(stop)
    setFocusSeq((n) => n + 1)
    // L'URL suit l'arrêt affiché : la carte se partage et le lien vers les horaires est prêt.
    setSearchParams(stopToParams(stop), { replace: true })
  }

  /** Recentre la carte sur la position GPS de l'utilisateur. */
  const backToPosition = () => {
    setSearchedStop(null)
    setSelectedStop(null)
    setFocusSeq((n) => n + 1)
    setSearchParams({}, { replace: true })
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Carte des arrêts</h1>

      <SearchBar onSelect={handleSearchSelect} placeholder="Rechercher une station ou un arrêt..." />

      {searchedStop ? (
        <div className="flex items-center justify-between gap-3 rounded-lg bg-blue-50 px-4 py-2 text-sm text-blue-800">
          <span>
            Carte centrée sur <strong>{searchedStop.name}</strong>
          </span>
          {lat !== null && lon !== null && (
            <button onClick={backToPosition} className="shrink-0 font-medium underline hover:no-underline">
              Revenir à ma position
            </button>
          )}
        </div>
      ) : !lat && !lon ? (
        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
          Activez la géolocalisation pour voir les arrêts autour de vous, ou recherchez une station ci-dessus.
        </div>
      ) : null}

      <div style={{ height: '55vh' }} className="rounded-xl overflow-hidden shadow">
        <Suspense fallback={<div className="flex h-full items-center justify-center"><Spinner size="lg" /></div>}>
          {isLoading ? (
            <div className="flex h-full items-center justify-center bg-gray-100">
              <Spinner size="lg" />
            </div>
          ) : (
            <TransportMap
              stops={markers}
              userLat={lat}
              userLon={lon}
              focus={focus}
              highlightStopId={searchedStop?.id}
              onStopClick={setSelectedStop}
            />
          )}
        </Suspense>
      </div>

      {selectedStop && (
        <div className="rounded-xl bg-white p-4 shadow-sm">
          <div className="mb-3 flex items-center justify-between gap-3">
            <h2 className="font-semibold text-gray-800">{selectedStop.name}</h2>
            <Link
              to={stopScheduleUrl(selectedStop)}
              className="shrink-0 text-sm font-medium text-blue-700 hover:underline"
            >
              Tous les horaires
            </Link>
          </div>
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
