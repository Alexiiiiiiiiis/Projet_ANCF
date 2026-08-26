import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { transportService, type FavoriteInput } from '../services/transportService'
import { useAuth } from '../context/AuthContext'

/**
 * Favoris de l'utilisateur connecté — arrêts comme lignes. Les quatre pages qui proposent
 * l'étoile (accueil, Horaires, fiche de ligne, fiche d'arrêt) partagent ainsi la même liste,
 * les mêmes messages d'erreur et le même rafraîchissement.
 */
export function useFavorites() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)

  const { data: favorites = [] } = useQuery({
    queryKey: ['favorites'],
    queryFn: transportService.getFavorites,
    enabled: !!user,
  })

  const onSuccess = () => {
    queryClient.invalidateQueries({ queryKey: ['favorites'] })
    setError(null)
  }

  const addMutation = useMutation({
    mutationFn: transportService.addFavorite,
    onSuccess,
    onError: () => setError("Impossible d'ajouter ce favori. Réessayez."),
  })

  const removeMutation = useMutation({
    mutationFn: transportService.removeFavorite,
    onSuccess,
    onError: () => setError('Impossible de retirer ce favori. Réessayez.'),
  })

  // Un arrêt et une ligne ne peuvent pas partager d'identifiant : « stop_area:IDFM:… » et
  // « line:IDFM:… » suffisent à distinguer les deux sortes de favoris.
  const find = (id: string) => favorites.find((f) => f.stopId === id)

  return {
    favorites,
    stops: favorites.filter((f) => f.kind !== 'LINE'),
    lines: favorites.filter((f) => f.kind === 'LINE'),
    /** false quand personne n'est connecté : l'étoile ne doit alors pas être proposée */
    canFavorite: !!user,
    isFavorite: (id: string) => !!find(id),
    toggle: (favorite: FavoriteInput) => {
      if (!user) return

      const existing = find(favorite.stopId)
      if (existing) {
        removeMutation.mutate(existing.id)
      } else {
        addMutation.mutate(favorite)
      }
    },
    error,
    clearError: () => setError(null),
  }
}
