import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { act } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { FavoriteStop } from '../types/transport'
// Import de type uniquement : il est effacé à la compilation et n'active donc pas le mock
// du module déclaré plus bas.
import type { ScheduleResponse } from '../services/transportService'

;(globalThis as unknown as { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true

/** Enregistre les arguments reçus par useSchedules, pour vérifier le filtre transmis. */
const appelsHoraires: Array<[string | null, unknown]> = []
let reponse: Partial<ScheduleResponse> = { departures: [] }
let enChargement = false

vi.mock('../hooks/useSchedules', () => ({
  useSchedules: (stopId: string | null, params: unknown) => {
    appelsHoraires.push([stopId, params])

    return { data: stopId ? reponse : undefined, isLoading: enChargement }
  },
}))

vi.mock('../hooks/useLineStatuses', () => ({ useLineStatuses: () => new Map() }))

// getFavorites doit renvoyer la même liste que celle préchargée : React Query rafraîchit en
// arrière-plan, et une fonction qui renvoie undefined déclenche un avertissement à chaque test.
let favorisCourants: FavoriteStop[] = []
vi.mock('../services/transportService', () => ({
  transportService: {
    getFavorites: () => Promise.resolve(favorisCourants),
    removeFavorite: vi.fn(),
  },
}))

const { FavoritesPage } = await import('../pages/FavoritesPage')

const GARGES: FavoriteStop = {
  id: 1,
  stopId: 'stop_area:IDFM:65582',
  stopName: 'Garges - Sarcelles',
  lineCode: 'RER D',
  transportType: 'RER',
  kind: 'STOP',
  addedAt: '2026-09-01T10:00:00+02:00',
  sortOrder: 1,
}

let conteneur: HTMLDivElement
let racine: Root

function rendre(favoris: FavoriteStop[]) {
  favorisCourants = favoris

  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(['favorites'], favoris)

  conteneur = document.createElement('div')
  document.body.appendChild(conteneur)
  racine = createRoot(conteneur)

  act(() => {
    racine.render(
      <QueryClientProvider client={client}>
        <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <FavoritesPage />
        </MemoryRouter>
      </QueryClientProvider>
    )
  })
}

/** Déplie le favori en cliquant sur sa ligne. */
function deplier() {
  const entete = conteneur.querySelector('.cursor-pointer')

  act(() => {
    entete?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
  })
}

const dernierAppel = () => appelsHoraires[appelsHoraires.length - 1]

beforeEach(() => {
  appelsHoraires.length = 0
  reponse = { departures: [] }
  enChargement = false
})

afterEach(() => {
  act(() => racine.unmount())
  conteneur.remove()
})

/**
 * Un arrêt est mis en favori depuis une ligne précise. « RER D à Garges » doit montrer les
 * passages du RER D, pas les bus 133, 252, 269 et le T5 qui desservent le même pôle.
 */
describe('horaires d un favori d arrêt', () => {
  it('demande les passages de la ligne du favori une fois déplié', () => {
    rendre([GARGES])
    deplier()

    const [stopId, params] = dernierAppel()
    expect(stopId).toBe(GARGES.stopId)
    expect(params).toEqual({ line: 'RER D' })
  })

  it("n'applique aucun filtre tant que rien n'est déplié", () => {
    rendre([GARGES])

    expect(dernierAppel()).toEqual([null, { line: undefined }])
  })

  it('affiche tous les passages pour un arrêt enregistré sans ligne', () => {
    rendre([{ ...GARGES, lineCode: '' }])
    deplier()

    const [, params] = dernierAppel()
    expect(params).toEqual({ line: undefined })
  })

  /**
   * Filtrer par ligne rend ce cas courant : plus aucun RER D la nuit, alors que des bus
   * desservent encore l'arrêt. Le Spinner tournerait sur une réponse pourtant arrivée.
   */
  it('annonce l absence de passage au lieu de tourner indéfiniment', () => {
    rendre([GARGES])
    deplier()

    expect(conteneur.textContent).toContain('Aucun passage RER D')
    expect(conteneur.querySelector('svg.animate-spin')).toBeNull()
  })

  it('montre le chargement tant que la réponse n est pas arrivée', () => {
    enChargement = true
    rendre([GARGES])
    deplier()

    expect(conteneur.textContent).not.toContain('Aucun passage')
  })
})
