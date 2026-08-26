import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../services/transportService'
import type { LineTrafficStatus } from '../types/transport'

/**
 * État de trafic de plusieurs lignes, indexé par identifiant. Un seul appel couvre toutes les
 * lignes affichées, et la clé de cache est triée pour qu'un simple changement d'ordre des
 * favoris ne relance pas la requête.
 */
export function useLineStatuses(lineIds: string[]): Map<string, LineTrafficStatus> {
  const ids = useMemo(() => [...new Set(lineIds)].sort(), [lineIds])

  const { data = [] } = useQuery({
    queryKey: ['line-statuses', ids.join(',')],
    queryFn: () => transportService.getLineStatuses(ids),
    enabled: ids.length > 0,
    // Les perturbations sont mises en cache 30 min côté serveur : inutile de sonder plus vite.
    staleTime: 5 * 60_000,
    refetchInterval: 5 * 60_000,
  })

  return useMemo(() => new Map(data.map((status) => [status.lineId, status])), [data])
}
