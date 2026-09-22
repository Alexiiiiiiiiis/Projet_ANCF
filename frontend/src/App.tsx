import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from './context/AuthContext'
import { Layout } from './components/layout/Layout'
import { ProtectedRoute } from './components/ui/ProtectedRoute'
import { ErrorBoundary } from './components/ui/ErrorBoundary'
import { MaintenanceGate } from './components/ui/MaintenanceGate'
import { Home } from './pages/Home'
import { MapPage } from './pages/MapPage'
import { SchedulesPage } from './pages/SchedulesPage'
import { LinePage } from './pages/LinePage'
import { StopSchedulePage } from './pages/StopSchedulePage'
import { JourneyPage } from './pages/JourneyPage'
import { AlertsPage } from './pages/AlertsPage'
import { FavoritesPage } from './pages/FavoritesPage'
import { ProfilePage } from './pages/ProfilePage'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import { AdminPage } from './pages/AdminPage'
import { ForgotPasswordPage } from './pages/ForgotPasswordPage'
import { ResetPasswordPage } from './pages/ResetPasswordPage'
import { MentionsLegalesPage } from './pages/MentionsLegalesPage'
import { PolitiqueConfidentialitePage } from './pages/PolitiqueConfidentialitePage'
import { NotFoundPage } from './pages/NotFoundPage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

/** Racine de l'application : fournisseurs globaux (React Query, authentification) et toutes les routes. */
export default function App() {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
            <MaintenanceGate>
              <Routes>
                <Route element={<Layout />}>
                  <Route path="/" element={<Home />} />
                  <Route path="/carte" element={<MapPage />} />
                  <Route path="/horaires" element={<SchedulesPage />} />
                  <Route path="/horaires/ligne/:lineId" element={<LinePage />} />
                  <Route path="/horaires/arret/:stopId" element={<StopSchedulePage />} />
                  <Route
                    path="/itineraire"
                    element={
                      <ProtectedRoute>
                        <JourneyPage />
                      </ProtectedRoute>
                    }
                  />
                  <Route path="/alertes" element={<AlertsPage />} />
                  <Route path="/mentions-legales" element={<MentionsLegalesPage />} />
                  <Route path="/politique-confidentialite" element={<PolitiqueConfidentialitePage />} />
                  <Route
                    path="/favoris"
                    element={
                      <ProtectedRoute>
                        <FavoritesPage />
                      </ProtectedRoute>
                    }
                  />
                  <Route
                    path="/profil"
                    element={
                      <ProtectedRoute>
                        <ProfilePage />
                      </ProtectedRoute>
                    }
                  />
                  <Route
                    path="/admin"
                    element={
                      <ProtectedRoute requireAdmin>
                        <AdminPage />
                      </ProtectedRoute>
                    }
                  />
                  <Route path="*" element={<NotFoundPage />} />
                </Route>
                <Route path="/connexion" element={<LoginPage />} />
                <Route path="/inscription" element={<RegisterPage />} />
                <Route path="/mot-de-passe-oublie" element={<ForgotPasswordPage />} />
                <Route path="/reinitialiser-mot-de-passe" element={<ResetPasswordPage />} />
              </Routes>
            </MaintenanceGate>
          </BrowserRouter>
        </AuthProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  )
}
