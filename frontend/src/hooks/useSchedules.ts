import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import type { TransportType } from '../types/transport'

export function useSchedules(stopId: string | null, type?: TransportType) {
  return useQuery({
    // Le mode fait partie de la cle : sans lui, changer de filtre reafficherait la liste
    // precedente depuis le cache de React Query.
    queryKey: ['schedules', stopId, type ?? 'tous'],
    queryFn: () => transportService.getDepartures(stopId!, type),
    enabled: !!stopId,
    refetchInterval: 30_000,
    staleTime: 25_000,
  })
}
