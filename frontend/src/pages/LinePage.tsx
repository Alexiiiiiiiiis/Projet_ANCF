import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { LineBadge } from '../components/lines/LineBadge'
import { AlertCard } from '../components/alerts/AlertCard'
import { Spinner } from '../components/ui/Spinner'
import { FavoriteStar } from '../components/ui/FavoriteStar'
import { useFavorites } from '../hooks/useFavorites'
import { transportService } from '../services/transportService'
import { stopScheduleUrl } from '../utils/stopParams'
import { alertesDeLigne, alertesDeStation } from '../utils/alerts'
import { TrafficPill } from '../components/lines/TrafficPill'
import { useLineStatuses } from '../hooks/useLineStatuses'
import type { Stop } from '../types/transport'

/** Accents ignorés dans le filtre : chercher « ache » doit trouver « Achères ». */
function normaliser(texte: string): string {
  return texte
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

export function LinePage() {
  const { lineId = '' } = useParams()
  const navigate = useNavigate()
  const { canFavorite, isFavorite, toggle, error: favoriError, clearError } = useFavorites()
  const [onglet, setOnglet] = useState<'ARRETS' | 'TRAFIC'>('ARRETS')
  const [filtre, setFiltre] = useState('')
  const [visiblesStations, setVisiblesStations] = useState(10)

  const { data, isLoading, error } = useQuery({
    queryKey: ['line-stops', lineId],
    queryFn: () => transportService.getLineStops(lineId),
    enabled: !!lineId,
    staleTime: 60 * 60_000,
  })

  const { data: alerts = [] } = useQuery({
    queryKey: ['line-alerts', lineId],
    queryFn: () => transportService.getLineAlerts(lineId),
    enabled: !!lineId,
    staleTime: 5 * 60_000,
  })

  const statuts = useLineStatuses(lineId ? [lineId] : [])

  // Le compteur suit les perturbations de la ligne : compter aussi les pannes d'ascenseur des
  // gares afficherait « 65 » en permanence.
  const perturbations = useMemo(() => alertesDeLigne(alerts), [alerts])
  const infosStations = useMemo(() => alertesDeStation(alerts), [alerts])

  const line = data?.line
  const stops = useMemo(() => data?.stops ?? [], [data])

  const arretsFiltres = useMemo(() => {
    const q = normaliser(filtre.trim())
    if (!q) return stops

    return stops.filter((s) => normaliser(s.name).includes(q))
  }, [stops, filtre])

  // Arriver depuis le RER A doit ouvrir la fiche de l'arrêt déjà filtrée sur le RER A.
  const ouvrirArret = (stop: Stop) =>
    navigate(
      stopScheduleUrl(stop, line ? { ligne: line.lineCode, ligneId: line.id } : {})
    )

  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    )
  }

  if (error || !line) {
    return (
      <div className="space-y-4">
        <Link to="/horaires" className="text-sm font-medium text-blue-700 hover:underline">
          ← Horaires
        </Link>
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          Cette ligne est introuvable.
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Link to="/horaires" className="text-xl text-blue-700 hover:text-blue-900" aria-label="Retour">
          ←
        </Link>
        <LineBadge
          code={line.code}
          transportType={line.transportType}
          color={line.color}
          textColor={line.textColor}
        />
        <div className="min-w-0 flex-1">
          <h1 className="truncate text-lg font-bold text-gray-900">{line.label}</h1>
          <div className="mt-0.5 flex items-center gap-2">
            <TrafficPill status={statuts.get(line.id)} />
            {line.network && <span className="truncate text-xs text-gray-400">{line.network}</span>}
          </div>
        </div>
        {canFavorite && (
          <FavoriteStar
            active={isFavorite(line.id)}
            label={line.label}
            onToggle={() =>
              toggle({
                // Une ligne se range dans la même table que les arrêts : stopId porte alors
                // son identifiant IDFM, et kind dit ce qu'on a mis en favori.
                stopId: line.id,
                stopName: line.label,
                lineCode: line.lineCode,
                transportType: line.transportType,
                kind: 'LINE',
              })
            }
          />
        )}
      </div>

      {favoriError && (
        <div className="flex items-center justify-between rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{favoriError}</span>
          <button onClick={clearError} className="ml-3 text-red-400 hover:text-red-600" aria-label="Fermer">
            ✕
          </button>
        </div>
      )}

      <div className="flex border-b border-gray-200">
        <button
          onClick={() => setOnglet('ARRETS')}
          className={`flex-1 border-b-2 pb-2 text-sm font-semibold transition-colors ${
            onglet === 'ARRETS' ? 'border-blue-700 text-blue-700' : 'border-transparent text-gray-500'
          }`}
        >
          Arrêts
        </button>
        <button
          onClick={() => setOnglet('TRAFIC')}
          className={`flex flex-1 items-center justify-center gap-2 border-b-2 pb-2 text-sm font-semibold transition-colors ${
            onglet === 'TRAFIC' ? 'border-blue-700 text-blue-700' : 'border-transparent text-gray-500'
          }`}
        >
          Infos trafic
          {perturbations.length > 0 && (
            <span className="rounded-full border border-blue-300 px-2 text-xs text-blue-700">
              {perturbations.length}
            </span>
          )}
        </button>
      </div>

      {onglet === 'ARRETS' ? (
        <>
          <input
            type="text"
            value={filtre}
            onChange={(e) => setFiltre(e.target.value)}
            placeholder="Rechercher un arrêt..."
            className="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          />

          {arretsFiltres.length === 0 ? (
            <p className="py-10 text-center text-sm text-gray-400">
              {stops.length === 0
                ? 'Aucun arrêt connu pour cette ligne.'
                : 'Aucun arrêt ne correspond.'}
            </p>
          ) : (
            <ul className="space-y-2">
              {arretsFiltres.map((stop) => (
                <li key={stop.id}>
                  <button
                    onClick={() => ouvrirArret(stop)}
                    className="flex w-full items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 text-left shadow-sm transition-colors hover:border-blue-200"
                  >
                    <span className="flex-1 font-medium text-gray-900">{stop.name}</span>
                    <span className="text-gray-300">›</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </>
      ) : alerts.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 py-12 text-gray-400">
          <span className="text-4xl">✅</span>
          <p className="text-sm">Aucune perturbation en cours sur cette ligne</p>
        </div>
      ) : (
        <div className="space-y-5">
          {perturbations.length > 0 && (
            <section className="space-y-3">
              <h2 className="text-sm font-semibold text-gray-500">Sur la ligne</h2>
              {perturbations.map((alert) => (
                <AlertCard key={alert.id} alert={alert} />
              ))}
            </section>
          )}

          {infosStations.length > 0 && (
            <section className="space-y-3">
              {/* Ascenseurs, escalators, accès fermés : utile, mais ce n'est pas l'état du trafic. */}
              <h2 className="text-sm font-semibold text-gray-500">
                Dans les gares et stations ({infosStations.length})
              </h2>
              {infosStations.slice(0, visiblesStations).map((alert) => (
                <AlertCard key={alert.id} alert={alert} />
              ))}
              {infosStations.length > visiblesStations && (
                <button
                  onClick={() => setVisiblesStations((n) => n + 10)}
                  className="w-full rounded-xl border border-gray-200 bg-white py-2.5 text-sm font-medium text-gray-600 transition-colors hover:border-blue-300"
                >
                  Afficher plus ({infosStations.length - visiblesStations} restantes)
                </button>
              )}
            </section>
          )}
        </div>
      )}
    </div>
  )
}
