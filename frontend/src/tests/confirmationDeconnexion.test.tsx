import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { act } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import { MemoryRouter } from 'react-router-dom'
import { Navbar } from '../components/layout/Navbar'
import type { User } from '../types/transport'

const session = {
  user: {
    id: 1,
    email: 'user@ancf.fr',
    firstName: 'Alexis',
    lastName: 'Rodrigues',
    roles: ['ROLE_USER'],
    isActive: true,
    createdAt: '2026-01-01T00:00:00+01:00',
  } as User | null,
  isAdmin: false,
  logout: vi.fn(),
}

// Sans ce drapeau, React 18 avertit à chaque act() que l'environnement ne le prend pas en
// charge : les tests passent, mais la sortie devient illisible.
;(globalThis as unknown as { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true

vi.mock('../context/AuthContext', () => ({ useAuth: () => session }))
vi.mock('../hooks/useTheme', () => ({
  useTheme: () => ({ theme: 'light', toggleTheme: vi.fn() }),
}))

let conteneur: HTMLDivElement
let racine: Root

beforeEach(() => {
  session.logout = vi.fn()
  // matchMedia n'existe pas dans jsdom, et ConfirmDialog n'en dépend pas — mais Navbar rend
  // d'autres composants qui pourraient l'appeler.
  window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia

  conteneur = document.createElement('div')
  document.body.appendChild(conteneur)
  racine = createRoot(conteneur)

  act(() => {
    racine.render(
      <MemoryRouter>
        <Navbar />
      </MemoryRouter>
    )
  })
})

afterEach(() => {
  act(() => racine.unmount())
  conteneur.remove()
})

const boutonDeconnexion = () =>
  document.querySelector<HTMLButtonElement>('button[aria-label="Se déconnecter"]')!

const dialogue = () => document.querySelector('[role="alertdialog"]')

const cliquer = (element: Element | null) =>
  act(() => {
    element?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
  })

/**
 * Sur mobile le bouton est réduit à une icône, collée à la pastille de compte : un appui
 * involontaire déconnecterait l'utilisateur sans aucun moyen d'annuler.
 */
describe('confirmation avant déconnexion', () => {
  it("n'affiche aucune boîte de dialogue tant qu'on ne clique pas", () => {
    expect(dialogue()).toBeNull()
    expect(session.logout).not.toHaveBeenCalled()
  })

  it('demande confirmation au lieu de déconnecter immédiatement', () => {
    cliquer(boutonDeconnexion())

    expect(dialogue()).not.toBeNull()
    expect(session.logout).not.toHaveBeenCalled()
  })

  it('déconnecte seulement après validation', () => {
    cliquer(boutonDeconnexion())

    const valider = [...document.querySelectorAll('[role="alertdialog"] button')].find(
      (b) => b.textContent === 'Se déconnecter'
    )
    cliquer(valider ?? null)

    expect(session.logout).toHaveBeenCalledTimes(1)
  })

  it('referme sans déconnecter quand on annule', () => {
    cliquer(boutonDeconnexion())

    const annuler = [...document.querySelectorAll('[role="alertdialog"] button')].find(
      (b) => b.textContent === 'Annuler'
    )
    cliquer(annuler ?? null)

    expect(dialogue()).toBeNull()
    expect(session.logout).not.toHaveBeenCalled()
  })

  it('referme sans déconnecter sur la touche Échap', () => {
    cliquer(boutonDeconnexion())

    act(() => {
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    })

    expect(dialogue()).toBeNull()
    expect(session.logout).not.toHaveBeenCalled()
  })
})
