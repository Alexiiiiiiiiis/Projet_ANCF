import { Component, type ReactNode, type ErrorInfo } from 'react'

interface Props {
  children: ReactNode
}

interface State {
  hasError: boolean
  error: Error | null
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, error: null }

  /** Passe en mode erreur quand un composant enfant plante. */
  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error }
  }

  /** Écrit l'erreur et la pile des composants dans la console. */
  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('[ErrorBoundary]', error, info.componentStack)
  }

  /** Efface l'erreur pour réessayer d'afficher la page. */
  handleReset = () => {
    this.setState({ hasError: false, error: null })
  }

  /** Affiche l'écran d'erreur si besoin, sinon la page normale. */
  render() {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-[60vh] flex-col items-center justify-center text-center px-4">
          <p className="text-5xl font-bold text-red-600">Oups</p>
          <h1 className="mt-4 text-xl font-bold text-gray-900">Une erreur est survenue</h1>
          <p className="mt-2 text-sm text-gray-500">
            L'application a rencontre un probleme inattendu.
          </p>
          {import.meta.env.DEV && this.state.error && (
            <pre className="mt-4 max-w-lg overflow-auto rounded-lg bg-red-50 p-4 text-left text-xs text-red-800">
              {this.state.error.message}
            </pre>
          )}
          <button
            onClick={this.handleReset}
            className="mt-6 rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800 transition-colors"
          >
            Reessayer
          </button>
        </div>
      )
    }

    return this.props.children
  }
}
