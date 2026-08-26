import { describe, it, expect } from 'vitest'
import { alertesDeLigne, alertesDeStation } from '../utils/alerts'
import { TRAFFIC_COLORS, TRAFFIC_LABELS, type AlertScope, type TrafficAlert } from '../types/transport'

function alerte(id: string, scope: AlertScope): TrafficAlert {
  return {
    id,
    lineCode: 'RER A',
    transportType: 'RER',
    severity: 'MODERATE',
    category: 'INCIDENT',
    scope,
    title: `Perturbation ${id}`,
    description: '',
    startDate: '2026-08-27T00:00:00+02:00',
    endDate: null,
  }
}

describe('tri des perturbations par portée', () => {
  const alertes = [alerte('ligne', 'LINE'), alerte('ascenseur', 'STOP'), alerte('travaux', 'LINE')]

  it('ne garde que les perturbations de la ligne', () => {
    expect(alertesDeLigne(alertes).map((a) => a.id)).toEqual(['ligne', 'travaux'])
  })

  it('isole les infos de gare', () => {
    expect(alertesDeStation(alertes).map((a) => a.id)).toEqual(['ascenseur'])
  })

  it('traite une alerte sans portée comme une perturbation de ligne', () => {
    // Les alertes simulées (sans clé API) n'en portent pas : elles visent toutes la ligne.
    const sansPortee = { ...alerte('mock', 'LINE'), scope: undefined } as unknown as TrafficAlert

    expect(alertesDeLigne([sansPortee])).toHaveLength(1)
    expect(alertesDeStation([sansPortee])).toHaveLength(0)
  })
})

describe('TRAFFIC_COLORS / TRAFFIC_LABELS', () => {
  it('couvre le trafic normal et les trois niveaux de gravité', () => {
    for (const etat of ['NORMAL', 'INFO', 'MODERATE', 'MAJOR'] as const) {
      expect(TRAFFIC_COLORS[etat]).toMatch(/^#[0-9a-fA-F]{6}$/)
      expect(TRAFFIC_LABELS[etat]).toBeTruthy()
    }
  })

  it('annonce un trafic normal en vert', () => {
    expect(TRAFFIC_LABELS.NORMAL).toBe('Trafic normal')
    expect(TRAFFIC_COLORS.NORMAL).toBe('#0B8A3D')
  })
})
