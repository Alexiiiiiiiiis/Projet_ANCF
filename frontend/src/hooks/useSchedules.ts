import { useQuery } from '@tanstack/react-query'
import { transportService, type ScheduleParams } from '../services/transportService'

export function useSchedules(stopId: string | null, params: ScheduleParams = {}) {
  const { type, line, limit } = params

  return useQuery({
    // Les filtres font partie de la cle : sans eux, en changer reafficherait la liste
    // precedente depuis le cache de React Query.
    queryKey: ['schedules', stopId, type ?? 'tous', line ?? 'toutes', limit ?? 'defaut'],
    queryFn: () => transportService.getDepartures(stopId!, { type, line, limit }),
    enabled: !!stopId,
    refetchInterval: 30_000,
    staleTime: 25_000,
  })
}
