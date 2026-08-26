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
  /** Heure de passage ISO — absente des donnees mises en cache avant son ajout */
  departureTime?: string | null
}

/** Une ligne desservant un arret, telle qu'annoncee par /api/schedules */
export interface StopLine {
  lineCode: string
  transportType: TransportType
}

/** Une ligne du reseau, telle que catalogue par /api/lines */
export interface TransportLine {
  id: string
  /** Code nu affiche dans la pastille : « A », « 4 », « T3a », « 72 » */
  code: string
  /** Libelle public : « RER A », « Train H », « Metro 4 », « Bus 72 » */
  label: string
  /** Code annonce par les horaires — c'est lui qui filtre les departs d'un arret */
  lineCode: string
  transportType: TransportType
  /** Couleur officielle IDFM, null si l'API ne la donne pas */
  color: string | null
  textColor: string | null
  /** Reseau exploitant — distingue les numeros de bus partages */
  network: string | null
}

export interface StopWithDepartures extends Stop {
  departures: Departure[]
}

/** LINE : la ligne elle-meme est touchee. STOP : un equipement de gare (ascenseur, acces). */
export type AlertScope = 'LINE' | 'STOP'

export interface TrafficAlert {
  id: string
  lineCode: string
  transportType: TransportType
  severity: AlertSeverity
  category: AlertCategory
  scope: AlertScope
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

/** Un favori porte soit un arret, soit une ligne entiere */
export type FavoriteKind = 'STOP' | 'LINE'

export interface FavoriteStop {
  id: number
  /** Identifiant de l'arret, ou de la ligne quand kind vaut LINE */
  stopId: string
  stopName: string
  lineCode: string
  transportType: TransportType
  kind: FavoriteKind
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

/** Etat de trafic d'une ligne, tel que le resume /api/lines/status */
export interface LineTrafficStatus {
  lineId: string
  severity: AlertSeverity | 'NORMAL'
  category: AlertCategory | null
  /** Perturbations en cours sur la ligne */
  count: number
  title: string | null
}

export const TRAFFIC_COLORS: Record<AlertSeverity | 'NORMAL', string> = {
  NORMAL:   '#0B8A3D',
  INFO:     '#0052CC',
  MODERATE: '#FF991F',
  MAJOR:    '#DE350B',
}

export const TRAFFIC_LABELS: Record<AlertSeverity | 'NORMAL', string> = {
  NORMAL:   'Trafic normal',
  INFO:     'Info trafic',
  MODERATE: 'Trafic perturbé',
  MAJOR:    'Trafic très perturbé',
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
