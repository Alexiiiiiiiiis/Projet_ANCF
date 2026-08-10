import { describe, it, expect } from 'vitest'

// Haversine formula (mirrors backend GeoService)
function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371000
  const toRad = (deg: number) => (deg * Math.PI) / 180
  const dLat = toRad(lat2 - lat1)
  const dLon = toRad(lon2 - lon1)
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function formatDistance(meters: number): string {
  if (meters < 1000) return `${Math.round(meters)}m`
  return `${(meters / 1000).toFixed(1)}km`
}

describe('Haversine distance calculation', () => {
  it('returns 0 for same point', () => {
    expect(calculateDistance(48.8566, 2.3522, 48.8566, 2.3522)).toBe(0)
  })

  it('Paris to Lyon is approximately 392km', () => {
    const d = calculateDistance(48.8566, 2.3522, 45.7640, 4.8357)
    expect(d).toBeGreaterThan(390_000)
    expect(d).toBeLessThan(395_000)
  })

  it('short walk within 500m', () => {
    const d = calculateDistance(48.8566, 2.3522, 48.8596, 2.3522)
    expect(d).toBeLessThan(500)
  })
})

describe('formatDistance', () => {
  it('formats meters under 1km', () => {
    expect(formatDistance(250)).toBe('250m')
  })

  it('formats kilometers', () => {
    expect(formatDistance(1500)).toBe('1.5km')
  })
})
