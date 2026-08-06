import { useEffect, useState } from 'react'
import { useDebounce } from '../../hooks/useDebounce'
import { useQuery } from '@tanstack/react-query'
import { transportService } from '../../services/transportService'
import type { Stop } from '../../types/transport'
import { TRANSPORT_COLORS, TRANSPORT_ICONS, TRANSPORT_LABELS } from '../../types/transport'

interface SearchBarProps {
  onSelect: (stop: Stop) => void
  placeholder?: string
  type?: string
  /** Requête injectée de l'extérieur (ex. clic sur une recherche récente) */
  presetQuery?: string
}

export function SearchBar({ onSelect, placeholder = 'Rechercher un arrêt...', type, presetQuery }: SearchBarProps) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const debouncedQuery = useDebounce(query, 400)

  useEffect(() => {
    if (presetQuery) {
      setQuery(presetQuery)
      setOpen(true)
    }
  }, [presetQuery])

  const { data: stops = [], isFetching } = useQuery({
    queryKey: ['stop-search', debouncedQuery, type],
    queryFn: () => transportService.searchStops({ q: debouncedQuery, type, limit: 8 }),
    enabled: debouncedQuery.length >= 2,
    staleTime: 60_000,
  })

  const handleSelect = (stop: Stop) => {
    setQuery(stop.name)
    setOpen(false)
    onSelect(stop)
  }

  return (
    <div className="relative w-full">
      <div className="relative">
        <input
          type="text"
          value={query}
          onChange={(e) => { setQuery(e.target.value); setOpen(true) }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          placeholder={placeholder}
          className="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 pl-10 text-sm shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        />
        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
          {isFetching ? '⏳' : '🔍'}
        </span>
        {query && (
          <button
            onClick={() => { setQuery(''); setOpen(false) }}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        )}
      </div>

      {open && stops.length > 0 && (
        <ul className="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-auto rounded-xl border border-gray-200 bg-white shadow-lg">
          {stops.map((stop) => (
            <li key={stop.id}>
              <button
                className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm hover:bg-blue-50 transition-colors"
                onMouseDown={() => handleSelect(stop)}
              >
                <span
                  className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm"
                  style={{ backgroundColor: TRANSPORT_COLORS[stop.transportType] }}
                  title={TRANSPORT_LABELS[stop.transportType]}
                >
                  {TRANSPORT_ICONS[stop.transportType]}
                </span>
                <span className="flex-1 font-medium text-gray-800">{stop.name}</span>
                {stop.lines && stop.lines.length > 0 && (
                  <span className="text-xs text-gray-400">{stop.lines.slice(0, 3).join(', ')}</span>
                )}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
