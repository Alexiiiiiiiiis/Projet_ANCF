interface FavoriteStarProps {
  active: boolean
  onToggle: () => void
  /** Ce que l'étoile met en favori, pour l'infobulle et les lecteurs d'écran */
  label: string
}

/** Étoile de mise en favori, telle qu'elle coiffe une fiche de ligne ou d'arrêt chez IDFM. */
export function FavoriteStar({ active, onToggle, label }: FavoriteStarProps) {
  const action = active ? `Retirer ${label} des favoris` : `Ajouter ${label} aux favoris`

  return (
    <button
      onClick={onToggle}
      className="shrink-0 text-2xl leading-none transition-transform hover:scale-110"
      aria-label={action}
      aria-pressed={active}
      title={action}
    >
      {active ? '⭐' : '☆'}
    </button>
  )
}
