import { describe, it, expect, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { LoginPage } from '../pages/LoginPage'
import { RegisterPage } from '../pages/RegisterPage'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ login: vi.fn() }) }))
vi.mock('../services/authService', () => ({ authService: { register: vi.fn() } }))

const classesDesChamps = (page: JSX.Element) =>
  [...renderToStaticMarkup(<MemoryRouter>{page}</MemoryRouter>).matchAll(/<input[^>]*class="([^"]*)"/g)].map(
    ([, classes]) => classes.split(/\s+/)
  )

// iOS Safari zoome toute la page dès qu'on touche un champ écrit en moins de 16px,
// et ne dézoome pas en sortant : le formulaire déborde alors de l'écran.
describe('champs des écrans de connexion et inscription sur mobile', () => {
  for (const [nom, page] of [
    ['connexion', <LoginPage key="connexion" />],
    ['inscription', <RegisterPage key="inscription" />],
  ] as const) {
    it(`restent en 16px sur la page ${nom} pour éviter le zoom automatique`, () => {
      const champs = classesDesChamps(page)

      expect(champs.length).toBeGreaterThan(0)
      for (const classes of champs) {
        expect(classes).toContain('text-base')
        expect(classes).not.toContain('text-sm')
      }
    })
  }
})
