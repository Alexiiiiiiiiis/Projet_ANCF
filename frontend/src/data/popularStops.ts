import type { Stop } from '../types/transport'

// Grands pôles d'échange franciliens (IDs réels IDFM) proposés en raccourci quand
// l'utilisateur n'a ni recherché ni activé la géolocalisation
export const POPULAR_STOPS: Stop[] = [
  { id: 'stop_area:IDFM:71264', name: 'Châtelet', lat: 48.8583, lon: 2.3485, transportType: 'METRO', lines: ['M1', 'M4', 'M7', 'M11', 'M14'] },
  { id: 'stop_area:IDFM:474151', name: 'Châtelet - Les Halles', lat: 48.8617, lon: 2.347, transportType: 'RER', lines: ['RER A', 'RER B', 'RER D'] },
  { id: 'stop_area:IDFM:71410', name: 'Gare du Nord', lat: 48.8809, lon: 2.3553, transportType: 'METRO', lines: ['M4', 'M5'] },
  { id: 'stop_area:IDFM:73626', name: 'Gare de Lyon', lat: 48.8445, lon: 2.3735, transportType: 'METRO', lines: ['M1', 'M14'] },
  { id: 'stop_area:IDFM:71517', name: 'La Défense', lat: 48.8921, lon: 2.2391, transportType: 'METRO', lines: ['M1'] },
  { id: 'stop_area:IDFM:71370', name: 'Gare Saint-Lazare', lat: 48.875, lon: 2.325, transportType: 'METRO', lines: ['M3', 'M12', 'M13', 'M14'] },
  { id: 'stop_area:IDFM:71045', name: 'Porte de Versailles', lat: 48.8324, lon: 2.2879, transportType: 'TRAM', lines: ['T2', 'T3a', 'M12'] },
  { id: 'stop_area:IDFM:71673', name: 'Nation', lat: 48.8488, lon: 2.3963, transportType: 'METRO', lines: ['M1', 'M2', 'M6', 'M9'] },

  // RER — une entrée par ligne (A/B/D déjà couvertes par Châtelet - Les Halles ci-dessus)
  { id: 'stop_area:IDFM:rer_defense', name: 'La Défense', lat: 48.8921, lon: 2.2391, transportType: 'RER', lines: ['RER A'] },
  { id: 'stop_area:IDFM:rer_denfert', name: 'Denfert-Rochereau', lat: 48.8339, lon: 2.3327, transportType: 'RER', lines: ['RER B'] },
  { id: 'stop_area:IDFM:rer_invalides', name: 'Invalides', lat: 48.8615, lon: 2.314, transportType: 'RER', lines: ['RER C'] },
  { id: 'stop_area:IDFM:rer_lyon_d', name: 'Gare de Lyon', lat: 48.8443, lon: 2.373, transportType: 'RER', lines: ['RER D'] },
  { id: 'stop_area:IDFM:rer_magenta', name: 'Magenta', lat: 48.8768, lon: 2.3565, transportType: 'RER', lines: ['RER E'] },

  // Tram — quelques lignes supplémentaires (T2/T3a déjà couvertes par Porte de Versailles ci-dessus)
  { id: 'stop_area:IDFM:tram_saint_denis', name: 'Marché de Saint-Denis', lat: 48.9356, lon: 2.3573, transportType: 'TRAM', lines: ['T1'] },
  { id: 'stop_area:IDFM:tram_bondy', name: 'Bondy', lat: 48.9019, lon: 2.4795, transportType: 'TRAM', lines: ['T4'] },
  { id: 'stop_area:IDFM:tram_athis_mons', name: 'Athis-Mons', lat: 48.7113, lon: 2.3893, transportType: 'TRAM', lines: ['T7'] },
]
