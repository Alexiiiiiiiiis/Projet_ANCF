import { useMemo, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { LineBadge } from '../components/lines/LineBadge'
import { ScheduleRow } from '../components/schedules/ScheduleRow'
import { Spinner } from '../components/ui/Spinner'
import { FavoriteStar } from '../components/ui/FavoriteStar'
import { useFavorites } from '../hooks/useFavorites'
import { useSchedules } from '../hooks/useSchedules'
import { transportService } from '../services/transportService'
import { stopFromParams, stopToParams } from '../utils/stopParams'
import { alertesDeLigne } from '../utils/alerts'
import type { StopLine, TransportType } from '../types/transport'
import { TRANSPORT_COLORS } from '../types/transport'

// La fiche d'un arrêt annonce tous ses passages connus, là où l'accueil s'en tient aux cinq
// prochains : c'est tout l'intérêt d'une page dédiée.
const DEPARTURES_LIMIT = 40

const TOUTES = 'TOUTES'

/** Fiche d'un arrêt : tous ses prochains départs, filtrables par ligne et par direction. */
export function StopSchedulePage() {
  const { stopId = '' } = useParams()
  const [searchParams] = useSearchParams()

  const stopName = searchParams.get('nom') ?? 'Arrêt'
  const mode = (searchParams.get('mode') ?? 'METRO') as TransportType
  // Arriver depuis la fiche du RER A ouvre l'arrêt déjà filtré sur le RER A.
  const ligneDorigine = searchParams.get('ligne')
  const ligneIdDorigine = searchParams.get('ligneId')

  const { canFavorite, isFavorite, toggle, error: favoriError, clearError } = useFavorites()
  const [line, setLine] = useState<string | null>(ligneDorigine)
  const [direction, setDirection] = useState<string>(TOUTES)

  const { data, isLoading, error, dataUpdatedAt } = useSchedules(stopId, {
    line: line ?? undefined,
    limit: DEPARTURES_LIMIT,
  })

  // Perturbations de la ligne d'où l'on vient, comme le bandeau de l'app IDFM.
  const { data: alerts = [] } = useQuery({
    queryKey: ['line-alerts', ligneIdDorigine],
    queryFn: () => transportService.getLineAlerts(ligneIdDorigine!),
    enabled: !!ligneIdDorigine,
    staleTime: 5 * 60_000,
  })

  // Seules les perturbations de la ligne justifient le bandeau : une panne d'ascenseur dans
  // une autre gare de la ligne n'a rien à y faire.
  const perturbations = useMemo(() => alertesDeLigne(alerts), [alerts])

  const lines = useMemo<StopLine[]>(() => {
    const annoncees = data?.lines ?? []
    // La nuit, un arrêt du RER A n'annonce que ses bus de substitution : sans ce repli, on
    // arriverait depuis la fiche du RER A sur une liste vide et sans bouton pour en sortir.
    if (!ligneDorigine || annoncees.some((l) => l.lineCode === ligneDorigine)) {
      return annoncees
    }

    return [{ lineCode: ligneDorigine, transportType: mode }, ...annoncees]
  }, [data, ligneDorigine, mode])
  const departures = useMemo(() => data?.departures ?? [], [data])

  // Les directions se lisent dans les départs annoncés : elles suivent donc le filtre de ligne.
  const directions = useMemo(
    () => [...new Set(departures.map((d) => d.direction))].sort((a, b) => a.localeCompare(b, 'fr')),
    [departures]
  )

  const affiches = useMemo(
    () => (direction === TOUTES ? departures : departures.filter((d) => d.direction === direction)),
    [departures, direction]
  )

  /** Change le filtre de ligne et remet à zéro le filtre de direction. */
  const changerLigne = (value: string | null) => {
    setLine(value)
    // Une direction du RER B n'existe plus quand on bascule sur le M4.
    setDirection(TOUTES)
  }

  const stop = stopFromParams(new URLSearchParams({ ...Object.fromEntries(searchParams), arret: stopId }))
  const carteUrl = stop && stop.lat && stop.lon
    ? `/carte?${new URLSearchParams(stopToParams(stop)).toString()}`
    : null

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-3">
          <Link
            to={ligneIdDorigine ? `/horaires/ligne/${encodeURIComponent(ligneIdDorigine)}` : '/horaires'}
            className="text-xl text-blue-700 hover:text-blue-900"
            aria-label="Retour"
          >
            ←
          </Link>
          <div className="min-w-0">
            <h1 className="truncate text-lg font-bold text-gray-900">{stopName}</h1>
            <div className="mt-1 flex flex-wrap items-center gap-1">
              {lines.slice(0, 8).map((l) => (
                <LineBadge
                  key={`${l.transportType}-${l.lineCode}`}
                  code={l.lineCode}
                  transportType={l.transportType}
                  size="sm"
                />
              ))}
            </div>
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-3">
          {carteUrl && (
            <Link to={carteUrl} className="text-sm font-medium text-blue-700 hover:underline">
              Voir sur la carte
            </Link>
          )}
          {canFavorite && (
            <FavoriteStar
              active={isFavorite(stopId)}
              label={stopName}
              onToggle={() =>
                toggle({
                  stopId,
                  stopName,
                  lineCode: ligneDorigine ?? lines[0]?.lineCode ?? '',
                  transportType: mode,
                })
              }
            />
          )}
        </div>
      </div>

      {favoriError && (
        <div className="flex items-center justify-between rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{favoriError}</span>
          <button onClick={clearError} className="ml-3 text-red-400 hover:text-red-600" aria-label="Fermer">
            ✕
          </button>
        </div>
      )}

      {/* Le bandeau compte les perturbations d'une seule ligne : il doit ouvrir ses infos
          trafic, pas les alertes de tout le réseau. */}
      {perturbations.length > 0 && (
        <Link
          to={`/horaires/ligne/${encodeURIComponent(ligneIdDorigine!)}?onglet=trafic`}
          className="flex items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 transition-colors hover:border-red-300"
        >
          <span>
            ⚠️ {perturbations.length} perturbation{perturbations.length > 1 ? 's' : ''} en cours
            {ligneDorigine ? ` sur le ${ligneDorigine}` : ''}
          </span>
          <span className="text-red-400">›</span>
        </Link>
      )}

      {/* Filtre par ligne — « à Gare du Nord, le RER B et pas les métros » */}
      {lines.length > 1 && (
        <div className="flex flex-wrap items-center gap-2">
          <button
            onClick={() => changerLigne(null)}
            className={`rounded-lg border px-3 py-1 text-sm font-medium transition-colors ${
              line === null
                ? 'border-gray-800 bg-gray-800 text-white'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-400'
            }`}
          >
            Toutes les lignes
          </button>
          {lines.map((l) => {
            const active = line === l.lineCode
            return (
              <button
                key={`${l.transportType}-${l.lineCode}`}
                onClick={() => changerLigne(active ? null : l.lineCode)}
                className={`rounded-lg px-3 py-1 text-sm font-bold text-white transition-all ${
                  active ? 'ring-2 ring-gray-900 ring-offset-1' : 'opacity-80 hover:opacity-100'
                }`}
                style={{ backgroundColor: TRANSPORT_COLORS[l.transportType] }}
                aria-pressed={active}
              >
                {l.lineCode}
              </button>
            )
          })}
        </div>
      )}

      {/* « Vers Toutes les directions » de l'app IDFM */}
      {directions.length > 1 && (
        <label className="flex items-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm">
          <span className="text-gray-500">Vers</span>
          <select
            value={direction}
            onChange={(e) => setDirection(e.target.value)}
            className="min-w-0 flex-1 bg-transparent font-semibold text-gray-900 outline-none"
          >
            <option value={TOUTES}>Toutes les directions</option>
            {directions.map((d) => (
              <option key={d} value={d}>
                {d}
              </option>
            ))}
          </select>
        </label>
      )}

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : error ? (
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          Impossible de charger les horaires de cet arrêt.
        </div>
      ) : affiches.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 py-12 text-gray-400">
          <span className="text-4xl">🕐</span>
          <p className="text-sm">
            {line
              ? `Aucun passage ${line} annoncé depuis cet arrêt`
              : direction !== TOUTES
                ? `Aucun passage vers ${direction} annoncé depuis cet arrêt`
                : 'Aucun passage annoncé depuis cet arrêt'}
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          <div className="flex items-center justify-between text-xs text-gray-400">
            <span>
              {affiches.length} passage{affiches.length > 1 ? 's' : ''} annoncé
              {affiches.length > 1 ? 's' : ''}
            </span>
            {dataUpdatedAt > 0 && (
              <span>
                Mis à jour à{' '}
                {new Date(dataUpdatedAt).toLocaleTimeString('fr-FR', {
                  hour: '2-digit',
                  minute: '2-digit',
                  second: '2-digit',
                })}
              </span>
            )}
          </div>
          {affiches.map((dep, i) => (
            <ScheduleRow
              key={`${dep.lineCode}-${dep.direction}-${dep.waitMinutes}-${i}`}
              departure={dep}
            />
          ))}
        </div>
      )}
    </div>
  )
}
