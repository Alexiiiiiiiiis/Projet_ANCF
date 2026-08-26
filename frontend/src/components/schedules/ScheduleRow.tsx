import type { Departure } from '../../types/transport'
import { TRANSPORT_COLORS } from '../../types/transport'

interface ScheduleRowProps {
  departure: Departure
}

/** « 2026-08-27T08:12:00+02:00 » → « 08:12 », null si l'heure est absente ou illisible. */
function formatDepartureTime(iso?: string | null): string | null {
  if (!iso) return null
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return null

  return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

export function ScheduleRow({ departure }: ScheduleRowProps) {
  const isRealtime = departure.isRealtime
  const minutesLeft = departure.waitMinutes

  // L'heure de passage reste juste quand la page est restée ouverte, là où le nombre de
  // minutes vieillit entre deux rafraîchissements.
  const departureTime = formatDepartureTime(departure.departureTime)
  const details = [
    departureTime ? `Départ à ${departureTime}` : null,
    departure.platform ? `Voie ${departure.platform}` : null,
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <div className="flex items-center gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
      <div
        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
        style={{ backgroundColor: TRANSPORT_COLORS[departure.transportType] ?? '#555' }}
      >
        {departure.lineCode}
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-gray-800">{departure.direction}</p>
        <p className="text-xs text-gray-500">{details}</p>
      </div>

      <div className="flex flex-col items-end shrink-0">
        <span className="text-base font-bold text-gray-900">
          {minutesLeft === 0 ? 'À quai' : minutesLeft === 1 ? '1 min' : `${minutesLeft} min`}
        </span>
        {isRealtime ? (
          <span className="text-[10px] font-medium text-green-600">Temps réel</span>
        ) : (
          <span className="text-[10px] text-gray-400">Théorique</span>
        )}
      </div>
    </div>
  )
}
