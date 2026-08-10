import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'

export function useSchedules(stopId: string | null) {
  return useQuery({
    queryKey: ['schedules', stopId],
    queryFn: () => transportService.getDepartures(stopId!),
    enabled: !!stopId,
    refetchInterval: 30_000,
    staleTime: 25_000,
  })
}
