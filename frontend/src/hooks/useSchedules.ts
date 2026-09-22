import { useQuery } from '@tanstack/react-query'
import { transportService, type ScheduleParams } from '../services/transportService'
import { useAppConfig } from './useAppConfig'

/** Prochains départs d'un arrêt, à la cadence réglée dans l'espace d'administration. */
export function useSchedules(stopId: string | null, params: ScheduleParams = {}) {
  const { type, line, limit } = params
  const { refreshInterval } = useAppConfig()
  const intervalleMs = refreshInterval * 1000

  return useQuery({
    // Les filtres font partie de la cle : sans eux, en changer reafficherait la liste
    // precedente depuis le cache de React Query.
    queryKey: ['schedules', stopId, type ?? 'tous', line ?? 'toutes', limit ?? 'defaut'],
    queryFn: () => transportService.getDepartures(stopId!, { type, line, limit }),
    enabled: !!stopId,
    refetchInterval: intervalleMs,
    // Légèrement sous l'intervalle : une donnée doit être considérée périmée juste avant le
    // rafraîchissement suivant, jamais après, sinon un rendu intermédiaire ressert l'ancienne.
    staleTime: Math.max(intervalleMs - 5_000, 1_000),
  })
}
