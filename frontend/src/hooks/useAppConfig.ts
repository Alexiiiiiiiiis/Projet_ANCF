import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '../services/api'

export interface AppConfig {
  refreshInterval: number
  defaultRadius: number
  maxFavorites: number
  maintenanceMode: boolean
}

/**
 * Valeurs de repli, alignées sur celles du backend (ConfigController) : si /api/config ne répond
 * pas, l'application doit continuer à fonctionner plutôt que d'attendre une configuration.
 */
const DEFAUTS: AppConfig = {
  refreshInterval: 30,
  defaultRadius: 500,
  maxFavorites: 20,
  maintenanceMode: false,
}

/**
 * Paramètres système réglables depuis l'espace d'administration.
 *
 * Rafraîchi périodiquement pour que la sortie du mode maintenance se voie sans rechargement, et
 * immédiatement sur l'événement émis par l'intercepteur HTTP quand une requête revient en 503 —
 * sans ça, un utilisateur en cours de navigation resterait sur des erreurs jusqu'au prochain
 * sondage.
 */
export function useAppConfig(): AppConfig {
  const { data, refetch } = useQuery({
    queryKey: ['config'],
    queryFn: async () => (await api.get<AppConfig>('/config')).data,
    staleTime: 30_000,
    refetchInterval: 60_000,
    retry: false,
  })

  useEffect(() => {
    const surMaintenance = () => void refetch()
    window.addEventListener('ancf:maintenance', surMaintenance)

    return () => window.removeEventListener('ancf:maintenance', surMaintenance)
  }, [refetch])

  return data ?? DEFAUTS
}
