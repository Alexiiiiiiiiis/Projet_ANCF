import type { TrafficAlert } from '../../types/transport'
import { SEVERITY_COLORS, SEVERITY_LABELS, CATEGORY_LABELS, CATEGORY_COLORS } from '../../types/transport'

interface AlertCardProps {
  alert: TrafficAlert
}

/** Carte d'une perturbation : titre, gravité, catégorie, description et dates. */
export function AlertCard({ alert }: AlertCardProps) {
  const color = SEVERITY_COLORS[alert.severity] ?? '#6b7280'
  const label = SEVERITY_LABELS[alert.severity] ?? alert.severity
  const categoryLabel = CATEGORY_LABELS[alert.category] ?? alert.category
  const categoryColor = CATEGORY_COLORS[alert.category] ?? '#6b7280'

  return (
    <div
      className="rounded-xl border-l-4 bg-white p-4 shadow-sm"
      style={{ borderColor: color }}
    >
      <div className="mb-2 flex items-center justify-between gap-2">
        <span className="flex items-center gap-2">
          <span className="text-lg">{alert.category === 'TRAVAUX' ? '🚧' : '⚠️'}</span>
          <span className="font-semibold text-gray-900 text-sm">{alert.title}</span>
        </span>
        <div className="flex shrink-0 gap-1.5">
          <span
            className="rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
            style={{ backgroundColor: categoryColor }}
          >
            {categoryLabel}
          </span>
          <span
            className="rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
            style={{ backgroundColor: color }}
          >
            {label}
          </span>
        </div>
      </div>
      <p className="text-sm text-gray-600">{alert.description}</p>
      <div className="mt-2 flex flex-wrap gap-4 text-xs text-gray-400">
        <span>{alert.lineCode}</span>
        {alert.startDate && (
          <span>Depuis le {new Date(alert.startDate).toLocaleDateString('fr-FR')}</span>
        )}
        {alert.endDate && (
          <span>Jusqu'au {new Date(alert.endDate).toLocaleDateString('fr-FR')}</span>
        )}
        {alert.estimatedResume && (
          <span>Reprise estimée : {alert.estimatedResume}</span>
        )}
      </div>
    </div>
  )
}
