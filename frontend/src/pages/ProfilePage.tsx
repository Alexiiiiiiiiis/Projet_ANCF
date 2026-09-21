import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { authService } from '../services/authService'
import { Spinner } from '../components/ui/Spinner'

/** Page profil : modifier ses infos, changer son mot de passe, supprimer son compte. */
export function ProfilePage() {
  const { user, refreshUser, logout } = useAuth()
  const navigate = useNavigate()

  const [profileForm, setProfileForm] = useState({
    firstName: user?.firstName ?? '',
    lastName: user?.lastName ?? '',
  })
  const [pwdForm, setPwdForm] = useState({ current: '', next: '', confirm: '' })
  const [deletePassword, setDeletePassword] = useState('')

  const [profileMsg, setProfileMsg] = useState('')
  const [pwdMsg, setPwdMsg] = useState('')
  const [loading, setLoading] = useState(false)
  const [showDelete, setShowDelete] = useState(false)

  /** Enregistre le prénom et le nom modifiés. */
  const updateProfile = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setProfileMsg('')
    try {
      await authService.updateProfile(profileForm)
      await refreshUser()
      setProfileMsg('Profil mis à jour.')
    } catch {
      setProfileMsg('Erreur lors de la mise à jour.')
    } finally {
      setLoading(false)
    }
  }

  /** Vérifie puis enregistre le nouveau mot de passe. */
  const changePassword = async (e: FormEvent) => {
    e.preventDefault()
    if (pwdForm.next.length < 8) { setPwdMsg('8 caractères minimum.'); return }
    if (pwdForm.next !== pwdForm.confirm) { setPwdMsg('Les deux mots de passe ne correspondent pas.'); return }
    setLoading(true)
    setPwdMsg('')
    try {
      await authService.changePassword(pwdForm.current, pwdForm.next)
      setPwdMsg('Mot de passe modifié.')
      setPwdForm({ current: '', next: '', confirm: '' })
    } catch {
      setPwdMsg('Mot de passe actuel incorrect.')
    } finally {
      setLoading(false)
    }
  }

  /** Ferme la confirmation de suppression et vide le mot de passe saisi. */
  const closeDeletePanel = () => {
    setShowDelete(false)
    setDeletePassword('')
  }

  /** Supprime le compte après confirmation par mot de passe, puis déconnecte. */
  const deleteAccount = async () => {
    setLoading(true)
    try {
      await authService.deleteAccount(deletePassword)
      logout()
      navigate('/')
    } catch {
      closeDeletePanel()
      alert('Mot de passe incorrect.')
    } finally {
      setLoading(false)
    }
  }

  if (!user) return null

  return (
    <div className="mx-auto max-w-lg space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Mon profil</h1>

      {/* Informations du profil */}
      <div className="rounded-2xl bg-white p-6 shadow-sm">
        <h2 className="mb-4 font-semibold text-gray-700">Informations personnelles</h2>
        <form onSubmit={updateProfile} className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-sm text-gray-600">Prénom</label>
              <input
                type="text"
                value={profileForm.firstName}
                onChange={(e) => setProfileForm((f) => ({ ...f, firstName: e.target.value }))}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm text-gray-600">Nom</label>
              <input
                type="text"
                value={profileForm.lastName}
                onChange={(e) => setProfileForm((f) => ({ ...f, lastName: e.target.value }))}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
              />
            </div>
          </div>
          <p className="text-sm text-gray-500">Email : {user.email}</p>
          {profileMsg && <p className="text-sm text-green-600">{profileMsg}</p>}
          <button
            type="submit"
            disabled={loading}
            className="flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60"
          >
            {loading && <Spinner size="sm" />}
            Enregistrer
          </button>
        </form>
      </div>

      {/* Changement de mot de passe */}
      <div className="rounded-2xl bg-white p-6 shadow-sm">
        <h2 className="mb-4 font-semibold text-gray-700">Changer le mot de passe</h2>
        <form onSubmit={changePassword} className="space-y-3">
          <div>
            <label className="mb-1 block text-sm text-gray-600">Mot de passe actuel</label>
            <input
              type="password"
              value={pwdForm.current}
              onChange={(e) => setPwdForm((f) => ({ ...f, current: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm text-gray-600">Nouveau mot de passe</label>
            <input
              type="password"
              value={pwdForm.next}
              onChange={(e) => setPwdForm((f) => ({ ...f, next: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm text-gray-600">Confirmer le nouveau mot de passe</label>
            <input
              type="password"
              value={pwdForm.confirm}
              onChange={(e) => setPwdForm((f) => ({ ...f, confirm: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>
          {pwdMsg && <p className="text-sm text-green-600">{pwdMsg}</p>}
          <button
            type="submit"
            disabled={loading}
            className="flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60"
          >
            {loading && <Spinner size="sm" />}
            Modifier
          </button>
        </form>
      </div>

      {/* Zone sensible : suppression du compte */}
      <div className="rounded-2xl border border-red-200 bg-red-50 p-6">
        <h2 className="mb-2 font-semibold text-red-700">Zone dangereuse</h2>
        <p className="mb-4 text-sm text-red-600">
          La suppression de votre compte est irréversible. Toutes vos données seront effacées (RGPD).
        </p>
        {!showDelete ? (
          <button
            onClick={() => setShowDelete(true)}
            className="rounded-lg border border-red-400 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
          >
            Supprimer mon compte
          </button>
        ) : (
          <div className="space-y-3">
            <input
              type="password"
              value={deletePassword}
              onChange={(e) => setDeletePassword(e.target.value)}
              placeholder="Confirmez votre mot de passe"
              className="w-full rounded-lg border border-red-300 px-3 py-2 text-sm outline-none"
            />
            <div className="flex gap-2">
              <button
                onClick={deleteAccount}
                disabled={loading}
                className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
              >
                {loading && <Spinner size="sm" />}
                Confirmer la suppression
              </button>
              <button
                onClick={closeDeletePanel}
                className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
              >
                Annuler
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
