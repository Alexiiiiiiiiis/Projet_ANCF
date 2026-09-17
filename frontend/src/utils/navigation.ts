/**
 * React Router numérote les entrées d'historique qu'il a poussées lui-même, dans
 * `window.history.state.idx` (remis à 0 au premier rendu s'il est absent).
 *
 * À 0, la page a été ouverte directement — lien partagé, favori, nouvel onglet — et
 * un `navigate(-1)` ferait sortir du site au lieu de ramener sur l'application.
 */
export function peutRevenirEnArriere(): boolean {
  const index = (window.history.state as { idx?: number } | null)?.idx
  return typeof index === 'number' && index > 0
}
