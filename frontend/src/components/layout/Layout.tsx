import { Outlet } from 'react-router-dom'
import { Navbar } from './Navbar'
import { BottomNav } from './BottomNav'
import { Footer } from './Footer'

export function Layout() {
  return (
    <div className="flex min-h-screen flex-col bg-gray-50">
      <Navbar />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 pt-6">
        <Outlet />
      </main>
      <Footer />
      <BottomNav />
    </div>
  )
}
