import { describe, it, expect, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { Navbar } from '../components/layout/Navbar'
import type { User } from '../types/transport'

const session = { user: null as User | null, isAdmin: false, logout: vi.fn() }

vi.mock('../context/AuthContext', () => ({ useAuth: () => session }))
vi.mock('../hooks/useTheme', () => ({
  useTheme: () => ({ theme: 'light', toggleTheme: vi.fn() }),
}))

function rendreConnecte(roles: string[]) {
  session.user = {
    id: 1,
    email: 'admin@ancf.fr',
    firstName: 'Alexis',
    lastName: 'Rodrigues',
    roles,
    isActive: true,
    createdAt: '2026-01-01T00:00:00+01:00',
  }
  session.isAdmin = roles.includes('ROLE_ADMIN')

  return renderToStaticMarkup(
    <MemoryRouter>
      <Navbar />
    </MemoryRouter>
  )
}

const lienAdminMobile = (html: string) =>
  html.match(/<a[^>]*aria-label="Dashboard admin"[^>]*>/)?.[0] ?? ''

describe('accès au dashboard admin sur mobile', () => {
  it('propose un raccourci admin en dehors de la navigation réservée au desktop', () => {
    const html = rendreConnecte(['ROLE_USER', 'ROLE_ADMIN'])

    // La <nav> principale est en `hidden md:flex` : un lien placé dedans est
    // invisible sur mobile, d'où la vérification de la position du raccourci.
    expect(html.slice(html.indexOf('</nav>'))).toContain('aria-label="Dashboard admin"')
    expect(lienAdminMobile(html)).toContain('href="/admin"')
  })

  it('masque ce raccourci sur desktop, où le menu affiche déjà Admin', () => {
    const html = rendreConnecte(['ROLE_ADMIN'])

    expect(lienAdminMobile(html)).toContain('md:hidden')
    expect(lienAdminMobile(html)).not.toMatch(/class(Name)?="hidden\s/)
  })

  it("n'expose rien vers /admin à un utilisateur sans le rôle", () => {
    expect(rendreConnecte(['ROLE_USER'])).not.toContain('href="/admin"')
  })
})
