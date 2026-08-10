import { api } from './api'
import type { Stop, Departure, TrafficAlert, FavoriteStop, Journey } from '../types/transport'

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
  departures: Departure[]
  fetchedAt: string
  refreshInterval: number
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

export const transportService = {
  async searchStops(params: StopSearchParams): Promise<Stop[]> {
    const { data } = await api.get<{ stops: Stop[] }>('/stops/search', { params })
    return data.stops
  },

  async getNearbyStops(params: NearbyParams): Promise<Stop[]> {
    const { data } = await api.get<{ stops: Stop[] }>('/stops/nearby', { params })
    return data.stops
  },

  async getStop(stopId: string): Promise<Stop> {
    const { data } = await api.get<Stop>(`/stops/${stopId}`)
    return data
  },

  async getDepartures(stopId: string): Promise<ScheduleResponse> {
    const { data } = await api.get<ScheduleResponse>(`/schedules/${stopId}`)
    return data
  },

  async getSearchHistory(): Promise<string[]> {
    const { data } = await api.get<{ queries: string[] }>('/stops/history')
    return data.queries
  },

  async getAlerts(params?: { lineId?: string; severity?: string; type?: string; category?: string; page?: number; limit?: number }): Promise<AlertsResponse> {
    const { data } = await api.get<AlertsResponse>('/alerts', { params })
    return data
  },

  async getLineAlerts(lineId: string): Promise<TrafficAlert[]> {
    const { data } = await api.get<AlertsResponse>(`/alerts/${lineId}`)
    return data.alerts
  },

  async searchJourneys(from: string, to: string, fromName?: string, toName?: string): Promise<JourneysResponse> {
    const { data } = await api.get<JourneysResponse>('/journeys', { params: { from, to, fromName, toName } })
    return data
  },

  // Favorites
  async getFavorites(): Promise<FavoriteStop[]> {
    const { data } = await api.get<{ favorites: FavoriteStop[] }>('/favorites')
    return data.favorites
  },

  async addFavorite(stop: Omit<FavoriteStop, 'id' | 'addedAt' | 'sortOrder'>): Promise<FavoriteStop> {
    const { data } = await api.post<FavoriteStop>('/favorites', stop)
    return data
  },

  async removeFavorite(id: number): Promise<void> {
    await api.delete(`/favorites/${id}`)
  },

  async reorderFavorites(orderedIds: number[]): Promise<void> {
    await api.put('/favorites/reorder', { orderedIds })
  },
}
