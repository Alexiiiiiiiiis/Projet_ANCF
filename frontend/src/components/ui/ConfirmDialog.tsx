import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'

interface ConfirmDialogProps {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  cancelLabel?: string
  onConfirm: () => void
  onCancel: () => void
}

/**
 * Boîte de confirmation pour une action qu'on ne veut pas déclencher par mégarde.
 *
 * Rendue dans un portail attaché au <body> : la barre du haut et la barre du bas sont toutes deux
 * en z-50, et une superposition rendue à l'intérieur de l'une d'elles passerait sous l'autre sur
 * mobile — le portail sort de leur contexte d'empilement.
 */
export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel,
  cancelLabel = 'Annuler',
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const annulerRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    if (!open) return

    const surTouche = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onCancel()
    }
    document.addEventListener('keydown', surTouche)

    // Le focus part sur « Annuler » : à l'ouverture, l'option sûre doit être celle qu'une
    // validation au clavier déclenche.
    annulerRef.current?.focus()

    return () => document.removeEventListener('keydown', surTouche)
  }, [open, onCancel])

  if (!open || typeof document === 'undefined') return null

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 px-4"
      onClick={onCancel}
      role="presentation"
    >
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-titre"
        aria-describedby="confirm-message"
        className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"
        // Sans ça, un clic à l'intérieur remonterait jusqu'à la superposition et fermerait
        // la boîte alors que l'utilisateur visait un bouton.
        onClick={(e) => e.stopPropagation()}
      >
        <h2 id="confirm-titre" className="mb-2 text-lg font-semibold text-gray-900">
          {title}
        </h2>
        <p id="confirm-message" className="mb-5 text-sm text-gray-600">
          {message}
        </p>

        <div className="flex justify-end gap-2">
          <button
            ref={annulerRef}
            onClick={onCancel}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
          >
            {cancelLabel}
          </button>
          <button
            onClick={onConfirm}
            className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-800"
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>,
    document.body
  )
}
