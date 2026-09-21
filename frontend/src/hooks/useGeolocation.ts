import { useState, useEffect } from 'react'

interface GeoState {
  lat: number | null
  lon: number | null
  error: string | null
  loading: boolean
}

/** Suit la position GPS de l'utilisateur (latitude, longitude, erreur, chargement). */
export function useGeolocation() {
  const [state, setState] = useState<GeoState>({ lat: null, lon: null, error: null, loading: true })

  useEffect(() => {
    if (!navigator.geolocation) {
      setState({ lat: null, lon: null, error: 'Géolocalisation non supportée', loading: false })
      return
    }

    const id = navigator.geolocation.watchPosition(
      ({ coords }) => {
        setState({ lat: coords.latitude, lon: coords.longitude, error: null, loading: false })
      },
      (err) => {
        setState({ lat: null, lon: null, error: err.message, loading: false })
      },
      { enableHighAccuracy: true, timeout: 10_000, maximumAge: 30_000 }
    )

    return () => navigator.geolocation.clearWatch(id)
  }, [])

  return state
}
