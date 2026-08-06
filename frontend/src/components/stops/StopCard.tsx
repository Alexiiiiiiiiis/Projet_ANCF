import type { Stop } from '../../types/transport'
import { TRANSPORT_COLORS, TRANSPORT_ICONS, TRANSPORT_LABELS } from '../../types/transport'

interface StopCardProps {
  stop: Stop
  onClick?: () => void
  isFavorite?: boolean
  onToggleFavorite?: () => void
  distance?: string
}

export function StopCard({ stop, onClick, isFavorite, onToggleFavorite, distance }: StopCardProps) {
  return (
    <div
      className="group flex cursor-pointer items-start gap-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:border-blue-200 hover:shadow-md"
      onClick={onClick}
    >
      <div
        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xl"
        style={{ backgroundColor: TRANSPORT_COLORS[stop.transportType] }}
        title={TRANSPORT_LABELS[stop.transportType]}
        aria-label={TRANSPORT_LABELS[stop.transportType]}
      >
        {TRANSPORT_ICONS[stop.transportType]}
      </div>

      <div className="min-w-0 flex-1">
        <p className="font-semibold text-gray-900 truncate">{stop.name}</p>
        {stop.lines && stop.lines.length > 0 && (
          <p className="mt-0.5 text-xs text-gray-500">
            Lignes : {stop.lines.join(', ')}
          </p>
        )}
        {distance && (
          <p className="mt-0.5 text-xs text-blue-600">{distance}</p>
        )}
      </div>

      {onToggleFavorite && (
        <button
          onClick={(e) => { e.stopPropagation(); onToggleFavorite() }}
          className="shrink-0 text-xl transition-transform hover:scale-110"
          aria-label={isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'}
        >
          {isFavorite ? '⭐' : '☆'}
        </button>
      )}
    </div>
  )
}
