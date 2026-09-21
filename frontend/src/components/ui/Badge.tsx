interface BadgeProps {
  label: string
  color?: string
  className?: string
}

/** Petite pastille colorée avec un texte. */
export function Badge({ label, color = '#1a73e8', className = '' }: BadgeProps) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white ${className}`}
      style={{ backgroundColor: color }}
    >
      {label}
    </span>
  )
}
