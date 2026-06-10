import { Link } from 'react-router-dom'

export function MentionsLegalesPage() {
  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="mb-6 text-2xl font-bold text-gray-900">Mentions Legales</h1>

      <div className="space-y-6 rounded-2xl bg-white p-8 shadow-sm text-sm text-gray-700 leading-relaxed">
        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Editeur du site</h2>
          <p>
            <strong>Transport ANCF</strong> — Projet realise dans le cadre de la formation
            Concepteur Developpeur d'Applications (CDA) a l'ecole IPSSI Paris.
          </p>
          <ul className="mt-2 list-disc pl-5 space-y-1">
            <li>Responsable : Alexis Rodrigues</li>
            <li>Email : contact@ancf-transport.fr</li>
            <li>Formation : Bachelor CDA 2025-2026 — IPSSI Paris</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Hebergement</h2>
          <p>
            Ce site est heberge dans le cadre d'un projet pedagogique.
            Les donnees sont stockees sur des serveurs Docker en environnement de developpement.
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Propriete intellectuelle</h2>
          <p>
            L'ensemble du contenu de ce site (textes, images, code source) est la propriete
            de son auteur ou fait l'objet d'une autorisation d'utilisation. Toute reproduction
            est interdite sans autorisation prealable.
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Donnees de transport</h2>
          <p>
            Les donnees de transport en temps reel sont fournies par
            l'API <strong>Ile-de-France Mobilites (IDFM)</strong> via la plateforme PRIM.
            Ces donnees sont mises a disposition sous licence ouverte.
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Cookies</h2>
          <p>
            Ce site utilise uniquement des cookies techniques necessaires au bon
            fonctionnement de l'application (authentification JWT, preferences utilisateur).
            Aucun cookie publicitaire ou de suivi n'est utilise.
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Protection des donnees</h2>
          <p>
            Pour en savoir plus sur la collecte et le traitement de vos donnees personnelles,
            consultez notre{' '}
            <Link to="/politique-confidentialite" className="text-blue-700 hover:underline font-medium">
              Politique de Confidentialite
            </Link>.
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Limitation de responsabilite</h2>
          <p>
            Les informations de transport affichees sont fournies a titre indicatif.
            Transport ANCF ne saurait etre tenu responsable en cas d'inexactitude
            des horaires ou des informations de perturbation affichees.
          </p>
        </section>
      </div>
    </div>
  )
}
