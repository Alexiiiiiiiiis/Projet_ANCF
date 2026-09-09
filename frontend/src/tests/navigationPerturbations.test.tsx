import { describe, it, expect, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StopSchedulePage } from '../pages/StopSchedulePage'
import { LinePage } from '../pages/LinePage'
import type { TrafficAlert } from '../types/transport'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: null }) }))
vi.mock('../services/transportService', () => ({ transportService: {} }))

const LIGNE = 'line:IDFM:C01728'
const ARRET = 'stop_area:IDFM:71517'

function alerte(id: string, scope: 'LINE' | 'STOP'): TrafficAlert {
  return {
    id,
    lineCode: 'RER D',
    transportType: 'RER',
    severity: 'MAJOR',
    category: 'INCIDENT',
    scope,
    title: `Titre ${id}`,
    description: `Description ${id}`,
    startDate: '2026-09-09T08:00:00+02:00',
  }
}

// Deux perturbations de ligne noyées dans les pannes d'équipement des gares, comme le flux IDFM.
const ALERTES = [alerte('L1', 'LINE'), alerte('S1', 'STOP'), alerte('L2', 'LINE'), alerte('S2', 'STOP')]

function rendre(route: string, chemin: string, page: JSX.Element) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(['line-alerts', LIGNE], ALERTES)
  client.setQueryData(['line-stops', LIGNE], {
    line: {
      id: LIGNE,
      code: 'D',
      label: 'RER D',
      lineCode: 'RER D',
      transportType: 'RER',
      color: '#008B5B',
      textColor: '#FFFFFF',
    },
    stops: [],
  })

  return renderToStaticMarkup(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[route]}>
        <Routes>
          <Route path={chemin} element={page} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

const routeArret = `/horaires/arret/${encodeURIComponent(ARRET)}?nom=Gare+de+Lyon&mode=RER&ligne=RER+D&ligneId=${encodeURIComponent(LIGNE)}`

describe("bandeau de perturbations d'un arrêt", () => {
  it('compte les seules perturbations de la ligne, pas les pannes de gare', () => {
    expect(rendre(routeArret, '/horaires/arret/:stopId', <StopSchedulePage />)).toContain(
      '2 perturbations en cours sur le RER D'
    )
  })

  it('mène aux infos trafic de la ligne et non aux alertes du réseau', () => {
    const html = rendre(routeArret, '/horaires/arret/:stopId', <StopSchedulePage />)

    expect(html).toContain(`href="/horaires/ligne/${encodeURIComponent(LIGNE)}?onglet=trafic"`)
    expect(html).not.toContain('href="/alertes"')
  })
})

describe('fiche de ligne ouverte depuis ce bandeau', () => {
  const routeLigne = `/horaires/ligne/${encodeURIComponent(LIGNE)}`

  it("ouvre l'onglet Infos trafic sur les perturbations de la ligne", () => {
    const html = rendre(`${routeLigne}?onglet=trafic`, '/horaires/ligne/:lineId', <LinePage />)

    expect(html).toContain('Sur la ligne')
    expect(html).toContain('Titre L1')
    expect(html).toContain('Dans les gares et stations (2)')
  })

  it('reste sur les arrêts sans le paramètre', () => {
    const html = rendre(routeLigne, '/horaires/ligne/:lineId', <LinePage />)

    expect(html).toContain('Rechercher un arrêt')
    expect(html).not.toContain('Sur la ligne')
  })
})
