import { useState } from 'react'
import { SearchBar } from '../components/stops/SearchBar'
import { StopCard } from '../components/stops/StopCard'
import { ScheduleRow } from '../components/schedules/ScheduleRow'
import { Spinner } from '../components/ui/Spinner'
import { useSchedules } from '../hooks/useSchedules'
import { useGeolocation } from '../hooks/useGeolocation'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { useAuth } from '../context/AuthContext'
import type { FavoriteStop, Stop, TransportType } from '../types/transport'
import { TRANSPORT_COLORS, TRANSPORT_LABELS } from '../types/transport'

// Grands pôles d'échange franciliens (IDs réels IDFM) affichés quand
// l'utilisateur n'a ni recherché ni activé la géolocalisation
const POPULAR_STOPS: Stop[] = [
  { id: 'stop_area:IDFM:71264', name: 'Châtelet', lat: 48.8583, lon: 2.3485, transportType: 'METRO', lines: ['M1', 'M4', 'M7', 'M11', 'M14'] },
  { id: 'stop_area:IDFM:474151', name: 'Châtelet - Les Halles', lat: 48.8617, lon: 2.347, transportType: 'RER', lines: ['RER A', 'RER B', 'RER D'] },
  { id: 'stop_area:IDFM:71410', name: 'Gare du Nord', lat: 48.8809, lon: 2.3553, transportType: 'METRO', lines: ['M4', 'M5'] },
  { id: 'stop_area:IDFM:73626', name: 'Gare de Lyon', lat: 48.8445, lon: 2.3735, transportType: 'METRO', lines: ['M1', 'M14'] },
  { id: 'stop_area:IDFM:71517', name: 'La Défense', lat: 48.8921, lon: 2.2391, transportType: 'METRO', lines: ['M1'] },
  { id: 'stop_area:IDFM:71370', name: 'Gare Saint-Lazare', lat: 48.875, lon: 2.325, transportType: 'METRO', lines: ['M3', 'M12', 'M13', 'M14'] },
  { id: 'stop_area:IDFM:71045', name: 'Porte de Versailles', lat: 48.8324, lon: 2.2879, transportType: 'TRAM', lines: ['T2', 'T3a', 'M12'] },
  { id: 'stop_area:IDFM:71673', name: 'Nation', lat: 48.8488, lon: 2.3963, transportType: 'METRO', lines: ['M1', 'M2', 'M6', 'M9'] },

  // RER — une entrée par ligne (A/B/D déjà couvertes par Châtelet - Les Halles ci-dessus)
  { id: 'stop_area:IDFM:rer_defense', name: 'La Défense', lat: 48.8921, lon: 2.2391, transportType: 'RER', lines: ['RER A'] },
  { id: 'stop_area:IDFM:rer_denfert', name: 'Denfert-Rochereau', lat: 48.8339, lon: 2.3327, transportType: 'RER', lines: ['RER B'] },
  { id: 'stop_area:IDFM:rer_invalides', name: 'Invalides', lat: 48.8615, lon: 2.314, transportType: 'RER', lines: ['RER C'] },
  { id: 'stop_area:IDFM:rer_lyon_d', name: 'Gare de Lyon', lat: 48.8443, lon: 2.373, transportType: 'RER', lines: ['RER D'] },
  { id: 'stop_area:IDFM:rer_magenta', name: 'Magenta', lat: 48.8768, lon: 2.3565, transportType: 'RER', lines: ['RER E'] },

  // Tram — quelques lignes supplémentaires (T2/T3a déjà couvertes par Porte de Versailles ci-dessus)
  { id: 'stop_area:IDFM:tram_saint_denis', name: 'Marché de Saint-Denis', lat: 48.9356, lon: 2.3573, transportType: 'TRAM', lines: ['T1'] },
  { id: 'stop_area:IDFM:tram_bondy', name: 'Bondy', lat: 48.9019, lon: 2.4795, transportType: 'TRAM', lines: ['T4'] },
  { id: 'stop_area:IDFM:tram_athis_mons', name: 'Athis-Mons', lat: 48.7113, lon: 2.3893, transportType: 'TRAM', lines: ['T7'] },
]

const TYPE_FILTERS: TransportType[] = ['METRO', 'RER', 'TRAM', 'BUS']

export function Home() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const { lat, lon, error: geoError } = useGeolocation()
  const [selectedStop, setSelectedStop] = useState<Stop | null>(null)
  const [typeFilter, setTypeFilter] = useState<TransportType | null>(null)
  const [presetQuery, setPresetQuery] = useState('')
  const [favoriteError, setFavoriteError] = useState<string | null>(null)

  const { data: schedules, isLoading: schedulesLoading, dataUpdatedAt } = useSchedules(selectedStop?.id ?? null)

  // Le GPS renvoie une position légèrement différente à chaque mise à jour (watchPosition) :
  // arrondir à ~111m près évite de relancer une requête réseau à chaque micro-mouvement.
  const roundedLat = lat !== null ? Math.round(lat * 1000) / 1000 : null
  const roundedLon = lon !== null ? Math.round(lon * 1000) / 1000 : null

  const { data: nearbyStops = [] } = useQuery({
    queryKey: ['nearby', roundedLat, roundedLon],
    queryFn: () => transportService.getNearbyStops({ lat: lat!, lon: lon!, radius: 500 }),
    enabled: !!(lat && lon),
    staleTime: 120_000,
  })

  const { data: favorites = [] } = useQuery({
    queryKey: ['favorites'],
    queryFn: transportService.getFavorites,
    enabled: !!user,
  })

  // F5.4 — dernières recherches de l'utilisateur
  const { data: recentSearches = [] } = useQuery({
    queryKey: ['search-history'],
    queryFn: transportService.getSearchHistory,
    enabled: !!user,
    staleTime: 60_000,
  })

  const addFavMutation = useMutation({
    mutationFn: (stop: Stop) =>
      transportService.addFavorite({
        stopId: stop.id,
        stopName: stop.name,
        lineCode: stop.lines?.[0] ?? '',
        transportType: stop.transportType,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] })
      setFavoriteError(null)
    },
    onError: () => setFavoriteError('Impossible d\'ajouter ce favori. Réessayez.'),
  })

  const removeFavMutation = useMutation({
    mutationFn: (id: number) => transportService.removeFavorite(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] })
      setFavoriteError(null)
    },
    onError: () => setFavoriteError('Impossible de retirer ce favori. Réessayez.'),
  })

  const isFav = (stopId: string) => favorites.some((f) => f.stopId === stopId)
  const getFavId = (stopId: string) => favorites.find((f) => f.stopId === stopId)?.id

  const toggleFavorite = (stop: Stop) => {
    if (!user) return
    const favId = getFavId(stop.id)
    if (favId) {
      removeFavMutation.mutate(favId)
    } else {
      addFavMutation.mutate(stop)
    }
  }

  const byType = (stops: Stop[]) =>
    typeFilter ? stops.filter((s) => s.transportType === typeFilter) : stops

  // F5.3 — les favoris deviennent des arrêts cliquables sur l'accueil
  const favoriteAsStop = (fav: FavoriteStop): Stop => ({
    id: fav.stopId,
    name: fav.stopName,
    lat: 0,
    lon: 0,
    transportType: fav.transportType,
    lines: fav.lineCode ? [fav.lineCode] : [],
  })

  const filteredNearby = byType(nearbyStops)
  const filteredPopular = byType(POPULAR_STOPS)
  const filteredFavorites = byType(favorites.map(favoriteAsStop))

  const renderStopCard = (stop: Stop) => (
    <StopCard
      key={stop.id}
      stop={stop}
      onClick={() => setSelectedStop(stop)}
      isFavorite={isFav(stop.id)}
      onToggleFavorite={user ? () => toggleFavorite(stop) : undefined}
      distance={stop.distanceLabel}
    />
  )

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">
          Bonjour{user ? `, ${user.firstName}` : ''} 👋
        </h1>
        <p className="text-sm text-gray-500 mt-1">Consultez les prochains passages en temps réel</p>
      </div>

      {favoriteError && (
        <div className="flex items-center justify-between rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{favoriteError}</span>
          <button onClick={() => setFavoriteError(null)} className="ml-3 text-red-400 hover:text-red-600" aria-label="Fermer">✕</button>
        </div>
      )}

      <SearchBar onSelect={setSelectedStop} type={typeFilter ?? undefined} presetQuery={presetQuery} />

      {/* F5.4 — Recherches récentes */}
      {!selectedStop && recentSearches.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="text-gray-400">🕘 Récentes :</span>
          {recentSearches.map((q) => (
            <button
              key={q}
              onClick={() => setPresetQuery(q)}
              className="rounded-full bg-gray-100 px-3 py-1 text-gray-600 hover:bg-gray-200 transition-colors"
            >
              {q}
            </button>
          ))}
        </div>
      )}

      {/* Filtres par type de transport */}
      <div className="flex flex-wrap items-center gap-2">
        <button
          onClick={() => setTypeFilter(null)}
          className={`rounded-full border px-4 py-1.5 text-sm font-medium transition-colors ${
            typeFilter === null
              ? 'border-gray-800 bg-gray-800 text-white'
              : 'border-gray-200 bg-white text-gray-600 hover:border-gray-400'
          }`}
        >
          Tous
        </button>
        {TYPE_FILTERS.map((type) => {
          const active = typeFilter === type
          return (
            <button
              key={type}
              onClick={() => setTypeFilter(active ? null : type)}
              className={`flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors ${
                active ? 'text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-400'
              }`}
              style={active ? { backgroundColor: TRANSPORT_COLORS[type], borderColor: TRANSPORT_COLORS[type] } : undefined}
            >
              <span
                className="h-2.5 w-2.5 rounded-full"
                style={{ backgroundColor: active ? 'white' : TRANSPORT_COLORS[type] }}
              />
              {TRANSPORT_LABELS[type]}
            </button>
          )
        })}
      </div>

      {selectedStop && (
        <section>
          <div className="mb-3 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <button
                onClick={() => setSelectedStop(null)}
                className="text-gray-400 hover:text-gray-600"
                aria-label="Retour"
              >
                ←
              </button>
              <h2 className="font-semibold text-gray-800">{selectedStop.name}</h2>
            </div>
            {dataUpdatedAt > 0 && (
              <span className="text-xs text-gray-400">
                Mis à jour {new Date(dataUpdatedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
              </span>
            )}
          </div>
          {schedulesLoading ? (
            <div className="flex justify-center py-8">
              <Spinner />
            </div>
          ) : schedules?.departures && schedules.departures.length > 0 ? (
            <div className="space-y-2">
              {schedules.departures.slice(0, 8).map((dep, i) => (
                <ScheduleRow key={`${dep.lineCode}-${dep.direction}-${dep.waitMinutes}-${i}`} departure={dep} />
              ))}
            </div>
          ) : (
            <p className="py-4 text-center text-sm text-gray-400">Aucun départ disponible</p>
          )}
        </section>
      )}

      {/* F5.3 — Accès rapide aux favoris depuis l'accueil */}
      {!selectedStop && filteredFavorites.length > 0 && (
        <section>
          <h2 className="mb-3 font-semibold text-gray-800">⭐ Mes favoris</h2>
          <div className="grid gap-2 sm:grid-cols-2">{filteredFavorites.map(renderStopCard)}</div>
        </section>
      )}

      {!selectedStop && filteredNearby.length > 0 && (
        <section>
          <h2 className="mb-3 font-semibold text-gray-800">📍 Arrêts à proximité</h2>
          <div className="space-y-2">{filteredNearby.slice(0, 5).map(renderStopCard)}</div>
        </section>
      )}

      {!selectedStop && (
        <section>
          <h2 className="mb-3 font-semibold text-gray-800">🚉 Arrêts populaires</h2>
          {filteredPopular.length > 0 ? (
            <div className="grid gap-2 sm:grid-cols-2">{filteredPopular.map(renderStopCard)}</div>
          ) : (
            <p className="py-4 text-center text-sm text-gray-400">
              Aucun arrêt populaire de ce type — utilisez la recherche ci-dessus
            </p>
          )}
        </section>
      )}

      {!selectedStop && nearbyStops.length === 0 && (
        <p className="text-center text-xs text-gray-400">
          {geoError
            ? '📍 Géolocalisation désactivée — autorisez-la dans votre navigateur pour voir les arrêts proches de vous'
            : '📍 Activez votre géolocalisation pour voir les arrêts proches de vous'}
        </p>
      )}
    </div>
  )
}
