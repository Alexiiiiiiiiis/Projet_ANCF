import { api } from './api'
import type { Stop, Departure, StopLine, TransportLine, TrafficAlert, LineTrafficStatus, FavoriteKind, FavoriteStop, Journey, TransportType } from '../types/transport'

export interface StopSearchParams {
  q: string
  type?: string
  limit?: number
}

export interface NearbyParams {
  lat: number
  lon: number
  radius?: number
  type?: string
}

export interface ScheduleResponse {
  stopId: string
  type: TransportType | null
  line: string | null
  /** Toutes les lignes de l'arret, filtres non appliques */
  lines: StopLine[]
  departures: Departure[]
  fetchedAt: string
  refreshInterval: number
}

export interface ScheduleParams {
  /** Ne garder qu'un mode (METRO, RER, TRAM, BUS) */
  type?: TransportType
  /** Ne garder qu'une ligne, par son code public (« RER B », « M4 ») */
  line?: string
  /** Nombre de passages demandes — 5 par defaut cote API, 40 au maximum */
  limit?: number
}

export interface AlertsResponse {
  alerts: TrafficAlert[]
  count: number
  page: number
  limit: number
  totalPages: number
}

export interface JourneysResponse {
  from: string
  to: string
  fetchedAt: string
  count: number
  journeys: Journey[]
}

/** Ce qu'il faut pour mettre un arret ou une ligne en favori */
export interface FavoriteInput {
  stopId: string
  stopName: string
  lineCode: string
  transportType: TransportType
  /** STOP par defaut, comme avant l'arrivee des lignes en favori */
  kind?: FavoriteKind
}

export const transportService = {
  /** Recherche d'arrêts par nom. */
  async searchStops(params: StopSearchParams): Promise<Stop[]> {
    const { data } = await api.get<{ stops: Stop[] }>('/stops/search', { params })
    return data.stops
  },

  /** Arrêts autour d'une position GPS. */
  async getNearbyStops(params: NearbyParams): Promise<Stop[]> {
    const { data } = await api.get<{ stops: Stop[] }>('/stops/nearby', { params })
    return data.stops
  },

  /** Détail d'un arrêt (non utilisée dans l'application). */
  async getStop(stopId: string): Promise<Stop> {
    const { data } = await api.get<Stop>(`/stops/${stopId}`)
    return data
  },

  /** Prochains départs d'un arrêt, filtrables par mode, ligne et nombre. */
  async getDepartures(stopId: string, params?: ScheduleParams): Promise<ScheduleResponse> {
    const { data } = await api.get<ScheduleResponse>(`/schedules/${stopId}`, { params })
    return data
  },

  /** Toutes les lignes d'un mode (METRO, RER, TRAM, BUS). */
  async getLines(type: TransportType): Promise<TransportLine[]> {
    const { data } = await api.get<{ lines: TransportLine[] }>('/lines', { params: { type } })
    return data.lines
  },

  // Les identifiants IDFM contiennent des deux-points (« line:IDFM:C01742 ») : ils sont
  // valides tels quels dans un chemin d'URL, les encoder casserait la route Symfony.
  async getLineStops(lineId: string): Promise<{ line: TransportLine; stops: Stop[] }> {
    const { data } = await api.get<{ line: TransportLine; stops: Stop[] }>(`/lines/${lineId}/stops`)
    return { line: data.line, stops: data.stops }
  },

  // Un seul aller-retour pour toutes les lignes favorites : leur pastille ne vaut pas une
  // requete chacune.
  async getLineStatuses(lineIds: string[]): Promise<LineTrafficStatus[]> {
    if (lineIds.length === 0) return []

    const { data } = await api.get<{ statuses: LineTrafficStatus[] }>('/lines/status', {
      params: { ids: lineIds.join(',') },
    })
    return data.statuses
  },

  /** Dernières recherches de l'utilisateur connecté. */
  async getSearchHistory(): Promise<string[]> {
    const { data } = await api.get<{ queries: string[] }>('/stops/history')
    return data.queries
  },

  /** Perturbations en cours, avec filtres et pagination. */
  async getAlerts(params?: { lineId?: string; severity?: string; type?: string; category?: string; page?: number; limit?: number }): Promise<AlertsResponse> {
    const { data } = await api.get<AlertsResponse>('/alerts', { params })
    return data
  },

  /** Perturbations d'une ligne. */
  async getLineAlerts(lineId: string): Promise<TrafficAlert[]> {
    const { data } = await api.get<AlertsResponse>(`/alerts/${lineId}`)
    return data.alerts
  },

  /** Calcule un itinéraire entre deux arrêts. */
  async searchJourneys(from: string, to: string, fromName?: string, toName?: string): Promise<JourneysResponse> {
    const { data } = await api.get<JourneysResponse>('/journeys', { params: { from, to, fromName, toName } })
    return data
  },

  // Favoris
  /** Favoris de l'utilisateur connecté. */
  async getFavorites(): Promise<FavoriteStop[]> {
    const { data } = await api.get<{ favorites: FavoriteStop[] }>('/favorites')
    return data.favorites
  },

  /** Ajoute un arrêt ou une ligne aux favoris. */
  async addFavorite(favorite: FavoriteInput): Promise<FavoriteStop> {
    const { data } = await api.post<FavoriteStop>('/favorites', favorite)
    return data
  },

  /** Retire un favori. */
  async removeFavorite(id: number): Promise<void> {
    await api.delete(`/favorites/${id}`)
  },

  /** Réordonne les favoris (non utilisée dans l'application). */
  async reorderFavorites(orderedIds: number[]): Promise<void> {
    await api.put('/favorites/reorder', { orderedIds })
  },
}
