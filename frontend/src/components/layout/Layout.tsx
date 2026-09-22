import { Outlet } from 'react-router-dom'
import { Navbar } from './Navbar'
import { BottomNav } from './BottomNav'
import { Footer } from './Footer'

/** Mise en page commune : barre du haut, contenu de la page, pied de page et navigation mobile. */
export function Layout() {
  return (
    <div className="flex min-h-screen flex-col bg-gray-50">
      <Navbar />
      {/* pb-10 : sans padding bas, le dernier élément d'une page — pagination, bouton,
          dernière carte — se colle au filet du footer. Réglé ici plutôt que page par page,
          où l'espacement final variait de rien du tout à pb-4 selon les écrans. */}
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 pb-10 pt-6">
        <Outlet />
      </main>
      <Footer />
      <BottomNav />
    </div>
  )
}
