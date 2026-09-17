import { describe, it, expect, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { LoginPage } from '../pages/LoginPage'
import { RegisterPage } from '../pages/RegisterPage'
import { peutRevenirEnArriere } from '../utils/navigation'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ login: vi.fn() }) }))
vi.mock('../services/authService', () => ({ authService: { register: vi.fn() } }))

// Les deux écrans sont rendus hors du Layout (ni Navbar ni BottomNav) : sans ce
// bouton, renoncer à se connecter ou à s'inscrire laisse l'utilisateur coincé.
const rendre = (page: JSX.Element) => renderToStaticMarkup(<MemoryRouter>{page}</MemoryRouter>)

const boutonRetour = (html: string) => html.match(/<button type="button"[\s\S]*?<\/button>/)?.[0] ?? ''

describe('sortie des écrans de connexion et inscription', () => {
  it('offre un retour depuis la connexion', () => {
    expect(boutonRetour(rendre(<LoginPage />))).toContain('Retour')
  })

  it("offre un retour depuis l'inscription", () => {
    expect(boutonRetour(rendre(<RegisterPage />))).toContain('Retour')
  })

  it("ne se fait pas passer pour un bouton d'envoi du formulaire", () => {
    const html = rendre(<RegisterPage />)

    expect(boutonRetour(html)).toContain('type="button"')
    expect(html).toContain('Créer mon compte')
  })
})

describe('peutRevenirEnArriere', () => {
  it('autorise le retour quand une page de l\'application précède', () => {
    window.history.replaceState({ idx: 3 }, '')

    expect(peutRevenirEnArriere()).toBe(true)
  })

  it("refuse le retour sur la première entrée, qui sortirait du site", () => {
    window.history.replaceState({ idx: 0 }, '')

    expect(peutRevenirEnArriere()).toBe(false)
  })

  it("refuse le retour quand l'historique n'est pas celui du routeur", () => {
    window.history.replaceState(null, '')

    expect(peutRevenirEnArriere()).toBe(false)
  })
})
