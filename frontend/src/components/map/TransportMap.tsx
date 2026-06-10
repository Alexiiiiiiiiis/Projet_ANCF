import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet'
import L from 'leaflet'
import type { Stop } from '../../types/transport'
import { TRANSPORT_COLORS } from '../../types/transport'
import 'leaflet/dist/leaflet.css'

// Fix leaflet default icon
delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
})

function makeIcon(color: string) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="38" viewBox="0 0 28 38">
    <path d="M14 0C6.27 0 0 6.27 0 14c0 5.5 3.18 10.29 7.84 12.73L14 38l6.16-11.27C24.82 24.29 28 19.5 28 14 28 6.27 21.73 0 14 0z" fill="${color}"/>
    <circle cx="14" cy="14" r="7" fill="white"/>
  </svg>`
  return L.divIcon({
    html: svg,
    className: '',
    iconSize: [28, 38],
    iconAnchor: [14, 38],
    popupAnchor: [0, -38],
  })
}

function RecenterMap({ lat, lon }: { lat: number; lon: number }) {
  const map = useMap()
  map.setView([lat, lon], map.getZoom())
  return null
}

interface TransportMapProps {
  stops: Stop[]
  userLat?: number | null
  userLon?: number | null
  onStopClick?: (stop: Stop) => void
}

export function TransportMap({ stops, userLat, userLon, onStopClick }: TransportMapProps) {
  const centerLat = userLat ?? 48.8566
  const centerLon = userLon ?? 2.3522

  return (
    <MapContainer
      center={[centerLat, centerLon]}
      zoom={14}
      style={{ height: '100%', width: '100%', borderRadius: '0.75rem' }}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />

      {userLat && userLon && (
        <>
          <RecenterMap lat={userLat} lon={userLon} />
          <Marker
            position={[userLat, userLon]}
            icon={makeIcon('#3b82f6')}
          >
            <Popup>Votre position</Popup>
          </Marker>
        </>
      )}

      {stops.map((stop) => (
        stop.lat && stop.lon ? (
          <Marker
            key={stop.id}
            position={[stop.lat, stop.lon]}
            icon={makeIcon(TRANSPORT_COLORS[stop.transportType] ?? '#6b7280')}
            eventHandlers={{ click: () => onStopClick?.(stop) }}
          >
            <Popup>
              <div className="text-sm">
                <p className="font-semibold">{stop.name}</p>
                {stop.lines && <p className="text-gray-500">Lignes : {stop.lines.join(', ')}</p>}
                {stop.distanceLabel && <p className="text-blue-600">{stop.distanceLabel}</p>}
              </div>
            </Popup>
          </Marker>
        ) : null
      ))}
    </MapContainer>
  )
}
