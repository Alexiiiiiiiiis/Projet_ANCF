import type { LineTrafficStatus } from '../../types/transport'
import { TRAFFIC_COLORS, TRAFFIC_LABELS } from '../../types/transport'

interface TrafficPillProps {
  /** Absent tant que l'état n'est pas connu : la pastille ne s'affiche alors pas */
  status?: LineTrafficStatus
  /** Réduit la pastille à son point de couleur, sans le libellé */
  compact?: boolean
}

/**
 * État de trafic d'une ligne. Ne comptent que les perturbations qui visent la ligne et qui
 * sont en cours : une panne d'ascenseur en gare ne rend pas le trafic perturbé.
 */
export function TrafficPill({ status, compact = false }: TrafficPillProps) {
  if (!status) return null

  const color = TRAFFIC_COLORS[status.severity] ?? TRAFFIC_COLORS.NORMAL
  const label = TRAFFIC_LABELS[status.severity] ?? TRAFFIC_LABELS.NORMAL
  const detail = status.count > 0 && status.title ? `${label} — ${status.title}` : label

  return (
    <span
      className="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium"
      style={{ backgroundColor: `${color}1a`, color }}
      title={detail}
    >
      <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: color }} />
      {!compact && label}
      {!compact && status.count > 1 && ` (${status.count})`}
    </span>
  )
}
