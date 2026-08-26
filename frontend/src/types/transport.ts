export type TransportType = 'METRO' | 'RER' | 'TRAM' | 'BUS'
export type AlertSeverity = 'INFO' | 'MODERATE' | 'MAJOR'
export type AlertCategory = 'INCIDENT' | 'TRAVAUX'

export interface Stop {
  id: string
  name: string
  lat: number
  lon: number
  transportType: TransportType
  lines: string[]
  distance?: number
  distanceLabel?: string
}

export interface Departure {
  lineCode: string
  transportType: TransportType
  direction: string
  waitMinutes: number
  isRealtime: boolean
  platform?: string | null
}

export interface StopWithDepartures extends Stop {
  departures: Departure[]
}

export interface TrafficAlert {
  id: string
  lineCode: string
  transportType: TransportType
  severity: AlertSeverity
  category: AlertCategory
  title: string
  description: string
  estimatedResume?: string | null
  startDate: string
  endDate?: string | null
}

export interface JourneySection {
  type: string
  mode: TransportType | 'WALK' | 'WAIT'
  lineCode: string | null
  direction: string | null
  from: string | null
  to: string | null
  departureTime: string | null
  arrivalTime: string | null
  durationMinutes: number
}

export interface Journey {
  departureTime: string | null
  arrivalTime: string | null
  durationMinutes: number
  transfers: number
  sections: JourneySection[]
}

export interface FavoriteStop {
  id: number
  stopId: string
  stopName: string
  lineCode: string
  transportType: TransportType
  addedAt: string
  sortOrder: number
}

export interface User {
  id: number
  email: string
  firstName: string
  lastName: string
  roles: string[]
  isActive: boolean
  createdAt: string
}

export interface ApiQuotaStatus {
  remaining: number
  limit: number
  checkedAt: string
}

export interface AdminStats {
  totalUsers: number
  requestsPerDay: number
  uptime: number
  activeAlerts: number
  errorsToday: number
  avgResponseMs: number
  apiQuota: ApiQuotaStatus | null
}

export interface AdminUser extends User {
  isActive: boolean
}

export const TRANSPORT_COLORS: Record<TransportType, string> = {
  METRO: '#0052CC',
  RER:   '#CC0000',
  TRAM:  '#007A33',
  BUS:   '#FF6900',
}

export const TRANSPORT_LABELS: Record<TransportType, string> = {
  METRO: 'Métro',
  RER:   'RER',
  TRAM:  'Tram',
  BUS:   'Bus',
}

// Icônes utilisées dans les badges ronds (à la place du nom en texte, qui se coupait
// mal dans un petit cercle — ex. "Métro" sur deux lignes) : plus lisible, plus rapide
// à distinguer d'un coup d'œil, la couleur du badge suffit ensuite à confirmer le mode.
export const TRANSPORT_ICONS: Record<TransportType, string> = {
  METRO: '🚇',
  RER:   '🚆',
  TRAM:  '🚊',
  BUS:   '🚌',
}

export const SEVERITY_COLORS: Record<AlertSeverity, string> = {
  MAJOR:    '#DE350B',
  MODERATE: '#FF991F',
  INFO:     '#0052CC',
}

// F4.3 du cahier des charges : "mineur, modéré, majeur" — INFO est le niveau le plus bas
// (perturbation mineure), mais on garde le code interne INFO pour ne pas casser l'API.
export const SEVERITY_LABELS: Record<AlertSeverity, string> = {
  MAJOR:    'MAJEUR',
  MODERATE: 'MODÉRÉ',
  INFO:     'MINEUR',
}

export const CATEGORY_LABELS: Record<AlertCategory, string> = {
  INCIDENT: 'Incident',
  TRAVAUX:  'Travaux programmés',
}

export const CATEGORY_COLORS: Record<AlertCategory, string> = {
  INCIDENT: '#DE350B',
  TRAVAUX:  '#6554C0',
}
