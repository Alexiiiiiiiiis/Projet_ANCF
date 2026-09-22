import { describe, it, expect, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { Navbar } from '../components/layout/Navbar'
import { BottomNav } from '../components/layout/BottomNav'
import type { User } from '../types/transport'

const session = { user: null as User | null, isAdmin: false, logout: vi.fn() }

vi.mock('../context/AuthContext', () => ({ useAuth: () => session }))
vi.mock('../hooks/useTheme', () => ({
  useTheme: () => ({ theme: 'light', toggleTheme: vi.fn() }),
}))

function connecter() {
  session.user = {
    id: 1,
    email: 'user@ancf.fr',
    firstName: 'Alexis',
    lastName: 'Rodrigues',
    roles: ['ROLE_USER'],
    isActive: true,
    createdAt: '2026-01-01T00:00:00+01:00',
  }
  session.isAdmin = false
}

const rendre = (composant: JSX.Element) =>
  renderToStaticMarkup(<MemoryRouter>{composant}</MemoryRouter>)

const pastilleCompte = (html: string) =>
  html.match(/<a[^>]*aria-label="Mon compte"[^>]*>/)?.[0] ?? ''

/**
 * La page de profil porte la suppression du compte : la rendre injoignable sur mobile
 * reviendrait à retirer ce droit à la majorité des utilisateurs d'une application de transport.
 */
describe('accès à la page de compte sur mobile', () => {
  it('expose un raccourci vers le profil en dehors de la navigation réservée au desktop', () => {
    connecter()
    const html = rendre(<Navbar />)

    // La <nav> principale est en `hidden md:flex` : un lien placé dedans reste invisible sur
    // mobile, d'où la vérification de la position du raccourci.
    expect(html.slice(html.indexOf('</nav>'))).toContain('aria-label="Mon compte"')
    expect(pastilleCompte(html)).toContain('href="/profil"')
  })

  it('masque ce raccourci sur desktop, où le prénom mène déjà au profil', () => {
    connecter()
    const html = rendre(<Navbar />)

    expect(pastilleCompte(html)).toContain('md:hidden')
    expect(pastilleCompte(html)).not.toMatch(/class(Name)?="hidden\s/)
  })

  it('garde un libellé de déconnexion lisible sur desktop et une icône sur mobile', () => {
    connecter()
    const html = rendre(<Navbar />)

    expect(html).toContain('aria-label="Se déconnecter"')
    expect(html).toContain('Déconnexion')
  })

  it("n'expose aucun accès au profil à un visiteur non connecté", () => {
    session.user = null
    session.isAdmin = false

    expect(rendre(<Navbar />)).not.toContain('href="/profil"')
  })

  /**
   * La barre du bas affiche déjà six entrées pour un utilisateur connecté : elle ne peut pas
   * absorber le profil sans tasser les libellés, c'est pourquoi le raccourci vit dans la barre
   * du haut. Ce test fige cette répartition.
   */
  it('laisse la barre du bas à la navigation de consultation', () => {
    connecter()
    const html = rendre(<BottomNav />)

    expect(html).not.toContain('href="/profil"')
  })
})
