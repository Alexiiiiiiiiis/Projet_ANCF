import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { SearchBar } from '../components/stops/SearchBar'
import { LineBadge } from '../components/lines/LineBadge'
import { TrafficPill } from '../components/lines/TrafficPill'
import { Spinner } from '../components/ui/Spinner'
import { useFavorites } from '../hooks/useFavorites'
import { useLineStatuses } from '../hooks/useLineStatuses'
import { transportService } from '../services/transportService'
import { stopScheduleUrl } from '../utils/stopParams'
import type { FavoriteStop, Stop, TransportType } from '../types/transport'
import { TRANSPORT_COLORS, TRANSPORT_ICONS } from '../types/transport'

// Onglets de la page Horaires d'Île-de-France Mobilités : les favoris, puis les modes du
// plus structurant au moins structurant.
const TABS: { value: 'FAVORIS' | TransportType; label: string; icon: string }[] = [
  { value: 'FAVORIS', label: 'Favoris', icon: '⭐' },
  { value: 'RER', label: 'RER', icon: '🚆' },
  { value: 'METRO', label: 'Métro', icon: '🚇' },
  { value: 'TRAM', label: 'Tram', icon: '🚊' },
  { value: 'BUS', label: 'Bus', icon: '🚌' },
]

// Le réseau bus compte près de 2000 lignes : on en affiche une tranche, le filtre fait le reste.
const BUS_PAGE = 60

export function SchedulesPage() {
  const navigate = useNavigate()
  const { stops: arretsFavoris, lines: lignesFavorites, canFavorite } = useFavorites()
  const statuts = useLineStatuses(lignesFavorites.map((f) => f.stopId))
  const [tab, setTab] = useState<'FAVORIS' | TransportType>('RER')
  const [filtre, setFiltre] = useState('')
  const [affichees, setAffichees] = useState(BUS_PAGE)

  const { data: lines = [], isLoading } = useQuery({
    queryKey: ['lines', tab],
    queryFn: () => transportService.getLines(tab as TransportType),
    enabled: tab !== 'FAVORIS',
    // Le catalogue des lignes ne bouge pas dans la journée.
    staleTime: 60 * 60_000,
  })

  const lignesFiltrees = useMemo(() => {
    const q = filtre.trim().toLowerCase()
    if (!q) return lines

    return lines.filter(
      (l) => l.code.toLowerCase().includes(q) || (l.network ?? '').toLowerCase().includes(q)
    )
  }, [lines, filtre])

  const changerOnglet = (value: 'FAVORIS' | TransportType) => {
    setTab(value)
    setFiltre('')
    setAffichees(BUS_PAGE)
  }

  const ouvrirArret = (stop: Stop) => navigate(stopScheduleUrl(stop))

  const favoriAsStop = (fav: FavoriteStop): Stop => ({
    id: fav.stopId,
    name: fav.stopName,
    lat: 0,
    lon: 0,
    transportType: fav.transportType,
    lines: fav.lineCode ? [fav.lineCode] : [],
  })

  // Au-delà d'une trentaine de lignes, chercher au clavier devient plus rapide que défiler.
  const avecFiltre = lines.length > 30
  const visibles = lignesFiltrees.slice(0, affichees)

  return (
    <div className="space-y-4">
      <h1 className="text-center text-xl font-bold text-gray-900">Horaires</h1>

      <SearchBar onSelect={ouvrirArret} placeholder="Rechercher une gare, station ou arrêt..." />

      {/* Onglets de mode */}
      <div className="flex border-b border-gray-200">
        {TABS.map((t) => {
          const active = tab === t.value
          return (
            <button
              key={t.value}
              onClick={() => changerOnglet(t.value)}
              className={`flex flex-1 flex-col items-center gap-0.5 border-b-2 px-1 pb-2 pt-1 text-[11px] font-medium transition-colors ${
                active
                  ? 'border-blue-700 text-blue-700'
                  : 'border-transparent text-gray-500 hover:text-blue-600'
              }`}
              aria-pressed={active}
            >
              <span className="text-lg">{t.icon}</span>
              {t.label}
            </button>
          )
        })}
      </div>

      {tab === 'FAVORIS' ? (
        !canFavorite ? (
          <p className="py-10 text-center text-sm text-gray-400">
            Connectez-vous pour retrouver vos lignes et vos arrêts favoris ici.
          </p>
        ) : lignesFavorites.length === 0 && arretsFavoris.length === 0 ? (
          <p className="py-10 text-center text-sm text-gray-400">
            Aucun favori pour l'instant — touchez l'étoile sur une ligne ou un arrêt.
          </p>
        ) : (
          <div className="space-y-5">
            {lignesFavorites.length > 0 && (
              <section>
                <h2 className="mb-2 text-sm font-semibold text-gray-500">Lignes</h2>
                <ul className="space-y-2">
                  {lignesFavorites.map((fav) => (
                    <li key={fav.id}>
                      <button
                        onClick={() => navigate(`/horaires/ligne/${encodeURIComponent(fav.stopId)}`)}
                        className="flex w-full items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 text-left shadow-sm transition-colors hover:border-blue-200"
                      >
                        <LineBadge code={fav.lineCode} transportType={fav.transportType} />
                        <span className="min-w-0 flex-1">
                          <span className="block truncate font-medium text-gray-900">{fav.stopName}</span>
                          <TrafficPill status={statuts.get(fav.stopId)} />
                        </span>
                        <span className="text-gray-300">›</span>
                      </button>
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {arretsFavoris.length > 0 && (
              <section>
                <h2 className="mb-2 text-sm font-semibold text-gray-500">Arrêts</h2>
                <ul className="space-y-2">
                  {arretsFavoris.map((fav) => {
                    const stop = favoriAsStop(fav)
                    return (
                      <li key={fav.id}>
                        <button
                          onClick={() => ouvrirArret(stop)}
                          className="flex w-full items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 text-left shadow-sm transition-colors hover:border-blue-200"
                        >
                          <span
                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base"
                            style={{ backgroundColor: TRANSPORT_COLORS[stop.transportType] }}
                          >
                            {TRANSPORT_ICONS[stop.transportType]}
                          </span>
                          <span className="flex-1 truncate font-medium text-gray-900">{stop.name}</span>
                          <span className="text-gray-300">›</span>
                        </button>
                      </li>
                    )
                  })}
                </ul>
              </section>
            )}
          </div>
        )
      ) : isLoading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : (
        <>
          {avecFiltre && (
            <input
              type="text"
              value={filtre}
              onChange={(e) => {
                setFiltre(e.target.value)
                setAffichees(BUS_PAGE)
              }}
              placeholder="Filtrer par numéro ou réseau..."
              className="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            />
          )}

          <ul className="space-y-2">
            {visibles.map((line) => (
              <li key={line.id}>
                <button
                  onClick={() => navigate(`/horaires/ligne/${encodeURIComponent(line.id)}`)}
                  className="flex w-full items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 text-left shadow-sm transition-colors hover:border-blue-200"
                >
                  <LineBadge
                    code={line.code}
                    transportType={line.transportType}
                    color={line.color}
                    textColor={line.textColor}
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium text-gray-900">{line.label}</span>
                    {line.transportType === 'BUS' && line.network && (
                      <span className="block truncate text-xs text-gray-400">{line.network}</span>
                    )}
                  </span>
                  <span className="text-gray-300">›</span>
                </button>
              </li>
            ))}
          </ul>

          {lignesFiltrees.length === 0 && (
            <p className="py-10 text-center text-sm text-gray-400">Aucune ligne ne correspond.</p>
          )}

          {lignesFiltrees.length > visibles.length && (
            <button
              onClick={() => setAffichees((n) => n + BUS_PAGE)}
              className="w-full rounded-xl border border-gray-200 bg-white py-2.5 text-sm font-medium text-gray-600 transition-colors hover:border-blue-300"
            >
              Afficher plus ({lignesFiltrees.length - visibles.length} restantes)
            </button>
          )}
        </>
      )}
    </div>
  )
}
