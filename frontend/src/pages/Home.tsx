import { useState } from 'react'
import { Link } from 'react-router-dom'
import { SearchBar } from '../components/stops/SearchBar'
import { StopCard } from '../components/stops/StopCard'
import { ScheduleRow } from '../components/schedules/ScheduleRow'
import { Spinner } from '../components/ui/Spinner'
import { useSchedules } from '../hooks/useSchedules'
import { useGeolocation } from '../hooks/useGeolocation'
import { useFavorites } from '../hooks/useFavorites'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import { POPULAR_STOPS } from '../data/popularStops'
import { stopScheduleUrl } from '../utils/stopParams'
import { useAuth } from '../context/AuthContext'
import type { FavoriteStop, Stop, TransportType } from '../types/transport'
import { TRANSPORT_COLORS, TRANSPORT_LABELS } from '../types/transport'

const TYPE_FILTERS: TransportType[] = ['METRO', 'RER', 'TRAM', 'BUS']

/** Page d'accueil : recherche, arrêts proches, favoris, arrêts populaires et prochains départs. */
export function Home() {
  const { user } = useAuth()
  const { stops: favoriteStops, isFavorite, toggle, error: favoriteError, clearError } = useFavorites()
  const { lat, lon, error: geoError } = useGeolocation()
  const [selectedStop, setSelectedStop] = useState<Stop | null>(null)
  const [typeFilter, setTypeFilter] = useState<TransportType | null>(null)
  const [presetQuery, setPresetQuery] = useState('')

  const { data: schedules, isLoading: schedulesLoading, dataUpdatedAt } = useSchedules(
    selectedStop?.id ?? null,
    { type: typeFilter ?? undefined }
  )

  // Le GPS renvoie une position légèrement différente à chaque mise à jour (watchPosition) :
  // arrondir à ~111m près évite de relancer une requête réseau à chaque micro-mouvement.
  const roundedLat = lat !== null ? Math.round(lat * 1000) / 1000 : null
  const roundedLon = lon !== null ? Math.round(lon * 1000) / 1000 : null

  // Pas de radius transmis : le backend applique le rayon par défaut réglé dans l'espace
  // d'administration (default_radius). Le figer ici en dupliquerait la valeur et rendrait le
  // réglage sans effet sur la page qu'il est censé piloter.
  const { data: nearbyStops = [] } = useQuery({
    queryKey: ['nearby', roundedLat, roundedLon],
    queryFn: () => transportService.getNearbyStops({ lat: lat!, lon: lon! }),
    enabled: !!(lat && lon),
    staleTime: 120_000,
  })

  // F5.4 — dernières recherches de l'utilisateur
  const { data: recentSearches = [] } = useQuery({
    queryKey: ['search-history'],
    queryFn: transportService.getSearchHistory,
    enabled: !!user,
    staleTime: 60_000,
  })

  /** Ajoute ou retire un arrêt des favoris. */
  const toggleFavorite = (stop: Stop) =>
    toggle({
      stopId: stop.id,
      stopName: stop.name,
      lineCode: stop.lines?.[0] ?? '',
      transportType: stop.transportType,
    })

  /** Garde seulement les arrêts du mode choisi dans le filtre. */
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
  // Seuls les arrets deviennent des cartes ici : une ligne favorite n'a pas de prochains
  // passages, elle se consulte depuis la page Horaires.
  const filteredFavorites = byType(favoriteStops.map(favoriteAsStop))

  /** Affiche la carte d'un arrêt ; un clic affiche ses prochains départs. */
  const renderStopCard = (stop: Stop) => (
    <StopCard
      key={stop.id}
      stop={stop}
      onClick={() => setSelectedStop(stop)}
      isFavorite={isFavorite(stop.id)}
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
          <button onClick={() => clearError()} className="ml-3 text-red-400 hover:text-red-600" aria-label="Fermer">✕</button>
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
            <div className="flex items-center gap-3">
              {dataUpdatedAt > 0 && (
                <span className="text-xs text-gray-400">
                  Mis à jour {new Date(dataUpdatedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </span>
              )}
              {/* L'accueil s'en tient aux huit prochains passages : la page Horaires les donne tous. */}
              <Link
                to={stopScheduleUrl(selectedStop)}
                className="shrink-0 text-sm font-medium text-blue-700 hover:underline"
              >
                Tous les horaires
              </Link>
            </div>
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
            <p className="py-4 text-center text-sm text-gray-400">
              {typeFilter
                ? `Aucun départ ${TRANSPORT_LABELS[typeFilter]} depuis cet arrêt`
                : 'Aucun départ disponible'}
            </p>
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
