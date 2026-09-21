import type { TrafficAlert } from '../types/transport'

/**
 * IDFM publie dans le même flux les perturbations de la ligne et les pannes d'équipement d'une
 * gare (ascenseur, escalator, accès fermé). Les secondes sont largement majoritaires — plus de
 * 200 pour le RER A un soir ordinaire, contre 3 vraies perturbations — et les mélanger ferait
 * annoncer un trafic perturbé en permanence.
 */
export function alertesDeLigne(alerts: TrafficAlert[]): TrafficAlert[] {
  return alerts.filter((a) => a.scope !== 'STOP')
}

/** Garde seulement les pannes d'équipement des gares (ascenseur, escalator...). */
export function alertesDeStation(alerts: TrafficAlert[]): TrafficAlert[] {
  return alerts.filter((a) => a.scope === 'STOP')
}
