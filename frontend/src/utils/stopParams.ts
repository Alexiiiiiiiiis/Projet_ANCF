import type { Stop, TransportType } from '../types/transport'

const MODES: string[] = ['METRO', 'RER', 'TRAM', 'BUS']

/**
 * L'arrêt consulté est porté par l'URL : /horaires et /carte se passent ainsi le relais sans
 * état partagé, et la page se partage ou se met en favori du navigateur telle quelle.
 */
export function stopToParams(stop: Stop): Record<string, string> {
  const params: Record<string, string> = {
    arret: stop.id,
    nom: stop.name,
    mode: stop.transportType,
  }

  // Un favori ne connaît pas ses coordonnées (0, 0) : mieux vaut ne rien écrire que de
  // renvoyer la carte au large du golfe de Guinée.
  if (stop.lat && stop.lon) {
    params.lat = String(stop.lat)
    params.lon = String(stop.lon)
  }

  return params
}

/**
 * Lien vers les horaires d'un arrêt. `extra` transporte la ligne d'où l'on vient (`ligne`,
 * `ligneId`) : arriver depuis le RER A doit ouvrir la fiche déjà filtrée sur le RER A.
 */
export function stopScheduleUrl(stop: Stop, extra: Record<string, string> = {}): string {
  const { arret, ...reste } = stopToParams(stop)
  const params = new URLSearchParams({ ...reste, ...extra })

  return `/horaires/arret/${encodeURIComponent(arret)}?${params.toString()}`
}

export function stopFromParams(params: URLSearchParams): Stop | null {
  const id = params.get('arret')
  if (!id) return null

  const mode = params.get('mode') ?? ''
  const lat = Number(params.get('lat'))
  const lon = Number(params.get('lon'))

  return {
    id,
    name: params.get('nom') ?? 'Arrêt',
    lat: Number.isFinite(lat) ? lat : 0,
    lon: Number.isFinite(lon) ? lon : 0,
    transportType: MODES.includes(mode) ? (mode as TransportType) : 'METRO',
    lines: [],
  }
}
