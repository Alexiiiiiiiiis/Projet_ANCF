import type { TransportType } from '../../types/transport'
import { TRANSPORT_COLORS } from '../../types/transport'

interface LineBadgeProps {
  /** Code nu de la ligne : « A », « 4 », « T3a », « 72 » */
  code: string
  transportType: TransportType
  /** Couleur officielle IDFM ; à défaut, celle du mode */
  color?: string | null
  textColor?: string | null
  size?: 'sm' | 'md' | 'lg'
}

const SIZES = {
  sm: 'h-6 min-w-6 px-1 text-xs',
  md: 'h-9 min-w-9 px-1.5 text-sm',
  lg: 'h-11 min-w-11 px-2 text-lg',
}

/**
 * Pastille carrée d'une ligne, comme dans l'application Île-de-France Mobilités : la couleur
 * officielle de la ligne prime, celle du mode ne sert que de repli.
 */
export function LineBadge({ code, transportType, color, textColor, size = 'md' }: LineBadgeProps) {
  return (
    <span
      className={`inline-flex shrink-0 items-center justify-center rounded-lg font-bold ${SIZES[size]}`}
      style={{
        backgroundColor: color ?? TRANSPORT_COLORS[transportType] ?? '#6b7280',
        color: textColor ?? '#FFFFFF',
      }}
    >
      {code}
    </span>
  )
}
